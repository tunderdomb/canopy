<?php

namespace canopy;

class Data {

  private $id;
  private $data;
  private $changed = array();
  private $deleted = false;

  public function __construct( $id, $data) {
    $this->id = $id;
    $this->data = $data;
  }

  public function get( $column ) {
    return $this->data[$column];
  }

  public function set( $column, $value ) {
    if ( isset($this->data[$column]) ) {
      return $this->changed[$column] = $this->data[$column] = $value;
    }

    return false;
  }

  public function delete() {
    $this->deleted = true;
  }

  public function extractData() {
    return array_map('utf8_encode', $this->data);
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