<?php
namespace canopy\request;

class History {
  public $history;
  public $limit;
  private $sessionVar;

  function __construct( $limit=3 ) {
    $this->limit = $limit;
  }

  public function startRecording( $sessionVar ){
    $this->sessionVar = $sessionVar;
    if ( Session::isActive() ){
      if( Session::has($sessionVar) ){
        $this->history = Session::get($sessionVar);
      }
      else Session::set($sessionVar, $this->history = array());
    }
  }

  public function push( $url ){
    if ( !Session::isActive() ) return;
    $l = count($this->history);
    if ( $l == $this->limit ){
      array_shift($this->history);
    }
    if ( !$l || $this->history[$l-1] != $url )
      array_push($this->history, $url);
    Session::set($this->sessionVar, $this->history);
  }

  public function pop( $url ){
    if ( $url > 0 ) {
      do {
        $route = array_pop($this->history);
      } while ( --$url && count($this->history) );

      Session::set($this->sessionVar, $this->history);
      return $route;
    }
  }
}
