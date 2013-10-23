<?php
namespace canopy\parse;


  // states are changed after reading a token from input
// when checking a state, it means the type of token the parser last came across
const STATE_ZERO = 0
, STATE_PRIMITIVE = 1
, STATE_VAR = 2
, STATE_DOT = 3
, STATE_UNARY = 4
;

const TOKEN_BINARY = 0
, TOKEN_UNARY = 1
, TOKEN_IDENTIF = 2
, TOKEN_BOOL = 3
, TOKEN_ASSIGNMENT = 4
, TOKEN_STRING = 5
, TOKEN_NUM = 6
;

const undefined = null;

class Interpret {

  // TODO: make error when there's assignment in middle expression
  private $precedence = array(

    '*=' => 8, '/=' => 8, '%=' => 8, '+=' => 8, '-=' => 8,
    '&=' => 8, '|=' => 8,

    '++' => 6, '--' => 6, '!'  => 6, '-u'  => 6, '+u'  => 6,
    '^'  => 5,
    '*'  => 4, '/' => 4, '%' => 4,
    '+'  => 3, '-' => 3,

    '==' => 2, '!=' => 2, '<=' => 2, '>=' => 2, '<' => 2, '>' => 2,

    '&&' => 1,
    '||' => 0,

    '.'  => -1, '['  => -1, ']'  => -1,

    '='  => -1
  );

  private function comparePrecedence( $operator, $topOfStack ) {
    $one = $this->precedence[$operator];
    $two = $this->precedence[$topOfStack];

    return $one > $two ? 1 : ($one < $two ? -1 : 0);
  }

  private function isUnary( $operator ) {
    switch ( $operator ) {
      case '!' :
      case '++':
      case '--':
      case '+u':
      case '-u':
        return true;
    }
  }

  private function isLeftAssoc( $operator ) {
    return $operator != '^' && @$operator[2] != '=';
  }

  private function isAssignment( $operator ) {
    return @$this->precedence[$operator] == 8;
  }

  // TODO: solve minus sign unary operator

  private function parseBinary( $offset ){
    $input = substr($this->input, $offset);
    if ( preg_match('/^\s*(\=\=|\=|\&\&|\|\||[\!\+\*\-\/\^\%\<\>]\=?|\.|\[|\])/', $input, $match) ) {
      $operator = $match[1];

      // [
      if ( $operator == '[' ){
        $this->outputQ []= $this->stack []= new Token($operator);
//        $this->parseState = STATE_BRACKET;
        $this->parseState = STATE_ZERO;
      }

      // .
      else if ( $operator == '.' ){
        $this->stack []= new Token($operator, TOKEN_BINARY);
//        $this->parseState = STATE_DOTACCESS;
        $this->parseState = STATE_DOT;
      }

      // ]
      else if( $operator == ']' ){
        if ( empty($this->stack) )
          return $this->evalError('Syntax Error: Mismatched brackets', $offset);
        $top = array_pop($this->stack);
        while( $top->value != '[' ){
          $this->outputQ [] = $top;
          if ( empty($this->stack) )
            return $this->evalError('Syntax Error: Mismatched brackets', $offset);
          $top = array_pop($this->stack);
        }
        $this->outputQ []= new Token(']');
//        $this->parseState = STATE_IDENTIFIER;
        $this->parseState = STATE_VAR;
      }

      // =
      else if ( $operator == '=' ){
        if ( $this->parseState != STATE_VAR ) return $this->evalError('Syntax Error: Assign to expression');
//        $this->assignStack [] = $this->outputQ;
//        $this->outputQ = array();
        $this->stack []= new Token('=', TOKEN_BINARY);
        $this->outputQ []= new Token('', TOKEN_ASSIGNMENT);
        $this->parseState = STATE_ZERO;
      }

      // ...
      else {
        $top = $this->stackTop();
        while (
          !empty($this->stack)
          && $top->value !== '('
          && (
            $this->isLeftAssoc($operator) && $this->comparePrecedence($operator, $top->value) != 1 // op <= top
            || $this->comparePrecedence($operator, $top->value) == -1 // op < top
          )
        ) {
          $this->outputQ [] = array_pop($this->stack); // A B C * +
          $top = $this->stackTop();
        }
        $this->stack []= new Token($operator, TOKEN_BINARY);

        $this->parseState = STATE_ZERO;
      }

      return strlen($match[0]);
    }
    return 0;
  }


  private function parseUnary( $offset ){
    $input = substr($this->input, $offset);
    if ( preg_match('/^\s*(\+\+|\-\-|\!|\+|\-)/', $input, $match) ) {
      $operator = $match[1];
      if( $operator == '-' || $operator == '+' ) $operator .= 'u';
      $this->stack []= new Token($operator, TOKEN_UNARY);
      $this->parseState = STATE_UNARY;

      return strlen($match[0]);
    }
    return 0;
  }

  private function parseParen( $offset ) {
    $input = substr($this->input, $offset);
    if ( preg_match('/^\s*(\(|\()/', $input, $match) ) {
      $paren = $match[1];
      if ( $paren == '(' ) {
        $this->stack []= new Token('(');
      }
      else {
        if ( empty($this->stack) )
          return $this->evalError('Syntax Error: Mismatched parentheses', $offset);

        $pop = array_pop($this->stack);
        while ( $pop->value !== '(' ) {
          $this->outputQ []= $pop;
          if ( empty($this->stack) )
            return $this->evalError('Syntax Error: Mismatched parentheses', $offset);
          $pop = array_pop($this->stack);
        }
      }

//      $this->parseState = STATE_PAREN;
      $this->parseState = STATE_ZERO;
      return strlen($match[0]);
    }
    return 0;
  }

  private function parsePrimitive( $offset ){
    $input = substr($this->input, $offset);
    if ( preg_match('/^\s*(?:(\"|\')((?:\\\\"|.)*?)\\1|(\d+(?:[\.\,](\d+))?)|(true|false))/', $input, $match) ) {
      if( isset($match[5]) ){
        $bool = $match[5];
        $this->outputQ []= new Token($bool == 'true', TOKEN_BOOL);
      }
      else if( isset($match[3]) ){
        $num = $match[3];
        $num = @$match[4] ? (float)$num : (int)$num;
        $this->outputQ []= new Token($num, TOKEN_NUM);
      }
      else if ( isset($match[2]) ){
        $string = $match[2];
        $this->outputQ []= new Token($string, TOKEN_STRING);
      }
      $this->parseState = STATE_PRIMITIVE;
      return strlen($match[0]);
    }
  }


  private function parseIdentifier( $offset ) {
    $input = substr($this->input, $offset);
    if ( preg_match('/^\s*([a-zA-Z\_]\w*)/', $input, $match) ) {
      $identif = $match[1];
      $this->outputQ []= new Token($identif, TOKEN_IDENTIF);
      // asd.asd -> asd asd .
      if ( !empty($this->stack) && $this->stackTop()->value == '.' ) $this->outputQ []= array_pop($this->stack);
      $this->parseState = STATE_VAR;

      return strlen($match[0]);
    }
    return 0;
  }

  private function parse( $offset, $length ) {
    while (  !$this->error && $offset < $length ) {
      $consumed = 0;
      switch( $this->parseState ){
        // S -> ( | number | identifier | ..
        // binary -> paren | num | identif | string | bool | unary
//        case STATE_FIRST_READ :
//        case STATE_BRACKET :
//        case STATE_PAREN :
//        case STATE_BINARY :
//        case STATE_ASSIGN :
        case STATE_ZERO :
          if ( $consumed = $this->parseParen($offset) );
          else if ( $consumed = $this->parseUnary($offset) );
          else if ( $consumed = $this->parsePrimitive($offset) );
//          else if ( $consumed = $this->parseNum($offset) );
//          else if ( $consumed = $this->parseString($offset) );
          else if ( $consumed = $this->parseIdentifier($offset) );
          else return $this->evalError('Parse Error: illegal token', $offset);
          break;

        // string | bool | num -> binary
        // identifier -> postinc | binary | bracket | dotaccess
//        case STATE_STRING :
//        case STATE_NUM :
//        case STATE_IDENTIFIER :
//        case STATE_BOOL :
        case STATE_PRIMITIVE :
        case STATE_VAR :
          if ( $consumed = $this->parseBinary($offset) );
          else return $this->evalError('Parse Error: illegal token', $offset);
          break;

        // dot access -> identifier
        case STATE_DOT :
          if ( $consumed = $this->parseIdentifier($offset) );
          else return $this->evalError('Parse Error: illegal token', $offset);
          break;

        // unary -> paren | num | identif | bool | string
        case STATE_UNARY :
          if ( $consumed= $this->parseParen($offset) );
          else if ( $consumed = $this->parsePrimitive($offset) );
//          else if ( $consumed= $this->parseNum($offset) );
          else if ( $consumed= $this->parseIdentifier($offset) );
//          else if ( $consumed= $this->parseString($offset) );
          else return $this->evalError('Parse Error: illegal token', $offset);
          break;

        default :
          if ( !preg_match('/\s*$/', $this->input, null, 0, $offset) ){
            return $this->error
              ? false
              : $this->evalError('Syntax Error: Unexpected token', $offset);
          }
          else return true;
      }
      $offset += $consumed;
    }
    return true;
  }


  private function stackTop() {
    $length = count($this->stack);
    if ( $length ) return $this->stack[$length - 1];
  }

  private function compare( $a, $b ) {
    if ( is_string($a) && (is_int($b) || is_float($b)) ) {
      return preg_match('/^\d*(\.\d+)?$/', $a)
        ? ($a < $b ? -1 : ($a > $b ? 1 : 0))
        : NAN;
    }
    else if ( is_string($b) && (is_int($a) || is_float($a)) ) {
      return preg_match('/^\d*(\.\d+)?$/', $b)
        ? ($a < $b ? -1 : ($a > $b ? 1 : 0))
        : NAN;
    }
    else return $a < $b
      ? -1
      : ($a > $b ? 1 : 0);
  }

  private function &evalBinary( &$a, $operator, $b ){
    $equals = undefined;
    switch( $operator ){
      case '=' :
//        if ( $a === undefined ) {
//          $this->evalError('Parse Error: Can\'t assign '.$b.' to undefined');
//          return $equals;
//        }
        if ( $a[0] == '=' ){
          $this->scope->set(substr($a, 1), $b);
        }
        $a = $b;
        return $a;
      case '^=': $equals = $a ^=  $b;
        break;
      case '+=': $equals = $a += $b;
        break;
      case '-=': $equals = $a -= $b;
        break;
      case '*=': $equals = $a *= $b;
        break;
      case '/=': $equals = $a /= $b;
        break;
      case '%=': $equals = $a %= $b;
        break;
      case '&=': $equals = $a &= $b;
        break;
      case '|=': $equals = $a |= $b;
        break;

      case '^' : $equals = pow($a, $b);
        break;
      case '+' : $equals = is_string($a) || is_string($b) ? $a . $b : $a + $b;
        break;
      case '-' : $equals = $a - $b;
        break;
      case '*' : $equals = $a * $b;
        break;
      case '/' : $equals = $a / $b;
        break;
      case '%' : $equals = $a % $b;
        break;

      case '==': $equals = $this->compare($a, $b) === 0;
        break;
      case '!=': $equals = $this->compare($a, $b) !== 0;
        break;
      case '<=': $equals = $this->compare($a, $b) != 1;
        break;
      case '>=': $equals = $this->compare($a, $b) != -1;
        break;
      case '>' : $equals = $this->compare($a, $b) == 1;
        break;
      case '<' : $equals = $this->compare($a, $b) == -1;
        break;

      case '&&': $equals = $a && $b ? $b : false;
        break;
      case '||': $equals = $a ? $a : ($b ? $b : false);
        break;

      case '[' :
      case ']' :
      case '.' :
      if ( $a == null || is_string($a) && $a[0] == '=' ) {
        $this->evalError('Parse Error: Can\'t find field '.$b.' of undefined');
        return $equals;
      }
      if( is_string($a) && !is_numeric($b) ){
        $this->evalError('Parse Error: Illegal string offset');
        return $equals;
      }
      if( is_array($a) && array_key_exists($b, $a) ){
        $equals = &$a[$b];
      }
      else if( is_object($a) && property_exists($a, $b) ){
        $equals = &$a->$b;
      }
    }
    return $equals;
  }
  private function evalUnary( &$left, $operator ){
    switch( $operator ){
      case '++': return ++$left;
      case '--': return --$left;
      case '!' : return !$left;
      case '+u' : return 0+$left;
      case '-u' : return 0-$left;
    }
  }
  private function evalPostInc( &$left, $operator ){
    switch( $operator ){
      case '++': return $left++;
      case '--': return $left--;
    }
  }

  private function evalQ() {
    $expression = array();
    $e = -1;

    $state = null;
    $newIdentifier = true;

    while ( !$this->error && !empty($this->outputQ) ) {
      $token = array_shift($this->outputQ);
      $type = $token->type;
      $token = $token->value;

      if ( $type == TOKEN_BOOL || $type == TOKEN_NUM || $type == TOKEN_STRING ){
        $expression[++$e] = $token;
      }
      else if( $token == '[' || $type == TOKEN_ASSIGNMENT  ){
        $newIdentifier = true;
      }
      else if ( !array_key_exists($token, $this->precedence) ) {
        if ( $newIdentifier ){
          $newIdentifier = false;
          $expression[++$e] = &$this->scope->search($token, $found);
          if ( !$found )
            $expression[$e] = '='.$token;
//            return $this->evalError('Undefined variable: '.$token);
        }
        else $expression[++$e] = $token;
      }
      else{
        $operator = $token;
        $newIdentifier = $operator == ']';

        if ( $type == TOKEN_UNARY ){
          $left = $expression[$e--];
          if ( $left && is_string($left) && $left[0] == '=' ) $left = null;
          unset($expression[++$e]);
          $expression[$e] = $this->evalUnary($left, $operator);
          unset($left);
        }
        else {
          $opright = $expression[$e--];
          $opleft = &$expression[$e--];
          array_pop($expression);
          array_pop($expression);
//          $equals = $this->evalBinary($expression[$e--], $operator, $right);
          $expression[++$e] = &$this->evalBinary($opleft, $operator, $opright);
          $newIdentifier = $operator != '.' && $operator != '[' && $operator != ']';
          unset($opright);
          unset($opleft);
          unset($operator);
        }
      }
    }
    $result = array_pop($expression);
    if ( is_string($result) && $result[0] == '=' ) return null;
    return $result;
  }

  private function finishQ() {
    while ( !empty($this->stack) ) {
      $this->outputQ []= array_pop($this->stack);
    }
  }

  private $input
  , $assignStack = array()
  , $outputQ = array()
  , $stack = array()
  , $parseState
  ;
  private $scope;

  public $error
  , $errorOffset
  , $throwExceptions = false
  ;


  public function __construct( Scope $scope ) {
    $this->scope = $scope;
  }

  public function evaluate( $input ) {
    $this->error = '';
    $this->parseState = STATE_ZERO;
    $this->input = $input;
    $this->parse(0, strlen($this->input));
    $this->finishQ();
    $value = $this->evalQ();

    return $value;
  }

  // make an errorOffset field and make evalError accept an additional argument
  // so it can be substringed out what part of the input was wrong
  private function evalError( $message, $offset=null ) {
    if ( $this->throwExceptions ) throw new ParseError($message);
    $this->error = $message;
    if ( $offset !== null ) $this->errorOffset = $offset;
    return null;
  }
}