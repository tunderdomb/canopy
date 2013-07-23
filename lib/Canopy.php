<?php
namespace canopy;

use canopy\filesystem\Directory;
use canopy\util\Autoload;

final class Canopy {
  private static $forest = null;
  public final static function plantForest( Visitor $visitor, Directory $documentRoot, Autoload $autoLoader, $serviceNS ){
    if( !self::$forest ){
      self::$forest = new Forest($visitor, $documentRoot);
      $autoLoader->register($serviceNS);
    }
    return self::$forest;
  }
}
