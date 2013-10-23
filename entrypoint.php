<?php

use canopy\util\Autoload;
use canopy\Canopy;

include __DIR__.strtr('/canopy/util/Autoload.php', '/', DIRECTORY_SEPARATOR);
$loader = new Autoload(__DIR__);

$canopy = Canopy::plant(array(
  'root' => '',
  'log' => array(
    'path' => '../log.log',
    'depth' => 1
  ),
  'history' => array(
    // enable switch
    'record' => true,
    // a session variable to store the url history
    'sessionKey' => 'history',
    // the number of urls to keep track of
    'depth' => 3
  ),
  'session' => array(
    // start the session automatically
    'start' => true,
    // a session variable for one time session keys (variables that lives for one request)
    'oneTimeKey' => 'oneTimeKeys'
  ),
  // cookie setting for the session cookie
  'cookie' => array(
    'enable' => true,
    'name' => null,
    'lifetime' => 0,
    'path' => '/',
    'domain' => null,
    'secure' => false,
    'httponly' => true
  ),
  // database credentials
  'connection' => array(
    'server' => '127.0.0.1',
    'user' => 'root',
    'password' => '',
    'database' => '',
    // list additional credentials for different user classes here
    'userClasses' => array()
  ),
  'visitor' => array(
    'table' => 'users',
    'userId' => 'uid',
    'loginId' => 'email',
    'password' => 'password',
    'rememberField' => 'rememberme',
    'sessionUserData' => 'user',
    'userClass' => '',
    'passKeys' => 'passkeys',
    'cookie' => array(
      'name' => 'visitor',
      'expire' => 0
    ),
    'fieldMap' => array(
      'email' => array(
        'column' => 'login',
        'pattern' => '//'
      ),
      'password' => array(
        'column' => 'password',
        'pattern' => '//'
      )
    )
  ),
  // url mappings
  'routes' => array(
    '/' => array(
      'render' => 'layout',
      'params' => array(
        'main' => 'home'
      )
    ),

    '*' => array(
      'render' => 'layout'
    )
  ),
  // response details
  'response' => array(
    // respond to requests automatically
    'auto' => true,
    // error reporting and logging presets
    'mode' => 'dev', // dev | test | update | live
    // break responding on data errors
    'breakOnData' => true
  ),
  // template options
  'templates' => array(
    // template root
    'dir' => 'templates',
    // default template extension
    'extension' => 'html'
  ),
  // error documents
  'errors' => array(
    // error docs folder
    'errorDocs' => 'templates/errors',
    // whether or not to parse error docs
    'show' => 'docs' // docs | templates
  )
));