<?php
namespace canopy;

use canopy\request\Request;
use canopy\request\Session;
use canopy\filesystem\Path;
use canopy\util\JSONParseError;
use canopy\request\History;
use canopy\filesystem\IOException;
use canopy\filesystem\Directory;
use canopy\request\Response;
use canopy\util\JSON;
use canopy\util\Console;

class Forest {
  public $response;
  public $request;
  public $route;
  public $history;
  public $gateway;
  public $visitor;
  public $root;

  private $serviceNS='\\';
  private $nativeServices = array(
    'register', 'login', 'logout'
  );
  private $templateDirectory;
  private $errorDocDirectory;
  private $errorUrls;
  public $parseErrorPages = false;
  public $showErrorPages = false;
  public $redirectErrors;

  public function __construct( Visitor $visitor, Directory $documentRoot ) {
    $this->visitor = $visitor;
    $this->root = $documentRoot;
    $this->request = new Request();
    $this->response = new Response();
    $this->route = new RouteMap();
    $this->history = new History();
  }
  public function setResponseMode( $mode ){
    switch ( $mode ){
      case 'develop':
        error_reporting(E_ALL ^ E_NOTICE);
        Console::enable();
        Console::setEmbed(true);
        break;

      case 'test':
        error_reporting(-1);
        Console::enable();
        Console::setEmbed(true);
        break;

      case 'patch':
        error_reporting( E_ERROR | E_WARNING );
        Console::enable();
        Console::setEmbed(false);
        break;

      case 'update':
        error_reporting( E_ERROR | E_WARNING | E_PARSE | E_NOTICE | E_STRICT );
        Console::enable();
        Console::setEmbed(false);
        break;

      case 'publish':
        error_reporting(0);
        Console::disable();
        Console::setEmbed(false);
        break;

      default:
        error_reporting(-1 ^ E_NOTICE);
        Console::disable();
        Console::setEmbed(false);
    }
  }
  public function setLog( $savePath ){
    if ( $savePath = Path::realpath($savePath, $this->root->root) ){
      Console::savePath($savePath);
      return true;
    }
  }

  public function createGateWay( $defaultServer, $defaultUser, $defaultPassword, $defaultDatabase, $userClasses=array() ){
    $this->gateway = new Gateway(array($defaultServer, $defaultUser, $defaultPassword, $defaultDatabase), $userClasses);
  }
  public function setTemplateDirectory( Directory $templateDirectory ){
    $this->templateDirectory = $templateDirectory;
  }
  public function setErrorDocs( Directory $errorDirectory ){
    $this->errorDocDirectory = $errorDirectory;
  }
  public function setErrorUrls( $errorUrls ){
    $this->errorUrls = $errorUrls;
  }
  public function setServiceNS( $namespace ){
    $this->serviceNS = $namespace;
  }

  public function view( $tpl ){
    try{
      return new View($this->templateDirectory->readFile($tpl), $this);
    }
    catch ( IOException $e ){
      return null;
    }
  }
  public function template( $tpl ){
    try{
      return $this->templateDirectory->readFile($tpl);
    }
    catch ( IOException $e ){
      return null;
    }
  }
  public function errorDoc( $tpl ){
    try{
      return $this->errorDocDirectory->readFile($tpl.'.html');
    }
    catch ( IOException $e ){
      return null;
    }
  }

  public function startSession(){
    Session::start();
    $this->visitor->onsessionstart($this);
  }

  final public function respond() {
    if ( $this->response->isAlreadySent() ) return;

    $route = $this->route;
    if( !$route ){
      return $this->deadEnd(404);
    }

    if ( @$route->lock && $this->visitor && !$this->visitor->unlock($route->lock) )
      return $this->deadEnd(403, null, 'Restricted route');

    // service
    if ( @$route->service ) $this->runService($route->service);

    // data
    if ( @$route->data ) $this->serveData($route->data);

    // render
    if ( @$route->render ) {
      if ( $this->render($route->render) )
        $this->history->push($this->request->pathname);
    }

    // redirect
    if ( @$route->redirect ) $this->redirect($route->redirect);

    $this->response->send();
  }

  public function render( $tpl, $templateScope=null ){
    if ( $tpl = $this->template($tpl) ){
      $template = new View($tpl, $this);
      $render = $template->render($templateScope ? $templateScope : array(
        'route'   => $this->route->var
      , 'request' => $this->request->data
      , 'post' => $this->request->post
      , 'get' => $this->request->get
      , 'raw' => $this->request->raw
      , 'session' => $_SESSION
      , 'cookie'  => $_COOKIE
      ));

      $render = Console::embedTtoHTML($render, $this);

      $this->response->contentType('text/html');
      $this->response->body($render);
      return true;
    }
    $this->deadEnd(404);
  }

  public function getFactory( $dataRequest, &$branch, &$params, &$group ){
    if ( preg_match('/^([\w\.]+)(?:\(\s*(\w+)(?:\s*,\s*(\w+)(?:\s*,\s*(\w+))?)?\s*\))?/', $dataRequest, $match) ){
      list(, $classPath, $branch, $source, $group) = $match;
      $classPath = strtr($classPath, '.', '\\');
      switch ($source) {
        case 'request':
          $params = $this->request->data;
          break;
        case 'post':
          if ( $this->request->method == 'POST' )
            $params = $this->request->post;
          else return $this->deadEnd(404, null, 'Bad request');
          break;
        case 'get':
          if ( $this->request->method == 'GET' )
            $params = $this->request->get;
          else return $this->deadEnd(404, null, 'Bad request');
          break;
        case 'json':
          if ( $this->request->json )
            $params = $this->request->json;
          else return $this->deadEnd(404, null, 'Bad request');
          break;
        case 'session':
          $params = $_SESSION;
          break;
        case 'url':
          $params = $this->route->var;
        default: $params = $this->route->var;
      }
      try{
        $factory = new $classPath();
      }
      catch( \Exception $e ){
        return $this->deadEnd(500, null, 'Invalid data request, class does not exists');
      }
      return $factory;
    }
    else {
      $this->deadEnd(500, null, 'Invalid data request '.$dataRequest);
    }
  }
  public function getData( $dataRequest, $params=null, $group=null ){
    $factory = $this->getFactory($dataRequest, $branch, $source, $groupBy);
    if( !$factory ) return null;
    $params = $params ? $params : $source;
    $group = $group ? $group : $groupBy;
    $dataSet = $factory->fetch($this->gateway->connect(), $branch, $params, true, $group);
    if( $factory->error ){
      return $this->deadEnd(500, null, 'Data request error '.$factory->error);
    }
    return $dataSet;
  }
  /*
   * class.path(dataBranch, source, groupby)
   * source sets the params for the data fetch
   * source = request | post | get | json | session | url
   * */
  public function serveData( $dataRequest, $params=null, $group=null ){
    $dataSet = $this->getData($dataRequest, $params, $group);
    if( !$dataSet ) return null;
    $json = JSON::stringify($dataSet);
    if ( $this->route->response ){
      $this->response->setResponseType($this->route->response);
    }
    $this->response->contentType('application/json');
    $this->response->body($json);
    return true;
  }
  private function runNativeService( $service ){
    switch($service){
      case 'register':
        $this->visitor->register($this);
        break;
      case 'login':
        $this->visitor->login($this);
        break;
      case 'logout':
        $this->visitor->logout($this);
        break;
    }
  }
  public function runService( $classpath ){
    $class = trim(strtr($classpath, '.', '\\'));
    if( in_array($classpath, $this->nativeServices) ){
      return $this->runNativeService($classpath);
    }
    if ( preg_match('/^([\w+\\\]+?)\\\(\w+)$/', $class, $match) ){
      $class = $this->serviceNS.$match[1];
      $method = $match[2];
      /*if( $match[1] == 'canopy' ){
        $visitorControl = new VisitorControl();
        if( method_exists($visitorControl, $method) ){
          return $visitorControl->$method($this);
        }
      }
      else*/ if ( class_exists($class) ){
        if ( method_exists($class, $method) ){
          $obj = new \ReflectionMethod($class, $method);
          return $obj->invoke(new $class($this), $this);
        }
      }
      else {
        $this->deadEnd(500, null, 'Request to undefined method '.$classpath);
      }
    }
    else {
      $this->deadEnd(500, null, 'Request to undefined method '.$classpath);
    }
  }
  public function redirect( $url ){
    if ( $this->history ){
      if ( is_int($url) && $url = $this->history->pop($url) ){
        return $this->response->redirect($url);

      }
      else return $this->response->redirect($url);
    }
    else return $this->response->redirect($url ? $url : '/');
  }

  public function download( $file ){
    if ( $file = $this->file($file) ){
      // set proper headers and such..
    }
    else {

    }
  }

  private function parseErrorPage( $tpl, $templateScope=null ){
    $template = new View($tpl, $this);
    return $template->render($templateScope ? $templateScope : array(
      'route'   => $this->route->var
    , 'request' => $this->request->data
    , 'post' => $this->request->post
    , 'get' => $this->request->get
    , 'raw' => $this->request->raw
    , 'session' => $_SESSION
    , 'cookie'  => $_COOKIE
    ));
  }
  public function deadEnd( $errorCode, \Exception $e=null, $message=null ){
    $message = $e ? $e->getMessage() : $message;
    $message && Console::log($message);

    if ( $this->redirectErrors && !empty($this->errorUrls) && @$url = $this->errorUrls->$errorCode ){
      $this->redirect($url);
    }
    else if ( $this->errorDocDirectory && $this->showErrorPages ) {
      if( $tpl = $this->errorDoc($errorCode) ){
        if ( $this->parseErrorPages ){
          $tpl = $this->parseErrorPage($tpl);
        }
        $tpl = Console::embedTtoHTML($tpl);
        $this->response->contentType('text/html');
        $this->response->body($tpl);
      }
      else {
        $this->response->body('Missing Error Page '.$errorCode);
        $this->response->status(500);
      }
    }
    else{
      $this->response->body($message);
      $this->response->status($errorCode);
    }
    Console::save();
    $this->response->send();
    return null;
  }
}
