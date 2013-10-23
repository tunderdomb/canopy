<?php
namespace canopy;

use canopy\parse\String;
use canopy\parse\Template;
use canopy\util\JSON;
use canopy\parse\Scope;
use canopy\Forest;
use canopy\util\JSONParseError;

class View extends Template{

  protected $canopy;

  function __construct( $template, Canopy $canopy ){
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
    $this->canopy = $canopy;
  }

  private function getData( $dataRequest ){
    $data = $this->scope->getGlobal($dataRequest['source']);
    if ( $data ) {
      return $data;
    }
    if ( $data = $this->canopy->getData($dataRequest) ){
      $this->scope->setGlobal($dataRequest['source'], $data);
      return $data;
    }
  }


  // TODO: add function support for template parser
  // and make the only block parser 'block'
  /*
   * {data source="" branch="" params="" group=""}
   * */
  protected function parseDataBlock( $arguments, $yep, $nope='' ){
    $requestOptions = String::parseTagAttributes($arguments);
    $data = $this->getData($requestOptions);
    $parsed = '';
    if( !empty($data) ){
      $this->scope->open();
      if ( $requestOptions['group'] ){
        foreach ( $data as $group => $row ) {
          $this->currentParseGroup = $group;
          foreach ( $row as $groupedData ) {
            $this->scope->registerLocals($groupedData);
            $parsed .= $this->parse($yep);
          }
        }
      }
      else {
        foreach ( $data as $row ) {
          $this->scope->registerLocals($row);
          $parsed .= $this->parse($yep);
        }
      }
      $this->scope->close();
    }

    return $parsed ? $parsed : $nope;
  }

  protected function parseLock( $arguments, $yep, $nope='' ){
    $lock = preg_split('/\s+/', trim($arguments, '\s'));
    if( $this->canopy->visitor->unlock($lock) ) return $yep;
    else return $nope;
  }

  /*
   * {{bootstrap assign="<variable name>"  [data request options] }}
   * */
  protected function bootstrapData( $arguments ){
    $arguments = String::parseTagAttributes($arguments);
    $data = $this->getData($arguments);
    $ret = array();

    if ( !empty($data) ){
      /*
       * if grouping is specified
       * it will map the given data attribute
       * and generate an array for each unique value
       * */
      $group = $arguments['group'];
      if ( $group ){
        foreach( $data as $row ){
          if ( $row[$group] ){
            $ret[$row[$group]]
              ? $ret[$row[$group]] []= $row
              : $ret[$row[$group]] = array($row);
          }
        }
        $data = $ret;
      }
      /*
       * assigning to a variable in the template
       * won't show up in the markup
       * instead it will be available
       * as a variable named as the assign option
       * */
      if ( $arguments['assign'] ){
        $this->scope->set($arguments['assign'], $data);
      }
      /*
       * else it bootstraps the data as json
       * */
      else {
        $ret = JSON::stringify($ret);
        if( !JSON::$error ) return $ret;
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

  protected function insertTemplate( $arguments, &$skipInsert ){
    $skipInsert = false;
    $arguments = trim($arguments);
    $tpl = $this->interpreter->evaluate($arguments);
    $tpl = $tpl ? $tpl : $arguments;
    return $this->canopy->template($tpl);
  }

  protected function insertServiceResult( $arguments, &$skipInsert ){
    if( preg_match('/(\w+)\s*\=\s*(.+)$/', $arguments, $match) ) {
      list(, $variable, $serviceRequest ) = $match;
      $result = $this->canopy->runService($serviceRequest);
      $this->scope->set($variable, $result);
      $skipInsert = true;
      return;
    }
    else {
      $ret = $this->canopy->runService($arguments);
      try{
        return JSON::stringify($ret);
      }
      catch ( JSONParseError $e ){
        return '';
      }
    }
  }
}