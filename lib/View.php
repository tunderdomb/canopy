<?php
namespace canopy;

use canopy\parse\Template;
use canopy\util\JSON;
use canopy\parse\Scope;
use canopy\Forest;

class View extends Template{

  protected $forest;

  function __construct( $template, Forest $forest ){
    parent::__construct($template, new Scope(array(
      'session' => $_SESSION,
      'cookie' => $_COOKIE
    )));
    $this->blockParsers['data'] = 'parseDataBlock';
    $this->blockParsers['group'] = 'parseGroup';
    $this->blockParsers['lock'] = 'parseLock';
    $this->inlineParsers['bootstrap'] = 'bootstrapData';
    $this->inlineParsers['template'] = 'insertTemplate';
    $this->inlineParsers['service'] = 'insertServiceResult';
    $this->forest = $forest;
  }

  private function getData( $dataRequest ){
    $data = $this->scope->getGlobal($dataRequest);
    if ( $data ) {
      return $data;
    }
    if ( $data = $this->forest->getData($dataRequest) ){
      $this->scope->setGlobal($dataRequest, $data);
      return $data;
    }
  }

  // TODO: add function support for template parser
  // and make the only block parser 'block'
  /*
   * {data trunk.branch group = something}
   * */
  protected function parseDataBlock( $arguments, $yep, $nope='' ){
    $parsed = '';
    if ( preg_match('/^([\w\.]+\([^\(\)]*\))(?:\s+group\s*\=\s*(\w+)\s*)?/', $arguments, $match) ) {
      @list(, $dataRequest, $group) = $match;
      $basket = $this->getData($dataRequest);
      $parsed = '';
      if( !empty($basket) ){
        $this->scope->open();
        if ( $group ){
          foreach ( $basket as $fruit ) {
            $this->scope->registerLocals($fruit);
            $this->currentParseGroup = $fruit[$group];
            $parsed .= $this->parse($yep);
          }
        }
        else {
          foreach ( $basket as $fruit ) {
            $this->scope->registerLocals($fruit);
            $parsed .= $this->parse($yep);
          }
        }
        $this->scope->close();
      }
    }

    return $parsed ? $parsed : $nope;
  }

  private function parseLock( $arguments, $yep, $nope='' ){
    $lock = preg_split('/\s+/', trim($arguments, '\s'));
    if( $this->forest->visitor->unlock($lock) ) return $yep;
    else return $nope;
  }

  /*
   * {bootstrap assign = trunk.branch group = aColumnName}
   * */
  protected function bootstrapData( $arguments ){
    if ( preg_match('/^\s*(?:(\w+)\s*\=\s*)?([\w\.]+\([^\(\)]*\))(?:\s+group\s*\=\s*(\w+)\s*)?/', $arguments, $match) ) {
      @list(, $assign, $dataRequest, $group) = $match;
      $basket = $this->getData($dataRequest);
      if ( !empty($basket) ){
        /*
         * if grouping is specified
         * it will map the given data attribute
         * and generate an array for each unique value
         * */
        if ( $group ){
          $ret = array();
          foreach( $basket as $row ){
            if ( $row[$group] ){
              $ret[$row[$group]]
                ? $ret[$row[$group]] []= $row
                : $ret[$row[$group]] = array($row);
            }
          }
          $basket = $ret;
        }
        /*
         * assigning to a variable in the template
         * won't show up in the markup
         * instead it will be available
         * as a variable named as the assign option
         * */
        if ( $assign ){
          $this->scope->set($assign, $basket);
        }
        /*
         * else it bootstraps the data as json
         * */
        else {
          try{
            return JSON::stringify($ret);
          }
          catch ( JSONParseError $e ){
            return '';
          }
        }
      }
    }
    return '';
  }

  private $currentParseGroup;

  protected function parseGroup( $arguments, $yep, $nope ){
    if ( preg_match('/^\s*'.$this->currentParseGroup.'\s*/', $arguments) ){
      return $yep;
    }
    return $nope;
  }

  protected function insertTemplate( $arguments, &$skiptInsert ){
    $skiptInsert = false;
    $arguments = trim($arguments);
    return $this->forest->template($arguments);
  }

  protected function insertServiceResult( $arguments, &$skipInsert ){
    if( preg_match('/(\w+)\s*\=\s*(.+)$/', $arguments, $match) ) {
      list(, $variable, $serviceRequest ) = $match;
      $result = $this->forest->runService($serviceRequest);
      $this->scope->set($variable, $result);
      $skipInsert = true;
      return;
    }
    else {
      $ret = $this->forest->runService($arguments);
      try{
        return JSON::stringify($ret);
      }
      catch ( JSONParseError $e ){
        return '';
      }
    }
  }
}