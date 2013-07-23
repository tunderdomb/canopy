<?php

namespace canopy;

class Data {

  private $id;
  private $fruit;
  private $leaves = array();
  private $changed = array();
  private $deleted = false;

  public function __construct( $fruit, Plant $plant, $idColumn ) {
    $this->id = $fruit[$idColumn];
    $this->leaves = $plant->columnNames();
    $this->fruit = $fruit;
  }

  public function get( $leaf ) {
    return $this->fruit[$leaf];
  }

  public function set( $leaf, $value ) {
    if ( isset($this->fruit[$leaf]) ) {
      return $this->changed[$leaf] = $this->fruit[$leaf] = $value;
    }

    return false;
  }

  public function delete() {
    $this->deleted = true;
  }

  public function extractLeaves() {
    return array_map('utf8_encode', $this->fruit);
  }

  public function changedColumns() {
    return $this->changed;
  }

  public function getId() {
    return $this->id;
  }

  final public function isDeleted() {
    return $this->deleted;
  }

  final public function isChanged() {
    return empty($this->changed);
  }
}