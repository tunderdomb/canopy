<?php
namespace canopy\database;

class SQLHandler implements DatabaseHandler {
  private $server;
  private $user;
  private $pass;
  private $database;

  public $handler;

  private $bindParamCallback;
  private $params = array();
  private $paramTypes = '';

  public $error;

  function __construct( $server, $user, $pass, $database = null ) {
    $this->server = $server;
    $this->user = $user;
    $this->pass = $pass;
    $this->database = $database;
    $this->bindParamCallback = new \ReflectionMethod('mysqli_stmt', 'bind_param');
  }

  final public function __destruct() {
    if ( $this->handler ) $this->handler->close();
  }

  public function connect() {
    if ( !$this->handler ) $this->handler = new \MySQLi(
      $this->server,
      $this->user,
      $this->pass,
      $this->database);
    if ( !$this->handler ) throw new DatabaseException('Couldn\'t establish connection');
    if ( $this->handler->error ) throw new DatabaseException($this->handler->error);
    $this->handler->set_charset('utf8');
    return $this;
  }

  public function selectDB( $database ) {
    !$this->handler && $this->connect();
    $this->database = $database;
    $this->handler->select_db($database);
  }

  public function bindParam( $param, $type = null ) {
    !$this->handler && $this->connect();
    if ( is_array($param) ) {
      foreach ( $param as $p ) {
        $this->paramTypes .= $this->paramType($p);
        $this->params [] = $p;
      }
    }
    else {
      $this->paramTypes .= $type ? $type : $this->paramType($param);
      $this->params [] = $param;
    }
  }

  private function paramType( $param ) {
    if ( is_int($param) ) return 'i';
    if ( is_float($param) || is_double($param) ) return 'd';
    if ( is_string($param) ) {
      preg_match('/(^\d+$)|(^\d+[,\.]\d+$)/', $param, $number);
      if ( $number ) {
        if ( $number[1] && ($number[1] < 2147483647) ) {
          return 'i';
        }
        else if ( $number[2] ) return 'd';
      }

      return 's';
    }
    else throw new DatabaseException('Wrong param type');
  }

  private function prepare( $query ) {
    $reference = array();
    foreach ( $this->params as $key => $val ) {
      $reference[$key] = & $this->params[$key];
    }
    array_unshift($reference, $this->paramTypes);
    $this->bindParamCallback->invokeArgs($query, $reference);
    $this->paramTypes = '';
    $this->params = array();
  }

  public function query( $sql ) {
    !$this->handler && $this->connect();
    $sql = (string)$sql;
    if ( !$sql ) {
      $this->error = 'Empty query';
      return null;
    }
    else if ( !empty($this->params) ) {
      $query = $this->handler->prepare($sql);
      if ( $query ){
        $params = $this->params;
        if ( $this->paramTypes ) $this->prepare($query);
        $query->execute();

        if ( !$query->error && !$this->handler->error ) {
          return new SQLResults($query, $this, $sql, $params);
        }
        $this->error = $query->error ? $query->error : $this->handler->error;
      }
      if ( !$this->handler->error ){
        if ( count($this->params) != substr_count($sql, '?') ){
          $this->error = 'Argument count missmatch';
        }
        else $this->error = 'Unknown error';
      }
      $this->error = $this->handler->error;
    }
    else {
      $query = $this->handler->query($sql);
      if ( $query || !$this->handler->error ) return $query;
      $this->error = $this->handler->error;
    }
  }
}