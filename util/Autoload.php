<?php
namespace canopy\util;

class Autoload {
  function __construct( $includepath ) {
    set_include_path( $includepath );
    spl_autoload_extensions(".php");
    $this->register($includepath);
  }
  public function register( $namespace, $throw=true, $prepend=false ){
    spl_autoload_register($this->createLoader($namespace), $throw, $prepend);
  }
  
  private function createLoader( $namespace ){
    $namespace = $namespace.DIRECTORY_SEPARATOR;
    return function($class) use ($namespace){
      $class = $namespace.strtr($class, '\\', DIRECTORY_SEPARATOR).'.php';
      @include $class;
  };
  }
}
