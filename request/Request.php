<?php
namespace canopy\request;

use canopy\util\JSON;

const ROOTDIR = __DIR__;

class Request {
  public $protocol;
  public $host;
  public $hostname;
  public $port;
  public $origin;
  public $href;
  public $pathname;
  public $url;
  public $method;

  public $data;
  public $uploads;
  public $post;
  public $get;
  public $put;
  public $delete;
  public $raw, $json;

  public $error;

  public function __construct() {
    $this->protocol = $_SERVER['REQUEST_SCHEME'];
    $this->host = $_SERVER['SERVER_ADDR'];
    $this->hostname = $_SERVER['SERVER_NAME'];
    $this->pathname = preg_replace('/(?:\?.*?$)/', '', $_SERVER['REQUEST_URI']);
    $this->port = $_SERVER['SERVER_PORT'];
    $this->origin = ($this->protocol ? $this->protocol. '://' : '') . $this->hostname;
    $this->href = $this->origin . $this->pathname;
    $this->url = $this->protocol . '://' . $this->hostname . ':' . $this->port . $this->pathname;
    $this->method = $_SERVER['REQUEST_METHOD'];

    switch ( $_SERVER['REQUEST_METHOD'] ) {
      case 'POST' :
        $this->data = $this->post = $_POST;
        break;
      case 'GET':
        $this->data = $this->get = $_GET;
        break;
      case 'PUT':
        $this->data = $this->put = $_SERVER['REQUEST_URI'];
        break;
      case 'DELETE':
        $this->data = $this->delete = $_SERVER['REQUEST_URI'];
        break;
    }

    if ( array_key_exists('HTTP_RAW_POST_DATA', $GLOBALS) && empty($_POST) ) {
      $this->data = $this->raw = $raw = $GLOBALS['HTTP_RAW_POST_DATA'];

      if ( @$_SERVER['CONTENT_TYPE'] == "application/json" ){
        $this->data = $this->json = JSON::parse($raw, true);
      }
    }
    if ( !empty($_FILES) ) {
      $this->normalizeUploads();
    }
  }

  public function validate( $signature, $notNull = false ) {
    $valid = true;
    $i = -1;
    $l = count($signature);
    $data = $this->data;
    if ( $notNull ) {
      while ( $valid && ++$i < $l ) {
        $sig = $signature[$i];
        $valid &= array_key_exists($sig, $data) && $data[$sig] != '';
      }
    }
    else {
      while ( $valid && ++$i < $l ) {
        $valid &= array_key_exists($signature[$i], $data);
      }
    }

    return $valid;
  }

  private function normalizeUploads() {
    $normalized = array();
    foreach ( $_FILES as $file => $props ) {
      $normalized[$file] = array();
      foreach ( $props as $prop => $values ) {
        if ( is_array($values) ) {
          foreach ( $values as $i => $data ) {
            $normalized[$file][$i]['name'] = $props['name'][$i];
            $normalized[$file][$i]['size'] = $props['size'][$i];
            $normalized[$file][$i]['src'] = $props['tmp_name'][$i];
            $normalized[$file][$i]['type'] = $props['type'][$i];
            $normalized[$file][$i]['error'] = $props['error'][$i];
          }
        }
        else {
          $normalized[$file]['name'] = $props['name'];
          $normalized[$file]['size'] = $props['size'];
          $normalized[$file]['src'] = $props['tmp_name'];
          $normalized[$file]['type'] = $props['type'];
          $normalized[$file]['error'] = $props['error'];
        }
      }
    }
    $this->uploads = $normalized;
  }

  public function upload( $file, $destination, $rename = 0 ) {
    $src = $file['src'];
    $name = $file['name'];
    $destination = ROOTDIR . '/' . trim($destination, '/');
    //chdir($destination);
    //trace(getcwd(), '\n', $destination, '\n');
    //trace(scandir(getcwd()));
    if ( !file_exists($destination) ) {
      mkdir($destination);
      scandir($destination);
    }

    //trace('upload $src to $destination/$name\n');

    if ( $rename ) {
    }
    if ( is_uploaded_file($src) ) {
      if ( move_uploaded_file($src, $destination . '/' . $name) ) {
        Console::log(' SUCCESS $destination/$name;\n');

        return $destination . '/' . $name;
      }
      else {
        Console::log('cant upload $src to $destination\n');
      }
    }
  }

}
