<?php
namespace canopy;

use canopy\database\SQLHandler;

class Gateway {
  private $connection;
  /*
   * array(
   *  "classname" => array($server, $user, $password, $database)
   * )
   * */
  private $userClasses;

  public function __construct( $defaultVisitor, $userClasses=array() ) {
    $this->userClasses = $userClasses;
    $this->userClasses['default'] = $defaultVisitor;
  }

  private function getClass( $class ){
    return $class && @$this->userClasses[$class]
      ? $this->userClasses[$class]
      : $this->userClasses['default'];
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

  public function connect(){
    return $this->connection ? $this->connection : $this->createConnection();
  }
}
