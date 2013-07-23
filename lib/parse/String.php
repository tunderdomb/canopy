<?php
namespace canopy\parse;


final class String{

  public static function normalizeMatchSet( $match ) {
    $offsets = array();
    if ( is_array($match[0]) ) {
      foreach ( $match as $key => $value ) {
        $offsets[$key] = $value[1];
        $match[$key] = $value[1] == -1 ? null : $value[0];
      }
    }
    $match["length"] = count($match);
    $match["index"] = $offsets[0];
    $match["offsets"] = $offsets;

    return $match;
  }

  public static function match( $regexp, $string, $startOffset = 0 ) {
    if ( preg_match($regexp, $string, $match, PREG_OFFSET_CAPTURE, $startOffset) ) {
      return self::normalizeMatchSet($match);
    }

    return null;
  }

  public static function parseParens( &$string, $callback = null, $opening = '(', $closing = ')', $arguments=array() ) {
    $i = -1;
    $l = strlen($string);

    if ( !$callback ){
      while ( ++$i < $l ) {
        $c = $string[$i];
        if ( $c == $opening ) {
          $openings[] = $i;
        }
        else if ( $c == $closing ) {
          if ( empty($opening) ) return false;
          array_pop($openings);
        }
      }
      return empty($opening);
    }

    $openings = array();
    array_unshift($arguments, '');

    while ( ++$i < $l ) {
      $c = $string[$i];
      if ( $c == $opening ) {
        $openings[] = $i;
      }
      else if ( $c == $closing ) {
        if ( empty($opening) ) return false;

        $start = array_pop($openings);
        $length = $i - $start + 1;

        // crop the insides of the parenthesis
        $arguments[0] = substr($string, $start + 1, $length - 2);
        // callback(inside, arguments)
        $replace = call_user_func_array($callback, $arguments);

        // replace the parens in the string with the parsed string
        $string = substr_replace($string, $replace, $start, $length);

        // set the parser's start index back to the first parens position
        $i = $start - 1;

        // recalculate string length
        $l = strlen($string);
      }
    }

    return empty($openings);
  }

  public static function balanceParen( $string, $startOffset = 0, $opening = "(", $closing = ")", $withparens = false ) {
    $l = strlen($string);
    $i = $startOffset ? $startOffset-1 : -1;
    $openings = array();

    while ( ++$i < $l ) {
      $c = $string[$i];
      if ( $c == $opening ) {
        $openings[] = $i;
      }
      else if ( $c == $closing ) {
        if ( empty($openings) ) return false;
        $start = array_pop($openings);
        if ( empty($openings) ) {
          $length = $i - $start + 1;
          if ( $withparens ) {
            return substr($string, $start, $length);
          }
          else return substr($string, $start + 1, $length - 2);
        }
      }
    }

    return false;
  }

  public static function substring( $string, $start, $end = null ) {
    return $end != null
      ? substr($string, $start, $end - $start)
      : substr($string, $start);
  }

  public static function replaceSubstring( $string, $start, $end = null, $with = '' ) {
    return $end != null
      ? substr_replace($string, $with, $start, $end - $start)
      : substr_replace($string, $with, $start);
  }

  public static function replace( $string, $regexp, $callback = "" ) {
    if ( is_string($callback) ) {
      $replace = $callback;
      while ( preg_match($regexp, $string, $match, PREG_OFFSET_CAPTURE) ) {
        $start = $match[0][1];
        $length = strlen($match[0][0]);
        $string = substr_replace($string, $replace, $start, $length);
      }
    }
    else if ( is_callable($callback) ) {
      while ( preg_match($regexp, $string, $groups, PREG_OFFSET_CAPTURE) ) {
        $start = $groups[0][1];
        $length = strlen($groups[0][0]);
        self::normalizeMatchSet($groups);
        $replace = call_user_func_array($callback, array($groups[0], $groups, $start, $length, $string));
        $string = substr_replace($string, $replace, $start, $length);
      }
    }

    return $string;
  }

  public static function remove( $string, $regexp, $group = 0 ) {
    if ( preg_match($regexp, $string, $match) ) {
      return $match[$group];
    }
    return null;
  }
}
