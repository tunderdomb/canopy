<?php
namespace canopy;

use canopy\request\Session;
use canopy\request\Cookie;

abstract class Visitor {

  protected $table;
  protected $userIdColumn;
  protected $loginIdColumn;
  protected $passwordColumn;
  protected $userClassColumn;
  protected $passKeysColumn;
  protected $fieldMap = array();

  protected $cookieExpires = 0;
  protected $loginAfterRegister = true;

  protected $passKeys = array();

  private $passwordHasher;

  function __construct( $table, $userIdColumn, $loginIdColumn, $passwordColumn,
                        $passKeysColumn, $userClassColumn, $fieldMap, $hashCost = 8 ) {
    $this->table = $table;
    $this->userIdColumn = $userIdColumn;
    $this->loginIdColumn = $loginIdColumn;
    $this->passwordColumn = $passwordColumn;
    $this->userClassColumn = $userClassColumn;
    $this->passKeysColumn = $passKeysColumn;
    $this->fieldMap = $fieldMap;
    $this->passwordHasher = new Password($hashCost);
  }

  private static $inputFields = array(
    "loginId" => "loginId",
    "loginPassword" => "loginPassword"
  );

  final static function setInputFields( $loginId, $loginPassword ){
    self::$inputFields["loginId"] = $loginId;
    self::$inputFields["loginPassword"] = $loginPassword;
  }

  final public function register( Forest $forest ) {
    $registerData = $forest->request->data;
    $loginIdField = self::$inputFields['loginId'];
    $loginPasswordField = self::$inputFields['loginPassword'];
    if ( !$registerData || count($registerData) < 2 || @!$registerData [$loginPasswordField] || @!$registerData[$loginIdField] ) {
      return $this->onregistererror($forest, 'Invalid request', $registerData);
    }
    $connection = $forest->gateway->connect();
    $sql = "select 1 from $this->table where $this->loginIdColumn = ?";
    $connection->bindParam(@$registerData[$loginIdField]);
    $result = $connection->query($sql);
    if ( $connection->error ) {
      return $this->onregistererror($forest, 'Failed request', $registerData);
    }
    if ( /*!empty($exists)*/ $result->length ) {
      return $this->onregisterexists($forest);
    }
    $values = array_intersect_key($registerData, $this->fieldMap);
    $columns = array_intersect_key($this->fieldMap, $values);
    $columns = implode(',', $columns);
    $bindMarks = trim(str_repeat(',?', count($values)), ',');
    $values[$loginPasswordField] = $this->passwordHasher->hash($values[$loginPasswordField]);
    $connection->bindParam($values);
    $sql = "insert into $this->table ($columns) values ($bindMarks);";
    $result = $connection->query($sql);
    if ( $connection->error ) {
      return $this->onregistererror($forest, 'Failed request', $registerData);
    }
    if ( $this->loginAfterRegister ) {
      return $this->login($forest);
    }
    $this->onregister($forest, $registerData);
  }

  final public function login( Forest $forest ) {
    $loginIdField = self::$inputFields['loginId'];
    $loginPasswordField = self::$inputFields['loginPassword'];
    $response = true;
    $loginData = $forest->request->data;
    if ( !$loginData || !$loginData[$loginPasswordField] || !$loginData[$loginIdField] ) {
      return $this->onloginerror($forest, 'Invalid request');
    }
    if ( !Session::get('isLoggedIn') ) {
      $connection = $forest->gateway->connect();
      $sql = "select * from $this->table where $this->loginIdColumn = ?";
      $connection->bindParam($loginData[$loginIdField]);
      $userData = $connection->query($sql);
      if ( $connection->error ) {
        return $this->onloginerror($forest, 'Failed request');
      }
      if ( empty($userData) ) {
        return $this->onloginfailed($forest, $userData, $loginData);
      }
      $userData = $userData[0];
      if ( $this->passwordHasher->verify(
      // entered password                stored hash
        $loginData[$loginPasswordField], $userData[$this->passwordColumn]
      )
      ) {
        Session::resume(Cookie::get('visitor'));
        Session::regenerate(true);
        Session::set('isLoggedIn', true);
      }
      else return $this->onloginfailed($forest, $userData, $loginData);
    }
    $this->onlogin($forest, $userData, $loginData);
  }

  final public function logout( Forest $forest ) {
    Session::removeCookie();
    Session::reset();
    $forest->gateway->createConnection();
    $this->onlogout($forest);
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
    if ( !count($this->passKeys) ) return true;
    $lock = preg_split('/\s+/', $lock);
    $intersection = array_intersect($lock, $this->passKeys);

    return count($lock) == count($intersection);
  }

  // implement these

  public function onregister( Forest $forest, $registerData ) {
    if ( $forest->request->raw ) {
      $forest->response->body(1);
    }
    else if ( $forest->request->post ) {
      $forest->redirect(1);
    }
  }

  public function onregistererror( Forest $forest, $errorMessage, $registerData ) {
    $forest->deadEnd(500, null, $errorMessage);
  }

  public function onregisterexists( Forest $forest ) {
    if ( $forest->request->raw ) {
      $forest->response->body(-1);
    }
    else if ( $forest->request->post ) {
      $forest->redirect(1);
    }
  }

  public function onlogin( Forest $forest, $userData, $loginData ) {
    Session::set('userId', $userData[$this->userIdColumn]);
    Session::set('userClass', $userData[$this->userClassColumn]);
    Session::set('passKeys', $userData[$this->passKeysColumn]);
    $loginData['rememberme'] && Session::setCookieExpire($this->cookieExpires);
    $forest->gateway->createConnection($userData[$this->userClassColumn]);
    if ( $forest->request->raw ) {
      $forest->response->body(1);
    }
    else if ( $forest->request->post ) {
      $forest->redirect(1);
    }
  }

  public function onloginerror( Forest $forest, $message ) {
    $forest->deadEnd(500, null, $message);
  }

  public function onloginfailed( Forest $forest ) {
    if ( $forest->request->raw ) {
      $forest->response->body(0);
    }
    if ( $forest->request->post ) {
      $forest->redirect(1);
    }
  }

  public function onlogout( Forest $forest ) {
  }

  public function onsessionstart( Forest $forest ){
    $this->addPassKeys(Session::get('passKeys'));
    if( $forest->gateway) $forest->gateway->createConnection(Session::get('userClass'));
  }

}
