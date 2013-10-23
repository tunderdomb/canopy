<?php
namespace canopy;

use canopy\database\SQLHandler;

class Connection {
  private $connection;
  /*
   * array(
   *  "classname" => array($server, $user, $password, $database)
   * )
   * */
  private $userClasses;
  private $user;

  public function __construct( $defaultVisitor, $userClasses=array() ) {
    $this->userClasses = $userClasses;
    $this->userClasses['default'] = $defaultVisitor;
  }

  private function getClass( $class ){
    return $class && @$this->userClasses[$class]
      ? $this->userClasses[$class]
      : $this->userClasses['default'];
  }

  final public function setUser( $class ){
    $this->user = $this->user ? $this->user : $class;
  }

  public function createConnection( $className='' ){
    $visitorClass = $this->getClass($className);
    $reflection = new \ReflectionClass('canopy\database\SQLHandler');
    try {
      return $this->connection = $reflection->newInstanceArgs($visitorClass);
    }
    catch ( \Exception $e ) {
      return null;
    }
  }

  public function connect(  ){
    return $this->connection ? $this->connection : $this->createConnection($this->user);
  }
}
