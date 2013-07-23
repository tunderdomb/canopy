<?php
namespace canopy\util;

class JSON {

  public static function stringify( $json, $options = 0 ) {
    $json = json_encode($json, $options);
    self::validate();
    return $json;
  }

  public static function parse( $json, $assoc = false, $depth = 512, $options = 0 ) {
    $json = json_decode($json, $assoc, $depth, $options);
    self::validate();
    return $json;
  }

  private static function validate() {
    switch ( json_last_error() ) {
      case JSON_ERROR_NONE:
        return;
      case JSON_ERROR_DEPTH:
        throw new JSONParseError('Maximum stack depth exceeded');
      case JSON_ERROR_STATE_MISMATCH:
        throw new JSONParseError('Underflow or the modes mismatch');
      case JSON_ERROR_CTRL_CHAR:
        throw new JSONParseError('Unexpected control character found');
      case JSON_ERROR_SYNTAX:
        throw new JSONParseError('Syntax error, malformed JSON');
      case JSON_ERROR_UTF8:
        throw new JSONParseError('Malformed UTF-8 characters, possibly incorrectly encoded');
      default:
        throw new JSONParseError('Unknown error');
    }
  }
}
