<?php
namespace canopy\parse;

class Token {
  public $type;
  public $value;

  public function __construct( $value, $type=null ) {
    $this->value = $value;
    $this->type = $type;
  }

  public function __toString() {
    return (string)$this->value;
  }
}
