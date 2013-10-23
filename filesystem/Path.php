<?php

namespace canopy\filesystem;

final class Path {

  final public static function isAbsolute( $path ) {
    return (boolean)preg_match('/^(\\\|\/|\w+\:)/', $path);
  }

  final public static function absolute( $path, $root=null, $separator = DIRECTORY_SEPARATOR ){
    if ( !self::isAbsolute($path) ){
      return self::normalize($root ? $root : $_SERVER['DOCUMENT_ROOT'], $separator).$separator.$path;
    }
    else {
      return $path;
    }
  }

  final public static function normalize( $path, $root=null, $separator = DIRECTORY_SEPARATOR ) {
    return self::absolute(self::strip($path), $root, $separator);
  }

  final public static function realpath( $path, $root=null, $separator = DIRECTORY_SEPARATOR ) {
    if ( $path = realpath(self::normalize($path, $root)) ) {
      return is_dir($path) ? self::strip($path, $separator) . $separator : $path;
    }
  }

  final public static function strip( $path, $separator = DIRECTORY_SEPARATOR ) {
    return strtr(rtrim(rtrim($path, '\\'), '/'), array(
      '/' => $separator,
      '\\' => $separator
    ));
  }

  final public static function concat( $separator = DIRECTORY_SEPARATOR ) {
    $i = 0;
    $l = func_num_args();
    $args = func_get_args();
    if( $separator == '/' || $separator == '\\' ) {
      ++$i;
    }
    $path = self::strip($args[$i]);

    while ( ++$i < $l ) {
      $path .= '/' . self::strip($args[$i]);
    }

    return $path;
  }
}
