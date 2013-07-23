<?php
namespace canopy\request;

final class Cookie {

  static public function set( $name, $value="", $expire=0, $path="/", $domain="", $secure=false, $httponly=false ){
    setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
  }
  static public function has( $name ){
    return (boolean) isset($_COOKIE[$name]);
  }
  static public function get( $name ){
    return self::has($name) ? $_COOKIE[$name] : null;
  }
  static public function add( $name, $value ){
    if ( !self::has($name) ) self::set($name, $value);
  }
  static public function remove( $name ){
    setcookie($name, "", 1);
  }
}
