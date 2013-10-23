<?php
namespace canopy\request;
 
class Mail {
  public $from;
  public $to;
  public $subject;
  public $message;
  public $cc;
  public $bcc;
  public $replyTo;

  public $contentType = 'text/plain';
  public $charset = 'UTF-8';
  public $MIMEVersion;

  public $headers;
  public $error = null;

  public function __construct() {
  }

  public function validate(){
    return true;
  }
  protected function prepare(){
    $this->message = wordwrap($this->message, 70, "\r\n");
    $this->headers =  implode("\r\n", array(
      'MIME-Version: 1.0'
    , 'Content-Type: text/html; charset="'.$this->charset.'";'
//    , 'Content-Transfer-Encoding: 7bit'
//    , 'Date: ' . date('r', $_SERVER['REQUEST_TIME'])
//    , 'Message-ID: <' . $_SERVER['REQUEST_TIME'] . md5($_SERVER['REQUEST_TIME']) . '@' . $_SERVER['SERVER_NAME'] . '>'
    , 'From: ' . $this->from
    , 'Reply-To: ' . $this->from
    , 'Return-Path: ' . $this->from
//    , 'X-Mailer: PHP v' . phpversion()
//    , 'X-Originating-IP: ' . $_SERVER['SERVER_ADDR']
    ));
  }
  public function send(){
    $this->prepare();
    mail($this->to, $this->subject, $this->message, $this->headers);
  }
}
