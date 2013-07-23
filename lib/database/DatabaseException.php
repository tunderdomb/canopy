<?php

namespace canopy\database;

class DatabaseException extends \Exception{
  function __construct( $message ) {
    parent::__construct($message);
  }
}
