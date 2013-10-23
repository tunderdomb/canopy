<?php
namespace canopy\database;

class SQLResults implements \ArrayAccess, \Iterator, \Countable{


  private $position = 0;
  private $row;

  private $query;

  private $paginateSql;
  private $handler;
  private $params;

  public $length;
  public $affected;
  public $lastId;
  public $fields;
  public $error;

  public $all;
  public $show;
  public $pages;
  public $currentPage;
  public $hasNextPage;
  public $hasPreviousPage;
  /*
   * (
      [affected_rows] => 1
      [insert_id] => 6
      [num_rows] => 0
      [param_count] => 1
      [field_count] => 0
      [errno] => 0
      [error] =>
      [error_list] => Array
          (
          )

      [sqlstate] => 00000
      [id] => 1
  )

  mysqli_stmt Object
  (
      [affected_rows] => -1
      [insert_id] => 0
      [num_rows] => 0
      [param_count] => 1
      [field_count] => 2
      [errno] => 0
      [error] =>
      [error_list] => Array
          (
          )

      [sqlstate] => 00000
      [id] => 1
  )
   */
  public function __construct( \mysqli_stmt &$query, SQLHandler $handler, $sql, $params ) {
    $this->query = &$query;
    $query->store_result();
    $this->length = $query->num_rows;
    $this->affected = $query->affected_rows;
    $this->lastId = $query->insert_id;
    $this->fields = $query->field_count;
    $this->error = $query->error;

    if ( $fields = $query->result_metadata() ){
      $columns = array();
      foreach( $fields->fetch_fields() as $field ){
        $columns[] = &$this->row[$field->name];
      }
      $fields->free();

      call_user_func_array(array($query, 'bind_result'), $columns);
    }

    if( preg_match('/^\s*select\s+(?!count\(\*\)).*?(?:from|in)(.*?)(?:limit\s*\?\,\s*\?)\s*$/msi', $sql, $match) ){
      $this->paginateSql = $match[1];
      $this->handler = $handler;
      $this->params = $params;
    }
//    $query->close();
  }

  public function __destruct(){
    $this->query->close();
  }

  public function paginate(){
    if( $query = $this->paginateSql ){
      $query = "select count(*) from $query";

      $show = array_pop($this->params);
      $skip = array_pop($this->params);
      $this->handler->bindParam($this->params);

      $length = $this->handler->query($query);
      $length = $length[0];
      $length = array_pop($length);

      $this->all = $length;
      $this->show = $show;
      $this->pages = ceil($length/$show);
      $this->currentPage = $skip/$show + 1;
      $this->hasNextPage = $this->currentPage < $this->pages;
      $this->hasPreviousPage = $this->currentPage > 1;

      //$this->sql = NULL;
      $this->params = NULL;
      $this->handler = NULL;
    }
  }

  private function copy( $row ){
    $copy=array();
    foreach( $row as $field => $data ){
      $copy[$field] = $data;
    }
    return $copy;
  }

  public function extract(){
    $query = array();
    foreach( $this as $row ){
      $query[]= $row;
    }
    return $query;
  }

  // ArrayAccess methods
  public function offsetSet( $offset, $value ){
  }

  public function offsetExists( $offset ){
    return $offset < $this->length;
  }

  public function offsetUnset( $offset ){
  }

  public function offsetGet( $offset ){
    $this->query->data_seek($offset);
    $this->query->fetch();
    return $this->copy($this->row);
  }

  //Iterator methods
  public function rewind() {
    $this->query->data_seek($this->position = 0);
  }

  public function current() {
    return $this->copy($this->row);
  }

  public function key() {
    return $this->position;
  }

  public function next() {
    ++$this->position;
  }

  public function valid() {
    return (boolean) $this->query->fetch();
  }

//  implement Countable
  public function count(){
    return $this->length;
  }
}
