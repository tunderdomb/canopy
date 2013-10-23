<?php

namespace canopy\filesystem;

final class Path {

  final public static function isAbsolute( $path ) {
    return (boolean)preg_match('/^(\\\|\/|\w+\:)/', $path);
  }

  final public static function absolute( $path, $root=null ){
    if ( !self::isAbsolute($path) ){
      return self::normalize($root ? $root : $_SERVER['DOCUMENT_ROOT']).DIRECTORY_SEPARATOR.$path;
    }
    else {
      return $path;
    }
  }

  final public static function normalize( $path, $root=null ) {
    return self::absolute(self::strip($path), $root);
  }

  final public static function realpath( $path, $root=null ) {
    if ( $path = realpath(self::normalize($path, $root)) ) {
      return is_dir($path) ? self::strip($path) . DIRECTORY_SEPARATOR : $path;
    }
  }

  final public static function strip( $path ) {
    return strtr(rtrim(rtrim($path, '\\'), '/'), array(
      '/' => DIRECTORY_SEPARATOR,
      '\\' => DIRECTORY_SEPARATOR
    ));
  }

  final public static function concat() {
    $i = 0;
    $l = func_num_args();
    $args = func_get_args();
    $path = $args[$i];

    while ( ++$i < $l ) {
      $path .= DIRECTORY_SEPARATOR . self::strip($args[$i]);
    }

    return $path;
  }
}
