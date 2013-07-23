<?php
namespace canopy\util;

final class Console {
  private static $enabled;
  private static $embed;
  private static $stack=array();

  final static public function embedTtoHTML( $htmlResponse, $extra=null ){
    if ( !self::$enabled || !self::$embed ) return $htmlResponse;
    if ( preg_match('/<head[^>]*?>/', $htmlResponse, $match, PREG_OFFSET_CAPTURE) ){
      array_unshift(self::$stack, array('SERVER:', $_SERVER));
      array_unshift(self::$stack, array('REQUEST:', $_REQUEST));
      array_unshift(self::$stack, array('SESSION:', $_SESSION));
      array_unshift(self::$stack, array('COOKIE:', $_COOKIE));
      $extra && array_unshift(self::$stack, array('Forest:', $extra));
      $embed = $match[0][0].'<script type="text/javascript" id="canopy-console">';
      foreach( self::$stack as $line ){
        $embed .= 'console.log.apply(console,'. JSON::stringify($line) .');';
      }
      $embed .= 'document.getElementById("canopy-console").parentNode.removeChild(document.getElementById("canopy-console"));';
      $embed .= '</script>';

      $htmlResponse = substr_replace($htmlResponse, $embed, $match[0][1], 0);
    }
    return $htmlResponse;
  }

  final static public function setEmbed( $switch ){
    if ( self::$enabled ) self::$embed = $switch;
  }

  final public static function enable(){
    self::$enabled = true;
  }
  final public static function disable(){
    self::$enabled = false;
  }

  final public static function log(){
    if ( !self::$enabled ) return;
    self::$stack []= func_get_args();
  }

  final public static function write(){
    if ( !self::$enabled ) return;
    $line = array();
    foreach ( func_get_args() as  $arg ) $line []= $arg;
    print_r('\n'.JSON::stringify($line, JSON_PRETTY_PRINT));
  }

  final public static function clear(){
    self::$stack = array();
  }

  final public static function flush( $json=true ){
    if ( !self::$enabled ) return;
    $log = $json
      ? JSON::stringify(self::$stack)
      : self::$stack;
    self::clear();
    return $log;
  }

  private static $savePath;
  private static $logFileName;
  final public static function savePath( $path ){
    if ( !self::$enabled ) return;
    self::$savePath = $path;
  }
  final public static function save( $asJson=true  ){
    if ( !self::$enabled || !self::$savePath ) return;
    $logfile = self::flush($asJson);
    if ( @!file_put_contents(self::$savePath, $logfile) ){
      self::log('Can\'t save log file');
    }
  }

}
