<?php
namespace canopy\filesystem;
class IOException extends \Exception{
  public function __construct( $msg ) {
    parent::__construct($msg);
  }
}
