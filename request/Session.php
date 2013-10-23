<?php
namespace canopy\request;

final class Session {
  private static $cookieName;
  private static $cookieLifeTime = 0;
  private static $cookiePath = '/';
  private static $cookieDomain;
  private static $cookieSecure = false;
  private static $cookieHTTPonly = true;

  public static $oneTimeKey = '';

  public static function id( $id=null ){
    if ( $id != null ) return session_id($id);
    return session_id();
  }
  public static function regenerate( $deleteOld=false ){
    $newId = session_regenerate_id($deleteOld);
    if ( self::$cookieName ) self::sendCookie();
    return $newId;
  }
  public static function isActive(){
    return (boolean)session_id();
  }

  public static function savePath( $path=null ){
    if ( $path != null ) return session_save_path($path);
    return session_save_path();
  }
  public static function cookie( $name=null, $lifetime=0, $path='/', $domain, $secure=true, $httponly=true ){
    if ( $name ){
      ini_set('session.use_cookies', 0);
      self::$cookieName = $name;
      self::$cookieLifeTime = $lifetime;
      self::$cookiePath = $path;
      self::$cookieDomain = $domain;
      self::$cookieSecure = $secure;
      self::$cookieHTTPonly = $httponly;
    }
    else session_set_cookie_params($lifetime, $path, $domain, $secure, $httponly);
  }
  public static function setCookieName( $name ){
    self::$cookieName = $name;
  }
  public static function setCookieExpire( $lifetime ){
    self::$cookieLifeTime = $lifetime;
  }
  public static function setCookiePath( $path ){
    self::$cookiePath = $path;
  }
  public static function setCookieDomain( $domain ){
    self::$cookieDomain = $domain;
  }
  public static function setCookieSecure( $secure ){
    self::$cookieSecure = $secure;
  }
  public static function setCookieHTTPonly( $httponly ){
    self::$cookieHTTPonly = $httponly;
  }
  
  private static function sendCookie(){
    Cookie::set(
      self::$cookieName
      , session_id()
      , self::$cookieLifeTime
      , self::$cookiePath
      , self::$cookieDomain
      , self::$cookieSecure
      , self::$cookieHTTPonly
    );
  }

  public static function removeCookie(){
    if ( !self::isActive() ) return;
    Cookie::remove(self::$cookieName);
    self::$cookieLifeTime = 0;
  }

  public static function clearCookieSettings(){
    self::$cookieName =
    self::$cookieLifeTime =
    self::$cookiePath =
    self::$cookieDomain =
    self::$cookieSecure =
    self::$cookieHTTPonly = null;
  }

  public static function start(){
    if ( !self::isActive() ) {
      if ( self::$cookieName ){
        if ( $previousSession = Cookie::get(self::$cookieName) ){
          self::resume($previousSession);
        }
        else {
          session_start();
          self::sendCookie();
        }
      }
      else session_start();
      if( self::$oneTimeKey ) self::clearOneTimeKeys();
    }
  }
  public static function stop(){
    session_write_close();
  }
  final public static function destroy(){
    if ( !self::isActive() ) return;
    session_destroy();
  }
  public static function reset(){
    if ( !self::isActive() ) return;
    session_destroy();
    session_regenerate_id(true);
    $_SESSION = array();
  }
  public static function clear(){
    if ( !self::isActive() ) return;
    $_SESSION = array();
  }
  public static function resume( $previousID ){
    if ( self::isActive() ){
      $_SESSION = array();
      session_destroy();
    }
    session_id($previousID);
    session_start();
  }

  final public static function is( $prop, $value ){
    return array_key_exists($prop, $_SESSION)
      && $_SESSION[$prop] === $value;
  }

  final public static function get( $prop ){
    return array_key_exists($prop, $_SESSION)
      ? $_SESSION[$prop]
      : null;
  }

  final public static function set( $prop, $value ){
    if ( is_array($prop) ){
      foreach( $prop as $p => $value ) $_SESSION[$p] = $value;
    } else $_SESSION[$prop] = $value;
  }

  final public static function oneTime( $prop, $value ){
    self::set($prop, $value);
    $oneTimeKeys = self::get(self::$oneTimeKey);
    $oneTimeKeys = $oneTimeKeys ? $oneTimeKeys : array();
    $oneTimeKeys[$prop] = 1;
    self::set(self::$oneTimeKey, $oneTimeKeys);
  }
  final public static function clearOneTimeKeys(){
    if( !self::$oneTimeKey ) return;
      $oneTimeKeys = self::get(self::$oneTimeKey);
    if( $oneTimeKeys ) foreach( $oneTimeKeys as $key => $v ){
      if( $v == 2 ) {
        unset($oneTimeKeys[$key]);
        self::remove($key);
      }
      else ++$oneTimeKeys[$key];
    }
    if( empty($oneTimeKeys) ) self::remove(self::$oneTimeKey);
    else self::set(self::$oneTimeKey, $oneTimeKeys);
  }

  final public static function has( $prop ){
    return array_key_exists($prop, $_SESSION);
  }

  final public static function add( $prop, $value ){
    if ( !array_key_exists($prop, $_SESSION) ) $_SESSION[$prop] = $value;
  }
  public static function remove( $prop ){
    unset($_SESSION[$prop]);
  }
}
