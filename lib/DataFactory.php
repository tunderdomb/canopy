<?php
namespace canopy;

use canopy\database\SQLTable;
use canopy\database\SQLHandler;

class DataFactory {
  protected $root;
  protected $table;
  protected $show;
  
  protected $branches = array(
//    "login" => 'select 1 from blah where blah = ?'
  );
  private $lastData;
  public $error;

  function __construct( $table, $idColumn=null, $columnWhiteList=null ) {
    $this->table = $table;
    if( $idColumn ){
      $this->root = new SQLTable($connection, $table, $idColumn, $columnWhiteList);
    }
  }

  public function fetch( SQLHandler $connection, $branch = null, $params = array(), $plain = true, $group = null ) {
    unset($this->error);
    if ( $branch ) {
      if ( array_key_exists($branch, $this->branches) ) {
        $sql = $this->branches[$branch];
      }
      else {
        // error
      }
    }
    else {
      $sql = "select * from $this->table";
    }

    $page = null;
    $show = $this->show;

    if ( $params ) {
      $params = (array)$params;
      @$show = $params['show'];
      @$page = $params['page'];
    }

    $sql = $this->parseQuery($connection, $sql, $params);
    if ( !$sql ) {
      $this->error = 'Nullified query, probably argument name missmatch';

      return;
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
    if ( $plain ) {
      if ( $group ) {
        foreach ( $dataSet as $row ) {
          if ( @$row[$group] ) {
            @$dataSet[$row[$group]]
              ? $dataSet[$row[$group]] [] = $row
              : $dataSet[$row[$group]] = array( $row );
          }
        }
      }
      else {
        foreach ( $dataSet as $row ) {
          $ret[] = $row;
        }
      }
    }
    else {
      if ( $show && $page ) $dataSet->paginate();
      foreach ( $dataSet as $row ) {
        $ret[] = new DataRow($row, $this, $this->idColumn());
      }
    }

    $this->lastData = $ret;

    return $ret;
  }


  private function parseQuery( SQLHandler $connection, $branch, $params ) {
    $values = array();
    $start = strpos($branch, '{');
    $usedKeys = array();

    while ( $start !== false ) {
      $insideBrackets = String::balanceParen($branch, $start, '{', '}');
      preg_match('/^([\w\:\!\*\?]+)(.*?)$/', $insideBrackets, $groups);
      list(, $columns, $sqlContent) = $groups;
      $columns = explode(':', rtrim($columns, ':'));

      foreach ( $columns as $column ) {
        if ( $column[0] == '!' ) {
          $column = substr($column, 1);
          if ( array_key_exists($column, $params) || Session::has($column) ) {
            $sqlContent = '';
          }
        }
        /*
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
         * column name literal match in params
         * */
        else if ( array_key_exists($column, $params) ) {
          $value = $params[$column];
          $values[] = array_key_exists($column, $values) ? $values[$column] : $value;
          $usedKeys[$column] = 1;
        }
        /*
         * column refers to a session var
         * */
        else if ( $value = Session::has($column) ) {
          $values[] = array_key_exists($column, $values) ? $values[$column] : Session::get($column);
          $usedKeys[$column] = 1;
        }
        else return '';
      }
      $branch = substr_replace($branch, $sqlContent, $start, strlen($insideBrackets) + 2);
      $start = strpos($branch, '{');
    }
    $connection->bindParam($values);

    return $branch;
  }


  public function extract() {
    $rawData = array();
    foreach ( $this->lastData as $row ) {
      $rawData[] = $row->extractLeaves();
    }
    unset($this->lastData);
    return $rawData;
  }

  public function save() {
    foreach ( $this->lastData as $row ) {
      if ( $row->isDeleted ) {
        $this->root->deleteMultiple($row->id);
      }
    }
    $this->root->save();

    foreach ( $this->lastData as $row ) {
      if ( !$row->isDeleted && $row->isChanged() ) {
        $this->root->updateMultiple($row->changedColumns(), $row->id);
      }
    }
    $this->root->save();
  }
}
