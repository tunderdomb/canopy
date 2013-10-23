<?php
namespace canopy;

use canopy\filesystem\Directory;
use canopy\util\Autoload;

use canopy\request\Request;
use canopy\request\Session;
use canopy\filesystem\Path;
use canopy\util\Console;
use canopy\util\JSONParseError;
use canopy\request\History;
use canopy\filesystem\IOException;
use canopy\request\Response;
use canopy\util\JSON;
use canopy\Visitor;

class Canopy{

  // instance
  private static $forest;

  // dirs
  public $root;
  public $templatesDir;
  public $errorDocs;

  // controllers
  public $request;
  public $history;
  public $connection;
  public $visitor;
  public $route;
  public $response;

  // config
  private $config;

  // constructor
  final private function __construct( $config ) {
    $this->config = $config = (object) $config;

    $log = (object) $config->log;
    $history = (object) $config->history;
    $session = (object) $config->session;
    $cookie = (object) $config->cookie;
    $connection = (object) $config->connection;
    $response = (object) $config->response;
    $templates = (object) $config->templates;
    $errors = (object) $config->errors;

    $this->setResponseMode($response->mode);
    $this->setLog($log->path);

    $this->root = new Directory($config->root);
    $this->templatesDir = new Directory($templates->dir);
    $this->errorDocs = new Directory($errors->errorDocs);

    $this->request = new Request();
    $this->history = new History();
    $this->route = new RouteMap();
    $this->response = new Response();
    $this->visitor = new Visitor($config->visitor);

    if( $config->connection ) $this->connection = new Connection(
      array($connection->server, $connection->user, $connection->password, $connection->database), $connection->userClasses
    );

    if( $cookie->enable ) Session::cookie(
      $cookie->name, $cookie->lifetime, $cookie->path, $cookie->domain, $cookie->secure, $cookie->httponly
    );

    Session::$oneTimeKey = $session->oneTimeKey;
    if( $session->start ) $this->startSession();
    if( $this->visitor ) $this->visitor->relogin($this);

    if( $history->record ) $this->history->startRecording($history->sessionKey);

    if( $response->auto ) $this->respond($config->routes, $errors);
  }

  // instance creator
  final public static function plant( $config ){
    if( self::$forest ) return self::$forest;
    return self::$forest = new self($config);
  }

  private $serviceNS='\\';
  private $nativeServices = array(
    'register', 'login', 'logout'
  );

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

  public function startSession(){
    Session::start();
    if( $this->visitor ) $this->visitor->onsessionstart($this);
  }

  /*
   * respond to a request
   * {
   *  render: "<template name>"
   *  data: {
   *    source: "path.to.dataFactory",
   *    branch: "<branch to fetch (contains sql query)>",
   *    params: request | post | get | json | session | url,
   *    group: "<column to group by results>"
   *  }
   *  service: {
   *    name: "",
   *    arguments: {...}
   *  }
   *  redirect: <number to step back in history> | url
   * }
   * */
  final public function respond( $routes ) {
    if ( $this->response->isAlreadySent() ) return;

    $route = $this->route;
    if( !$route ) return $this->deadEnd(404);

    $route->findAWay($routes);

    if ( @$route->lock && @$this->visitor && !$this->visitor->unlock($route->lock) )
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

  public function view( $tpl, $extension = "html" ){
    return new View($this->template($tpl, $extension), $this);
  }
  public function template( $tpl, $extension = "html" ){
    $ext = $this->config->templates['extension'];
    $extension = $ext ? $ext : $extension;
    if( preg_match('/\.\w+$/', $tpl) ) return $this->templatesDir->readFile($tpl);
    else return $this->templatesDir->readFile($tpl.'.'.$extension);
  }
  public function errorDoc( $tpl, $extension = "html" ){
    $ext = $this->config->errors['extension'];
    $extension = $ext ? $ext : $extension;
    if( preg_match('/\.\w+$/', $tpl) ) return $this->errorDocs->readFile($tpl);
    else return $this->errorDocs->readFile($tpl.'.'.$extension);
  }

  /*
   * render a template with a template scope
   * */
  /*
   * respond to a request
   * {
   *  render: "<template name>"
   *  data: {
   *    source: "path.to.dataFactory",
   *    branch: "<branch to fetch (contains sql query)>",
   *    params: request | post | get | json | session | url,
   *    group: "<column to group by results>"
   *  }
   *  service: {
   *    name: "",
   *    arguments: {...}
   *  }
   *  redirect: <number to step back in history> | url
   * }
   * */
  public function render( $tpl, $templateScope=null ){
    if ( $template = $this->view($tpl) ){
      $render = $template->render($templateScope ? $templateScope : array(
        'url'   => $this->route->var
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

  /*
   * get request source by name
   * */
  private function getRequestSource( $source, $default ){
    switch ( $source ) {
      case 'request': return $this->request->data;
      case 'post':
        if ( $this->request->method == 'POST' ) return $this->request->post;
        return $this->deadEnd(404, null, 'Bad request');
      case 'get':
        if ( $this->request->method == 'GET' ) return $this->request->get;
        return $this->deadEnd(404, null, 'Bad request');
      case 'json':
        if ( $this->request->json ) return $this->request->json;
        return $this->deadEnd(404, null, 'Bad request');
      case 'session': return $_SESSION;
      case 'url': return $this->route->var;
      default: return $default;
    }
  }


  /*
   * returns a data factroy by path name (source)
   * sets the request parameters (params)
   * used by data server
   * */
  public function getFactory( &$requestOptions ){
    if( is_string($requestOptions['params']) ) {
      $requestOptions['params'] = $this->getRequestSource($requestOptions['params'], $this->route->var);
    }
    $factoryPath = strtr($requestOptions['source'], '.', '\\');
    try{
      $factory = new $factoryPath($this);
    }
    catch( \Exception $e ){
      if( $requestOptions['ignoreErrors'] ) return null;
      return $this->deadEnd(500, null, 'Invalid data request, class does not exists');
    }
    return $factory;
  }

  /*
   * uses a data factory to fetch data from the database
   * used by data server
   * */
  public function getData( $requestOptions ){
    $factory = $this->getFactory($requestOptions);
    if( !$factory ) return null;
    $dataSet = $factory->run($this->connection->connect(), $requestOptions);
    if( $factory->error && !$requestOptions['ignoreErrors'] ){
      return $this->deadEnd(500, null, 'Data request error '.$factory->error);
    }
    return $dataSet;
  }

  /*
   * serve data from database as json
   *  data: {
   *    source: "path.to.dataFactory",
   *    branch: "<branch to fetch (contains sql query)>",
   *    params: request | post | get | json | session | url,
   *    group: "<column to group by results>"
   *  }
   * */
  public function serveData( $dataRequest ){
    $dataSet = $this->getData($dataRequest);
    if( !$dataSet ) return null;
    $json = JSON::stringify($dataSet);
    if ( $this->route->responseType ){
      $this->response->setResponseType($this->route->responseType);
    }
    $this->response->contentType('application/json');
    $this->response->body($json);
    return true;
  }

  /*
   * built in services
   * */
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

  /*
   * run service with given arguments
   *  service: {
   *    name: "",
   *    arguments: {...} | <data request sources>
   *  }
   * */
  public function runService( $options ){
    if( is_string($options) ) {
      $name = $options;
    }
    else{
      $name = $options['name'];
      $arguments = $options['arguments'];
    }
    if( in_array($name, $this->nativeServices) ){
      $this->runNativeService($name);
      return null;
    }
    if ( preg_match('/^([\w+\.]+?)\.(\w+)$/', $name, $match) ){
      $class = $this->serviceNS.strtr($match[1], '.', '\\');
      $method = $match[2];
      $arguments = $this->getRequestSource($arguments, $this);
      if( !is_array($arguments) ) $arguments = array($arguments);
      if ( class_exists($class) ){
        if ( method_exists($class, $method) ){
          $obj = new \ReflectionMethod($class, $method);
          return $obj->invokeArgs(new $class($this), $arguments);
        }
      }
      else {
        $this->deadEnd(500, null, 'Request to undefined method '.$options);
      }
    }
    else {
      $this->deadEnd(500, null, 'Request to undefined method '.$options);
    }
  }

  /*
   * redirect: <number to step back in history> | url
   * */
  public function redirect( $url ){
    if ( $this->history ){
      if ( is_int($url) && $url = $this->history->pop($url) ){
        return $this->response->redirect($url);

      }
      else return $this->response->redirect($url);
    }
    else return $this->response->redirect($url ? $url : '/');
  }

  /*
   * download content
   * */
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
