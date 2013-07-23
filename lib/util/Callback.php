<?php
namespace canopy\util;

class Callback {

  private $object;
  private $reflection;
  private $function;
  private $boundArguments;

  public function __construct( $object, $method=0 ){

    if ( $method && is_object($object) ){
      $this->object = $object;
      $this->reflection = new \ReflectionMethod($object, $method);
    }
    else if ( is_array($object) ){
      list($class, $method) = $object;
      $this->reflection = new \ReflectionMethod($class, $method);
    }
    else if( is_string($object) && strpos($object, "::") !== false ){
      list($class, $method) = explode("::", $object);
      $this->reflection = new \ReflectionMethod($class, $method);
    }
    else if( is_callable($object) ){
      $this->function = new \ReflectionFunction($object);
    }
  }

  final public function bind(){
    if ( !$this->boundArguments ){
      $this->boundArguments = func_get_args();
    }
    else $this->boundArguments = array_merge($this->boundArguments, func_get_args());
  }

  final public function call(){
    $arguments = func_get_args();
    if ( $this->boundArguments ){
      array_splice($arguments, 0,0, $this->boundArguments);
    }
    if ( $this->function ) return $this->function->invokeArgs($arguments);
    return $this->reflection->invokeArgs($this->object, $arguments);
  }

  final public function apply( $arguments ){
    if ( $this->boundArguments ){
      array_splice($arguments, 0,0, $this->boundArguments);
    }
    if ( $this->function ) return $this->function->invokeArgs($arguments);
    return $this->reflection->invokeArgs($this->object, $arguments);
  }
}
