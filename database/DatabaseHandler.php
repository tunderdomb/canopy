<?php
namespace canopy\database;

interface DatabaseHandler {
  public function connect();
  public function selectDB( $database );
  public function bindParam( $param, $type = null );
  public function query( $sql );
}
