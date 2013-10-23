<?php
namespace canopy;

use canopy\util\PasswordHash;

class Password {

  private $hasher;

  function __construct( $cost=8 ) {
    $this->hasher = new PasswordHash($cost);
    $this->hasher->PasswordHash(8, false);
  }

  public function hash( $password ){
    return $this->hasher->HashPassword($password);
  }

  public function verify( $password, $storedHash ){
    return $this->hasher->CheckPassword($password, $storedHash);
  }
}
