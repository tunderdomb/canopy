<?php
namespace canopy;

use canopy\request\Session;
use canopy\request\Cookie;

class Visitor extends DataFactory{

  protected $table;
  protected $userIdColumn;
  protected $loginIdColumn;
  protected $passwordColumn;
  protected $userClassColumn;
  protected $passKeysColumn;

  /*
   * use this to alias database columns
   * so you don't have to use column names in html input field names
   * map:
   * alias -> real column name
   * */
  protected $fieldMap = array();
  protected $cookieSettings;

  protected $rememberField;
  protected $sessionUserData;
  protected $loginAfterRegister = true;

  protected $passKeys = array();

  private $passwordHasher;

  private $active = false;

  function __construct( $options ) {
    $this->active = is_array($options) && !empty($options);
    if( !$this->active ) return;
    $this->table = $options['table'];
    $this->userIdColumn = $options['userId'];
    $this->loginIdColumn = $options['loginId'];
    $this->passwordColumn = $options['password'];
    $this->rememberField = $options['rememberField'];
    $this->userClassColumn = $options['userClass'];
    $this->sessionUserData = $options['sessionUserData'];
    $this->passKeysColumn = $options['passKeys'];
    $this->cookieSettings = $options['cookie'];
    $this->fieldMap = $options['fieldMap'];
    $this->passwordHasher = new Password($options['hashCost']);
  }

  final public function register( Canopy $canopy ) {
    $registerData = $canopy->request->data;
    if(  !$registerData || count($registerData) < 2 || !$this->validate($registerData) ) return null;

    $loginId = $registerData[$this->loginIdColumn];
    $loginIdColumn = $this->fieldMap[$this->loginIdColumn]['column'];
    $loginPassword = $registerData[$this->passwordColumn];
    $connection = $canopy->connection->connect();
    $sql = "select 1 from $this->table where $loginIdColumn = ?";
    $connection->bindParam($loginId);
    $result = $connection->query($sql);
    if ( $connection->error ) {
      return $this->onregistererror($canopy, 'Failed request', $registerData);
    }
    if ( $result->length ) {
      return $this->onregisterexists($canopy);
    }

    $registerData[$this->passwordColumn] = $this->passwordHasher->hash($loginPassword);
    $this->push($connection, $registerData);

    // whitelist posted data according to the field map
    /*$values = array_intersect_key($registerData, $this->fieldMap);
    //
    $columns = array_intersect_key($this->fieldMap, $values);
    $columns = implode(',', $columns);
    $bindMarks = trim(str_repeat(',?', count($values)), ',');
    $values[$loginPasswordField] = $this->passwordHasher->hash($values[$loginPasswordField]);
    $connection->bindParam($values);
    $sql = "insert into $this->table ($columns) values ($bindMarks);";
    $result = $connection->query($sql);
    if ( $connection->error ) {
      return $this->onregistererror($canopy, 'Failed request', $registerData);
    }*/
    if ( $this->loginAfterRegister ) {
      return $this->login($canopy);
    }
    $this->onregister($canopy, $registerData);
  }

  final public function relogin( Canopy $canopy ){
    if ( !Session::get('isLoggedIn') ) {
      $sessionId = Cookie::get($this->cookieSettings['name']);
      if( $sessionId ) {
        Session::resume($sessionId);
        Session::set('isLoggedIn', true);
        $this->login($canopy);
      }
    }
  }

  final public function login( Canopy $canopy ) {
    if ( !Session::get('isLoggedIn') ) {
      $loginData = $canopy->request->data;
      if( !$loginData || !$this->validate($loginData) ) return null;

      $loginIdColumn = $this->resolveAlias($this->loginIdColumn);
      $passwordColumn = $this->resolveAlias($this->passwordColumn);

      $connection = $canopy->connection->connect();
      $sql = "select * from $this->table where $loginIdColumn = ?";
      $connection->bindParam($loginData[$this->loginIdColumn]);
      $userData = $connection->query($sql);
      if ( $connection->error ) {
        return $this->onloginerror($canopy, 'Failed request');
      }
      if ( empty($userData) ) {
        return $this->onloginfailed($canopy, $userData, $loginData);
      }
      $userData = $userData[0];

      // check entered password against stored hash
      if ( $this->passwordHasher->verify($loginData[$this->passwordColumn], $userData[$passwordColumn]) ){

        // resume or restart session
        $sessionId = Cookie::get($this->cookieSettings['name']);
        if( $sessionId ) Session::resume($sessionId);
        else {
          Session::regenerate(true);
          Cookie::set($this->cookieSettings['name'], Session::id());
        }
        Session::set('isLoggedIn', true);

        // set passkeys
        if( $userData[$this->passKeysColumn] ) {
          $this->passKeys = $userData[$this->passKeysColumn] = explode(',', $userData[$this->passKeysColumn]);
        }

        // store user data in session
        if( $this->sessionUserData ) Session::set($this->sessionUserData, $userData);

        // set cookie
        $loginData[$this->rememberField] && Session::setCookieExpire($this->cookieSettings['expire']);

        // reset connection
        $canopy->connection->setUser($userData[$this->resolveAlias($this->userClassColumn)]);
      }
//      else return $this->onloginfailed($canopy, $userData, $loginData);
    }
    else{
      $userData = Session::get($this->sessionUserData);
      if( !$userData ) return;
      if( $this->passKeysColumn ){
        if( is_string($userData[$this->passKeysColumn]) ) $this->passKeys = explode(',', $userData[$this->passKeysColumn]);
        else if( is_array($userData[$this->passKeysColumn]) ) $this->passKeys = $userData[$this->passKeysColumn];
      }
      // reset connection
      $canopy->connection->setUser($userData[$this->resolveAlias($this->userClassColumn)]);
    }
//    $this->onlogin($canopy, $userData, $loginData);
  }

  final public function logout( Canopy $canopy ) {
    Session::removeCookie();
    Session::reset();
    $canopy->connection->createConnection();
    $this->onlogout($canopy);
  }

  final public function addPassKeys( $key ) {
    if ( is_string($key) ) {
      $this->passKeys[] = $key;
    }
    else if ( is_array($key) ) {
      $this->passKeys = array_merge($key, $this->passKeys);
    }
  }

  final public function unlock( $lock ) {
    if ( !count($this->passKeys) ) return false;
    $intersection = array_intersect($lock, $this->passKeys);

    return count($lock) == count($intersection);
  }

  // implement these

  public function onregister( Canopy $canopy, $registerData ) {
    /*if ( $canopy->request->raw ) {
      $canopy->response->body(1);
    }
    else if ( $canopy->request->post ) {
      $canopy->redirect(1);
    }*/
  }

  public function onregistererror( Canopy $canopy, $errorMessage, $registerData ) {
    $canopy->deadEnd(500, null, $errorMessage);
  }

  public function onregisterexists( Canopy $canopy ) {
    if ( $canopy->request->raw ) {
      $canopy->response->body(-1);
    }
    else if ( $canopy->request->post ) {
      $canopy->redirect(1);
    }
  }

  public function onlogin( Canopy $canopy, $userData, $loginData ) {
    Session::set('userId', $userData[$this->userIdColumn]);
    Session::set('userClass', $userData[$this->userClassColumn]);
    Session::set('passKeys', $userData[$this->passKeysColumn]);
    $canopy->connection->createConnection($userData[$this->userClassColumn]);
    if ( $canopy->request->raw ) {
      $canopy->response->body(1);
    }
    else if ( $canopy->request->post ) {
      $canopy->redirect(1);
    }
  }

  public function onloginerror( Canopy $canopy, $message ) {
    $canopy->deadEnd(500, null, $message);
  }

  public function onloginfailed( Canopy $canopy ) {
    if ( $canopy->request->raw ) {
      $canopy->response->body(0);
    }
    if ( $canopy->request->post ) {
      $canopy->redirect(1);
    }
  }

  public function onlogout( Canopy $canopy ) {
  }

  public function onsessionstart( Canopy $canopy ){
    $this->addPassKeys(Session::get('passKeys'));
    if( $canopy->connection) $canopy->connection->createConnection(Session::get('userClass'));
  }

}
