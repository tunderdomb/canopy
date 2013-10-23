<?php
namespace canopy;

use canopy\database\SQLTable;
use canopy\database\SQLHandler;
use canopy\parse\String;
use canopy\request\Session;

class DataFactory {
  protected $table;
  protected $idColumn;
  protected $show;


  protected $fieldMap = array();

  protected $branches = array();

  public $error;

  function __construct(   ){}

  /*
   * {
   *   params: request | post | get | json | session | url,
   *   source: "factory.class.path",
   *   branch: "<data branch name in factory to fetch>",
   *   group: "<column name to group by>"
   * }
   * params: {
   *   page: <number>,
   *  show: <number>
   * }
   * page is a special parameter that appends a limiter to the end of the query
   * show is the number of results to return in this case
   * */
  public function run( SQLHandler $connection, $requestOptions = array() ) {
    unset($this->error);
    $branch = $requestOptions['branch'];

    if ( !$branch || !array_key_exists($branch, $this->branches) ) return;

    switch( $this->branches[$branch] ){
      case 'insert':
        return $this->push($connection, $requestOptions);
      case 'remove':
        return $this->remove($connection, $requestOptions);
      case 'update':
        return $this->refresh($connection, $requestOptions);
      default:
        return $this->fetch($connection, $requestOptions);
    }
  }

  /*
   * {alias: <sql>}
   * */
  private function parseQuery( SQLHandler $connection, $branch, $params ) {
    $values = array();
    $start = strpos($branch, '{');
    $usedKeys = array();

    while ( $start !== false ) {
      $insideBrackets = String::balanceParen($branch, $start, '{', '}');
      preg_match('/^([\w\:\!\*\?]+)(.*?)$/', $insideBrackets, $groups);
      list(, $columns, $sqlContent) = $groups;
      $columns = explode(':', rtrim($columns, ':'));
      $bindMarks = preg_match_all('/\?/', $sqlContent, $groups);

      foreach ( $columns as $column ) {
        /*
         * {!columnName: <sql>}
         * */
        if ( $column[0] == '!' ) {
          $column = substr($column, 1);
          if ( array_key_exists($column, $params) || Session::has($column) ) {
            $sqlContent = '';
          }
        }
        /*
         * {+columnName: <sql>}
         * column has to be present in params at least once
         * further occurances will be bound to the query as well
         * */
        else if ( $column[0] == '+' ) {
          $column = substr($column, 1);
          if ( array_key_exists($column, $params) ) {
            $value = $params[$column];
            $values[] = array_key_exists($column, $values) ? $values[$column] : $value;
            $usedKeys[$column] = 1;
          }
          else $sqlContent = '';
        }
        /*
         * {?columnName: <sql>}
         * column is optional for the query
         * but only the first will be bound
         * the rest (if any) will only replace the tempalte text
         * but not bound the param more than once
         * */
        else if( $column[0] == '?' ){
          $column = substr($column, 1);
          if ( array_key_exists($column, $params) ) {
            $value = $params[$column];
            if( !array_key_exists($column, $usedKeys) ) {
              $usedKeys[$column] = 1;
              $values[] = $value;
            }
          }
          else $sqlContent = '';
        }
        /*
         * {columName: <sql>}
         * column name literal match in params
         * */
        else if ( array_key_exists($column, $params) ) {
          $value = $params[$column];
          $values[] = array_key_exists($column, $values) ? $values[$column] : $value;
          $usedKeys[$column] = 1;
        }
        /*
         * {session.columnName: <sql>}
         * column refers to a session var
         * */
        else if( preg_match('/session.(.*?)$/', $column, $match) ){
          $column = $match[1];
          $values[] = array_key_exists($column, $values) ? $values[$column] : Session::get($column);
          $usedKeys[$column] = 1;
        }
        /*else if ( $value = Session::has($column) ) {
          $values[] = array_key_exists($column, $values) ? $values[$column] : Session::get($column);
          $usedKeys[$column] = 1;
        }*/
        else $sqlContent = '';
      }
      $branch = substr_replace($branch, $sqlContent, $start, strlen($insideBrackets) + 2);
      $start = strpos($branch, '{');
    }
    $connection->bindParam($values);

    return $branch;
  }
  /*
   * sql select
   * */
  private function fetch( SQLHandler $connection, $requestOptions ){
    $sql = $this->branches[$requestOptions['branch']];
    $params = $requestOptions['params'] ? $requestOptions['params'] : array();

    if( $sql == '*' )  {
      $sql = "select * from $this->table";
    }
    else{
      $sql = $this->parseQuery($connection, $sql, $params);
    }

    if ( !$sql ) {
      $this->error = 'Nullified query, probably argument name missmatch';
      return null;
    }

    $page = null;
    $show = $this->show;

    if ( $params ) {
      $params = (array)$params;
      @$show = $params['show'];
      @$page = $params['page'];
    }
    if ( $page ) {
      $sql .= ' limit ?, ?';
      $connection->bindParam($show * ($page - 1));
      $connection->bindParam($show);
    }
    else if ( $show ) {
      $sql .= ' limit ?';
      $connection->bindParam($show);
    }
    $dataSet = $connection->query($sql);
    if( $this->error = $connection->error ){
      return null;
    }

    $ret = array();

    $group = $requestOptions['group'];
    if ( $group ) {
      foreach ( $dataSet as $row ) {
        if ( @$row[$group] ) {
          @$ret[$row[$group]]
            ? $ret[$row[$group]] []= $row
            : $ret[$row[$group]] = array( $row );
        }
      }
    }
    else {
      foreach ( $dataSet as $row ) {
        $ret[] = $row;
      }
    }

    return $ret;
  }

  /*
   * filter data according to field map rules
   * */
  protected function validate( $data ){
    if( !$data || empty($data) ) return false;
//    $data = array_intersect_key($data, $this->fieldMap);
//    $postData = array();

    foreach( $data as $name => $value ){
      $def = $this->fieldMap[$name];
      if( $def && $def['pattern'] && !preg_match($def['pattern'], $value) ) {
        $this->onerror($def['error']);
        return false;
      }
    }
    return true;
  }

  protected function resolveColumnNames( $data ){
    if( empty($this->fieldMap) ) return $data;

    $data = array_intersect_key($data, $this->fieldMap);
    $postData = array();
    foreach( $data as $name => $value ){
      $def = $this->fieldMap[$name];
      $name = $def['column'] || $name;
      $postData[$name] = $value;
    }
    return $postData;
  }

  protected function resolveAlias( $alias ){
    return $this->fieldMap && $this->fieldMap[$alias] ? $this->fieldMap[$alias]['column'] : null;
  }

  /*
   * sql insert
   * {
   *   params: {},
   *
   * }
   * */
  protected function push( SQLHandler $connection, $requestOptions ){
    $data = $requestOptions['params'] ? $requestOptions['params'] : $requestOptions;
    if( !$this->validate($data) ) return null;
    $data = $this->resolveColumnNames($data);

    $columns = implode(',', array_keys($data));
    $connection->bindParam($data);
    $values = trim(str_repeat(',?', count($data)), ',');
    $sql = "insert into $this->table ($columns) values ($values)";
    $result = $connection->query($sql);
    if( $connection->error ) {
      $this->onerror($connection->error);
      return null;
    }
    return $result;
  }

  protected function refresh( SQLHandler $connection, $requestOptions ){
    $data = $requestOptions['params'] ? $requestOptions['params'] : $requestOptions;
    if( !$this->validate($data) ) return null;
    $data = $this->resolveColumnNames($data);

    $where = '';
    if( isset($data[$this->idColumn]) ) {
      $where = $data[$this->idColumn];
      unset($data[$this->idColumn]);
    }

    $set = '';
    foreach( $data as $name => $value ){
      $set .= $name .'=?,';
    }
    $set = trim($set, ',');

    $connection->bindParam($data);
    if( $where ) {
      $connection->bindParam($where);
      $sql = "update $this->table set $set where $this->idColumn = ?";
    }
    else $sql = "update $this->table set $set";
    $result = $connection->query($sql);
    if( $connection->error ) {
      $this->onerror($connection->error);
      return null;
    }
//    return 1;
    return $result;
  }


  protected function remove( SQLHandler $connection, $requestOptions ){
    $branch = $requestOptions['branch'];
    $params = $requestOptions['params'] ? $requestOptions['params'] : array();
  }

  protected function onerror( $e ){}

/*

  public function extract( $dataSet ) {
    $rawData = array();
    foreach ( $dataSet as $row ) {
      $rawData[] = $row->extractData();
    }
    return $rawData;
  }

  public function save( $dataSet, SQLHandler $connection ) {
    $table = new SQLTable($connection, $this->table, $this->idColumn);
    foreach ( $dataSet as $row ) {
      if ( $row->isDeleted ) {
        $table->deleteMultiple($row->id);
      }
    }
    $table->save();

    foreach ( $dataSet as $row ) {
      if ( !$row->isDeleted && $row->isChanged() ) {
        $table->updateMultiple($row->changedColumns(), $row->id);
      }
    }
    $table->save();
  }*/
}
