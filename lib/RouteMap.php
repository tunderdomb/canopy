<?php
namespace canopy;

class RouteMap {
  private $requestHash;
  private $requestLength;

  public $route;
  public $var;

  public function __construct() {
    $this->requestHash = explode('/', trim($_SERVER['REDIRECT_URL'], '/'));
    $this->requestLength = count($this->requestHash);
    $this->route = '/';
  }

  public function findAWay( $routes ){
    if ( $this->requestLength ){
      foreach ( $routes as $route => $options ) {
        if ( $this->compareDirections($route) ) {
          // first come, first served
          $this->route = $route;
          break;
        }
      }
    }
    else {
      $options = $routes->{'/'};
      $this->route = '/';
    }

    if ( !$this->route ) {
      $options = $routes->{'*'};
      $this->route = '*';
    }

    if ( !$options ) return null;

    $valid = false;
    foreach( $options as $option => $value ){
      if( !property_exists($this, $option) ){
        $this->$option = $value;
        $valid = true;
      }
    }
    if ( !$valid ) return null;
  }

  private function compareDirections( $route ) {
    if ( $route == '/' ) return false;
    $url = $this->requestHash;
    $ulength = $this->requestLength;
    $route = explode('/', trim($route, '/'));
    $rlength = count($route);
    $i = -1;
    $valid = true;
    $labels = array();

    /**
     * validate absolute part of route
     * runs until first :var part
     * returns on a mismatch
     */
    while ( ++$i < $ulength && $route[$i] && $route[$i][0] != ':' ) {
      $valid &= $route[$i] == $url[$i];
      if ( !$valid ) return false;
    }

    $l = $k = --$i;

    // collect labels for route into a hash
    while ( ++$l < $rlength ) {
      if ( $route[$l][0] != ':' ) {
        $labels[$route[$l]] = $l;
      }
    }

    // parse labels
    while ( ++$i < $ulength && ++$k < $rlength ) {

      // if urlchunk is a label
      // seek to that part of the route and continue from there
      if ( $l = $labels[$url[$i]] ) {
        unset($labels[$url[$i]]);
        $k = $l;
      }
      else if ( $route[$k][0] == ':' ) {
        $name = substr($route[$k], 1);
        $this->var[$name] = $url[$i];
      }
      /*
        echo
          'wrong number of arguments: ' . $url[$i] .
          ' should be ' . $route[$k] . '\n';
       */
      else return false;
    }

    // we found a pattern that wholly matches the requested url
    return true;
  }
}
