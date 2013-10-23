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
    if( $p ) return $p;
  }

  private function realpath( $path ) {
    if ( Path::isAbsolute($path) ) {
      $p = Path::realpath($path);
    }
    else $p = Path::realpath($path, $this->root);
    if( $p ) return $p;
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
