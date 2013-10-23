<?php
namespace canopy\request;

use canopy\util\JSON;

class Response {
  private $sent = false;
  private $body = '';
  private $headers = array();
  private $responseType;

  function __construct( $mode=null ) {

  }

  public function setResponseType( $type ){
    $this->responseType = $type;
  }

  public function selectResponseMode(){
    switch( $this->respMode ){
      case 'publish':
        error_reporting(0);
        break;
      case 'develop':
        error_reporting(E_ALL);
        break;
      case 'maintenance':
        error_reporting(E_ALL ^ E_NOTICE);
        break;
      default:
    }
  }

  public function contentType( $mime, $charset = 'utf-8' ) {
    $this->headers['Content-Type'] = $mime . ($charset ? ';' . $charset : '');
  }

  public function cacheControl( $maxage ) {
    $this->headers['Cache-Control'] = 'max-age=' . $type;
  }

  public function expires( $date ) {
    $this->headers['Expires'] = $date;
  }

  public function status( $code ) {
    $this->headers['Status'] = $code;
  }

  public function redirect( $location ) {
    $this->headers['Location'] = $location;
  }

  public function contentDisposition( $filename ) {
    $this->headers['Content-Disposition'] = 'attachment; filename='.$filename;
  }

  public function encoding( $encoding ) {
    $this->headers['Content-Encoding'] = $encoding;
  }

  public function language( $lang ) {
    $this->headers['Content-Language'] = $lang;
  }

  public function md5( $hash ) {
    $this->headers['Content-MD5'] = $hash;
  }

  public function body( $resp ) {
    $this->body = (string)$resp;
  }

  public function append( $resp ) {
    $this->body .= (string)$resp;
  }

  public function prepend( $resp ) {
    $this->body = (string)$resp . $this->body;
  }

  public function send() {
    $this->sent = true;
    foreach ( $this->headers as $field => $value ) {
      header($field . ': ' . $value);
    }

    switch ( $this->responseType ){
      case 'boolean':
      case 'bool':
        $this->body = (boolean) $this->body;
        break;
      case 'json':
        if ( !is_string($this->body) ) $this->body = JSON::stringify($this->body);
        break;
    }
    echo $this->body;
  }
  public function isAlreadySent(){
    return $this->sent;
  }
}
