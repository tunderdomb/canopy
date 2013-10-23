<?php
namespace canopy\filesystem;

use canopy\util\JSON;

class Directory {

  public $root;

  function __construct( $root = null ) {
    $this->root = $root
      ? $this->realpath($root)
      : rtrim($_SERVER['DOCUMENT_ROOT'], '\\\/') . DIRECTORY_SEPARATOR;
  }

  private function normalize( $path ) {
    if ( Path::isAbsolute($path) ) {
      $p = Path::normalize($path);
    }
    else $p = Path::normalize($path, $this->root);
    if ( $p ) return $p;
  }

  private function realpath( $path ) {
    if ( Path::isAbsolute($path) ) {
      $p = Path::realpath($path);
    }
    else $p = Path::realpath($path, $this->root);
    if ( $p ) return $p;
  }

  public function readFile( $filePath ) {
    return @file_get_contents($this->realpath($filePath));
  }

  public function writeFile( $filePath, $contents, $flags = null, $context = null ) {
    return file_put_contents($this->normalize($filePath), $contents, $flags, $context);
  }

  public function readJSON( $filePath, $assoc = false, $depth = 512, $options = 0 ) {
    return JSON::parse($this->readFile($filePath), $assoc, $depth, $options);
  }

  public function writeJSON( $filePath, $contents, $options = 0 ) {
    return $this->writeFile($filePath, JSON::stringify($contents, $options));
  }

  public function readBinary() {
  }

  public function listContents( $path = "", $filter = null, $deep = false ) {
    if( $path == "" ) {
      $path = $this->root;
      $rootSrc = '/';
    }
    else if ( !Path::isAbsolute($path) ) {
      $rootSrc = $path;
      $path = Path::concat($this->root, Path::strip($path));
    }
    @$contents = scandir($path);
    if ( !$contents ) return null;

    if ( $deep ) {
      foreach ( $contents as $i => $name ) {
        if ( $name == '.' || $name == '..' ) continue;
        $name = Path::concat($path, $name);
        if ( is_dir($name) ) {
          $contents[$i] = $this->listContents($name, $filter, true);
        }
        else {
          $contents[$i] = Path::concat($rootSrc, $name);
        }
      }
    }

    if ( $filter == null ) return $contents;
    else return $this->filterListContents($contents, $filter);
  }

  private function filterListContents( $contents, $filter ) {
    $ret = array();
    foreach ( $contents as $name ) {
      if ( $name == '.' || $name == '..' ) continue;
      if ( is_array($name) ) {
        $ret[] = $this->filterListContents($name, $filter);
      }
      else if ( call_user_func($filter, $name) ) {
        $ret[] = $name;
      }
    }

    return $ret;
  }

  public function writeBinary() {
  }

  public function moreTo() {
  }

  public function copyTo() {
  }

  public function size() {
  }

  public function isFile() {
  }

  public function isDir() {
  }

  public function exists( $fileOrDir ) {
  }
}
