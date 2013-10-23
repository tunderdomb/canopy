<?php
namespace canopy;

use canopy\util\Console;

class RouteMap {
  private $requestHash;
  private $requestLength = 0;

  public $route = '/';
  public $var = array();
  public $responseType;

  public function __construct() {
    $url = trim($_SERVER['REDIRECT_URL'], '/');
    if( !$url ) return;
    $this->requestHash = explode('/', $url);
    $this->requestLength = count($this->requestHash);
  }

  public function findAWay( $routes ){
    $options = null;

    if ( $this->requestLength ){
      foreach ( $routes as $route => $options ) {
//        $this->var = $options['params'] ? $options['params'] : array();
        if ( $this->compareDirections($route) ) {
          // first come, first served
          $this->route = $route;
          break;
        }
      }
    }
    else {
      $options = $routes['/'];
      $this->route = '/';
    }

    if ( !$this->route ) {
      $options = $routes['*'];
      $this->route = '*';
    }

    if ( !$options ) return null;

    $urlParams = $options['params'] ? $options['params'] : array();
    $this->var = array_merge($urlParams, $this->var);

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
    $requestLength = $this->requestLength;
    $route = explode('/', trim($route, '/'));
    $urlPartCount = count($route);
    $i = -1;
    $valid = true;
    $labels = array();

    /**
     * validate absolute part of route
     * runs until first :var part
     * returns on a mismatch
     */
    while ( ++$i < $requestLength && $route[$i] && $route[$i][0] != ':' ) {
      $valid = $valid && $route[$i] == $url[$i];
      if ( !$valid ) return false;
    }

    $l = $k = --$i;

    // collect labels for route into a hash
    while ( ++$l < $urlPartCount ) {
      if ( $route[$l][0] != ':' ) {
        $labels[$route[$l]] = $l;
      }
    }

    // parse labels
    while ( ++$i < $requestLength && ++$k < $urlPartCount ) {

      // if url chunk is a label
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
