<?php
namespace canopy\parse;

class Scope {
  private $stack = array();
  private $global;
  private $local;
  private $length = 1;

  const undefined = null;

  public function __construct( $globalscope = array() ) {
    $this->stack[] = &$globalscope;
    $this->global = &$this->stack[0];
    $this->local = &$this->stack[0];
  }

  public function &search( $identifier, &$found=false ) {
    $l = $this->length;
    while ( $l-- ) {
      if ( array_key_exists($identifier, $this->stack[$l]) ) {
        $found = true;
        return $this->stack[$l][$identifier];
      }
    }
    $n = null;
    return $n;
  }

  public function registerGlobals( $globals ) {
    $this->global = array_merge($this->global, (array)$globals);
  }

  public function registerLocals( $locals ) {
    $this->local = array_merge($this->local, (array)$locals);
  }

  public function &get( $identifier ) {
    if ( array_key_exists($identifier, $this->local) ) return $this->local[$identifier];
    $n = null;
    return $n;
  }

  public function set( $identifier, &$value ) {
    $this->local[$identifier] = $value;
  }

  public function has( $identifier ) {
    return array_key_exists($identifier, $this->local);
  }
  public function hasGlobal( $identifier ) {
    return array_key_exists($identifier, $this->global);
  }

  public function getGlobal( $identifier ) {
    if ( array_key_exists($identifier, $this->global) ) return $this->global[$identifier];
    $n = null;
    return $n;
  }

  public function setGlobal( $identifier, &$value ) {
    $this->global[$identifier] = $value;
  }

  public function open() {
    $this->stack[] = array();
    $this->local = &$this->stack[$this->length++];
  }

  public function close() {
    if ( $this->length > 1 ) {
      --$this->length;
      array_pop($this->stack);
      $this->local = &$this->stack[$this->length - 1];
      return true;
    }
    return false;
  }
}
