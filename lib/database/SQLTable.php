<?php
namespace canopy\database;

class SQLTable {

  const STACK_DELETE = "delete";
  const STACK_UPDATE = "update";
  const STACK_INSERT = "insert";
  private $stacking = false;
  private $queryStack = array(
    'fields' => array(),
    'values' => array()
  );

  private $handler;
  private $name;
  private $idColumn;
  private $fields;

  public function __construct( SQLHandler $handler, $name, $idColumn, $fields = null ) {
    $this->handler = $handler;
    $this->name = $name;
    $this->idColumn = $idColumn;
    $this->fields = $fields;
  }

  final public function insert( $fields ) {
    if ( empty($fields) ) throw new DatabaseException('Invalid query');

    $columns = "";

    $this->handler->bindParam($fields);

    if ( (bool)count(array_filter(array_keys($fields), 'is_string')) ) {
      $columns = array();
      $values = array();
      foreach ( $fields as $field ) {
        $columns[] = $field;
        $values[] = "?";
      }
      $columns = implode(',', $columns);
      $values = implode(',', $values);
    }
    else {
      $values = implode(',', $fields);
    }

    $sql = "insert into $this->name $columns values ($values)";

    return $this->handler->query($sql);
  }

  final public function stackFields( $fields ) {
    $this->queryStack['fields'] = $fields;
  }

  final public function insertMultiple( $fields ) {
    if ( empty($fields) ) {
      throw new DatabaseException('Invalid query');
    }

    if ( !$this->stacking ) $this->stacking = self::STACK_INSERT;
    else if ( $this->stacking != self::STACK_INSERT ) throw new DatabaseException('Query stack collision');
    if ( empty($this->queryStack['fields']) ) {
      $this->queryStack['fields'] = array_merge($this->queryStack['fields'], array_keys($fields));
    }
    $this->queryStack['values'][] = $fields;

    return $this;
  }

  final public function update( $fields, $id = null ) {
    if ( empty($fields) ) {
      throw new DatabaseException('Invalid query');
    }

    $sql = "update $this->name";
    foreach ( $fields as $field ) {
      $this->handler->bindParam($field['value']);
      $sql .= " set " . $field['field'] . " = ?";
    }
    if ( $id != null ) {
      $this->handler->bindParam($id);
      $sql .= " where $this->idColumn = $id";
    }

    return $this->handler->query($sql);
  }

  final public function updateMultiple( $fields, $id ) {
    if ( empty($fields) ) {
      throw new DatabaseException('Invalid query');
    }
    iF ( !$this->stacking ) $this->stacking = self::STACK_UPDATE;
    if ( $this->stacking != self::STACK_UPDATE ) {
      throw new DatabaseException('Query stack collision');
    }

    foreach ( $fields as $field => $value ) {
      if ( !$this->queryStack[$field] ) {
        $this->queryStack[$field] = array();
      }
      $this->queryStack[$field][$id] = $value;
    }

    return $this;
  }

  final public function delete( $id ) {
    $this->handler->bindParam($id);

    return $this->handler->query("delete from $this->name where $this->idColumn = ?");
  }

  final public function deleteWhere( $where ) {
    return $this->handler->query("delete from $this->name where $where");
  }

  final public function deleteMultiple( $id ) {
    if ( !$this->stacking ) $this->stacking = self::STACK_DELETE;
    if ( $this->stacking != self::STACK_DELETE ) throw new DatabaseException('Query stack collision');
    $this->queryStack[] = $id;

    return $this;
  }

  final public function save() {
    if ( !$this->stacking ) return true;

    $sql = '';

    $stack = $this->queryStack;
    $idColumn = $this->idColumn;

    switch ( $this->stacking ) {
      case self::STACK_INSERT:
        $valuelist = $stack['values'];
        foreach ( $valuelist as $key => $values ) {
          foreach ( $values as $i => $value ) {
            $this->handler->bindParam($value);
            $values[$i] = '?';
          }
          $valuelist[$key] = '(' . implode(',', $values) . ')';
        }
        $fields = implode(',', $this->whiteListFields($stack['fields']));
        $values = implode(',', $valuelist);
        $sql = "insert into $this->name ($fields) values $values";
        break;

      case self::STACK_DELETE :
        $id = array_shift($stack);
        $this->handler->bindParam($id);
        $sql = "delete from $this->name where $idColumn = ?";

        foreach ( $stack as $id ) {
          $this->handler->bindParam($id);
          $sql .= " or $idColumn = ?";
        }
        break;

      case self::STACK_UPDATE:
        $inIds = array();
        foreach ( $stack as $field => $ids ) {
          $sql .= "set $field = case $this->idColumn ";
          foreach ( $ids as $id => $value ) {
            $this->handler->bindParam($id);
            $this->handler->bindParam($value);
            $sql .= "when $id then ?";
          }
          $sql .= " end,";
          $inIds = array_merge($inIds, $ids);
        }
        $inIds = array_keys($inIds);
        $sql .= "where $this->idColumn in ($inIds);";
        break;
    }

    $this->stacking = false;
    $this->queryStack = array(
      'fields' => array(),
      'values' => array()
    );

    return $this->handler->query($sql);
  }

  private function whiteListFields( $fields ) {
    return $this->fields
      ? array_intersect_assoc($this->fields, $fields)
      : $fields;
  }
}
