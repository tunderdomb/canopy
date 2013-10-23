<?php
namespace canopy\parse;

use canopy\util\JSON;

class Template {
  public $template
  , $error;

  public static $throwExceptions;

  const COMMENT_TAG = 'comment';

  protected $xp = '/{{\s*(\/)?\s*((\w+)\s*([^{}]*)|[^{}]+)\s*}}/'
  , $scope
  , $interpreter
  , $blockParsers = array(
    'for' => 'parseFor',
    'if'  => 'parseIf',
    'comment' => 'replaceComment'
  )
  , $inlineParsers = array(
    'var' => 'parseVar',
    'json' => 'insertJSON'
  )
  , $nativeParsers = array(
    'urlencode'
  );

  public function __construct( $template, Scope $globalTemplateScope ) {
    $this->template = $template;
    $this->scope = $globalTemplateScope;
    $this->interpreter = new Interpret($globalTemplateScope);
    $this->root = array();
  }

  public function render( $scope = array() ) {
    $scope && $this->scope->registerGlobals($scope);
    $template = $this->parse($this->template);
    $this->insertSkips($template);
    $this->insertComments($template);
    return $template;
  }

  /*private function renderBlock( $block ){
    if ( $block['block'] ){
      $renderer = $block['type'];
      $yepOrNope = false;
      $rendered = $this->$renderer($block['inner'], $yepOrNope);
      if ( $yepOrNope ){
        $childblocks = $block['yepChildren'];

      }
      else{
        $childblocks = $block['nopeChildren'];
      }
      for( $i=-1, $l = count($childblocks); ++$i<$l; ){

      }
      substr_replace($block['inner'], $rendered, $block['start'], $block['length']);
    }
    else {

    }
  }*/

  protected function findTag( $template, &$tagStart, &$tagEnd, &$tag = null, &$isClosing = null, &$tagName = null, &$tagContent = null,  &$arguments = null ) {
    if ( preg_match($this->xp, $template, $match, PREG_OFFSET_CAPTURE, $tagStart) ) {
      @list($tag, $isClosing, $tagContent, $tagName, $arguments) = $match;
      $tag = $tag[0];
      $isClosing = (bool)$isClosing[0];
      $tagContent = $tagContent[0];
      $tagName = $tagName[0];
      $arguments = $arguments[0];

      $tagStart = $match[0][1]; // new cursor, offset of the end of tag {}<-
      $tagEnd = $tagStart+strlen($tag);
      return true;
    }
  }

  protected function findElse( $blockInner, &$yep, &$nope ) {
    $nope = '';
    $cursor = 0;
    $foreignOpenings = 0;

    while( $this->findTag($blockInner, $tagStart, $tagEnd, $tag, $isClosing, $tagName) ){
      if ( $tagName == 'else' && $foreignOpenings == 0){
        // found our else
        $yep = substr($blockInner, 0, $tagStart);
        $nope = substr($blockInner, $tagEnd);
        return;
      }
      else if ( @$this->blockParsers[$tagName] ){
        if ( $isClosing ) {
          --$foreignOpenings;
        }
        else {
          ++$foreignOpenings;
        }
      }
      $tagStart = $tagEnd;
    }
    $yep = $blockInner;
  }

  protected function findVariable( $variableExpression ){
    return $this->scope->search($this->interpreter->evaluate($variableExpression));
  }


  protected function parse( $template/*, &$parentBlock=null*/ ) {
    $tagStart = 0;

    while ( !$this->error
      && $this->findTag( $template, $tagStart, $innerStart
        , $openingTag, $isClosing, $openingTagName, $tagContent, $arguments) ) {

      // ->{}
      // {}<-
      $tagEnd = $innerStart;

      // {block ..}
      if ( @$blockParser = $this->blockParsers[$openingTagName] ) {

        $openings = 1;
        $closings = 0;
        do{
          $innerEnd = $tagEnd;
          $found = $this->findTag($template, $innerEnd, $tagEnd, $closingTag, $isClosing, $closingTagName);
          $openingTagName == $closingTagName && ($isClosing && ++$closings || ++$openings);
        }
        while ( $openings != $closings && $found );

        // skip comment blocks
        if( $openingTagName == 'comment' ){
          $tagStart = $tagEnd;
          continue;
        }

        if ( $openings !== $closings ) return $this->parseError('Syntax Error: Imbalanced block in ' . $openingTag);

        // {}...{}
        $inner = substr($template, $innerStart, $innerEnd - $innerStart);
        // {}...{else}...{}
        $this->findElse($inner, $yep, $nope);

        // ...
        $replace = $this->$blockParser($arguments, $yep, $nope);
        if ( $this->error ) return;
        $template = substr_replace($template, $replace, $tagStart, $tagEnd - $tagStart);
      }
      // inline tag
      else {
        // skip bootstraps
        if( $openingTagName == 'skip' ){
          $tagStart = $innerStart;
          continue;
        }
        // inline parser
        if( $openingTagName && $inlineParser = $this->inlineParsers[$openingTagName] ){
          $skipInsert = true;
          $replace = $this->$inlineParser($arguments, $skipInsert);
          $replace = $this->forceString($replace);
          $template = substr_replace($template, $replace, $tagStart, $tagEnd - $tagStart);
          $tagStart = $skipInsert ? $tagStart + strlen($replace) : $tagStart;
        }
        // native parser
        else if( in_array($openingTagName, $this->nativeParsers) ){
          $replace = call_user_func_array($openingTagName, $this->parseNativeArguments($arguments));
          $template = substr_replace($template, $replace, $tagStart, $tagEnd - $tagStart);
          $tagStart += strlen($replace);
        }
        // expression
        else {
          $replace = $this->interpreter->evaluate($tagContent);
          $replace = $this->forceString($replace);
          $template = substr_replace($template, $replace, $tagStart, $tagEnd - $tagStart);
          $tagStart += strlen($replace);
        }
      }
    }
    return $template;
  }

  private function parseNativeArguments( $arguments ){
    $args = explode(',', $arguments);
    foreach ( $args as $i => $arg ) {
      $args[$i] = $this->interpreter->evaluate($arg);
    }
    return $args;
  }

  protected $skips = array();
  protected $skipCount = -1;

  protected function insertSkips( &$template ){
    while( preg_match('/{{skip (\d+)}}/', $template, $match, PREG_OFFSET_CAPTURE) ){
      $template = substr_replace($template, $this->skips[$match[1][0]], $match[0][1], strlen($match[0][0]));
    }
    unset($this->skips);
  }
  protected function insertComments( &$template ){
    while( preg_match('/{{comment}}([\w\W]*?){{\/comment}}/m', $template, $match, PREG_OFFSET_CAPTURE) ){
      $template = substr_replace($template, $match[1][0], $match[0][1], strlen($match[0][0]));
    }
  }

  protected function skip( $value ){
    $this->skips[++$this->skipCount] = $value;
    return '{{skip '.$this->skipCount.'}}';
  }

  protected function insertJSON( $arguments ){
    return $this->skip($this->forceString($this->interpreter->evaluate($arguments)));
  }

  protected function forceString( $obj ){
    if ( is_array($obj) || is_object($obj) ){
      return json_encode($obj);
    }
    else {
      return ''.$obj;
    }
  }

  /**
   * syntax: {for int | array [, ith [, i]]}
   *
   * int: loop block int times
   *
   * or
   *
   * array: a variable available in the template scope, pointing to an array
   * ith: optional, will be available as a named variable in the loop block
   * containing the current value in each iteration
   * i: optional, will be available as a named variable in the loop block
   * the index of each iteration
   *
   * @param $arguments
   * @param $yep
   * @param $nope
   * @return null|string
   */
  protected function parseFor( $arguments, $yep, $nope ){
    if ( preg_match('/^\s*(?:(\d+)|([^,\s]+?)(?:\s*,\s*([a-zA-Z]?\w*))?(?:\s*,\s*([a-zA-Z]\w*))?)\s*$/', $arguments, $match) ){
      @list(, $int, $array, $ith, $i) = $match;
      $parsed = '';
      if ( $array ){
        $array = $this->interpreter->evaluate($array);
        if ( is_array($array) || is_object($array) ){
          if ( empty($array) )
            return $nope ? $this->parse($nope) : '';
          // {for ..}
          $this->scope->open();
          if ( $ith && $i ) foreach( $array as $key => $value ){
            $this->scope->set($ith, $value);
            $this->scope->set($i, $key);
            $parsed .= $this->parse($yep);
          }
          else if ( $ith ) foreach( $array as $value ){
            $this->scope->set($ith, $value);
            $parsed .= $this->parse($yep);
          }
          else if ( $i ) foreach( $array as $key => $value ){
            $this->scope->set($i, $key);
            $parsed .= $this->parse($yep);
          }
          $this->scope->close();
          // {/for}
          return $parsed;
        }
        else return $nope ? $this->parse($nope) : '';
      }
      else {
        if ( !$int )
          return $nope ? $this->parse($nope) : '';
        $int = (int) $int;
        for ($i=-1; ++$i<$int; ){
          $parsed .= $this->parse($yep);
        }
        return $parsed;
      }
    }
    return $this->parseError('Type Error: Invalid array arguments in '.$arguments);
  }

  protected function replaceComment( $arguments, $yep ){
    return $yep;
  }

  protected function parseIf( $arguments, $yep, $nope ){
    if ( $this->interpreter->evaluate($arguments) ){
      return $this->parse($yep);
    }
    return $nope ? $this->parse($nope) : '';
  }
  private function parseVar( $arguments ){
    $this->interpreter->evaluate($arguments);
  }

  protected function parseError( $message ) {
    if ( self::$throwExceptions ) throw new ParseError($message);
    else $this->error = $message;

    return null;
  }
}
