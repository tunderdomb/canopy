<?php
namespace canopy\util;

class JSONParseError extends \Exception{
  function __construct( $message ) {
    parent::__construct($message);
  }
}

