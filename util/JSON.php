<?php
namespace canopy\util;

class JSON {

  public static $error;

  public static function stringify( $json, $options = 0 ) {
    self::$error = null;
    $json = json_encode($json, $options);
    self::validate();
    return $json;
  }

  public static function parse( $json, $assoc = false, $depth = 512, $options = 0 ) {
    self::$error = null;
    $json = json_decode($json, $assoc, $depth, $options);
    self::validate();
    return $json;
  }

  private static function validate() {
    switch ( json_last_error() ) {
      case JSON_ERROR_NONE: return;
      case JSON_ERROR_DEPTH:
        self::$error = 'Maximum stack depth exceeded';
        break;
      case JSON_ERROR_STATE_MISMATCH:
        self::$error = 'Underflow or the modes mismatch';
        break;
      case JSON_ERROR_CTRL_CHAR:
        self::$error = 'Unexpected control character found';
        break;
      case JSON_ERROR_SYNTAX:
        self::$error = 'Syntax error, malformed JSON';
        break;
      case JSON_ERROR_UTF8:
        self::$error = 'Malformed UTF-8 characters, possibly incorrectly encoded';
        break;
      default:
        self::$error = 'Unknown error';
    }
  }
}
