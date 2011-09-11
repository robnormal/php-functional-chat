<?php

class RR_PHPUnit_TestCase extends PHPUnit_Framework_TestCase
{
	function hasAttribute ($attr, $class_or_obj, $msg = null)
	{
		if (is_object($class_or_obj)) {
			return $this->assertObjectHasAttribute($attr, $class_or_obj, $msg);
		} else {
			return $this->assertClassHasAttribute($attr, $class_or_obj, $msg);
		}
	}


  private static $aliased_methods = array();
  private static $alias_call_parent__call = false;

  static function aliasMethod($method, $alias)
  {
		if (method_exists(get_class(), $alias)) {
			trigger_error(get_class() . " already has a method called \"$alias\"", E_USER_ERROR);
		}

    if (empty(self::$aliased_methods[$alias])) {
      self::$alias_call_parent__call = method_exists(get_parent_class(), '__call');
    }

    self::$aliased_methods[$alias] = $method;
  }

  static function aliasMethods(array $methods)
  {
    foreach($methods as $method => $alias) {
      self::aliasMethod($method, $alias);
    }
  }

  function __call($func, $args)
  {
    if (isset(self::$aliased_methods[$func])) {
      $method = self::$aliased_methods[$func];
      return call_user_func_array(array($this, $method), $args);
    } else if (self::$alias_call_parent__call) {
      parent::__call($func, $args);
    } else {
      trigger_error('No such function: ' . get_class($this) . "::$func");
    }
  }
}

RR_PHPUnit_TestCase::aliasMethods(array(
  'assertArrayHasKey'                => 'hasKey',
  'assertArrayNotHasKey'             => 'notHasKey',
  'assertClassHasStaticAttribute'    => 'hasStaticAttribute',
  'assertClassNotHasStaticAttribute' => 'notHasStaticAttribute',
  'assertContains'                   => 'has',
  'assertContainsOnly'               => 'hasOnly',
  'assertEquals'                     => 'equals',
  'assertFalse'                      => 'not',
  'assertInstanceOf'                 => 'instanceOf',
  'assertInternalType'               => 'type',
  'assertRegExp'                     => 'regex',
  'assertSame'                       => 'same',
  'assertSelectCount'                => 'selectCount',
  'assertSelectEquals'               => 'selectEquals',
  'assertSelectRegExp'               => 'selectRegEx',
  'assertStringEndsWith'             => 'endsWith',
  'assertStringEqualsFile'           => 'fileEquals',
  'assertStringStartsWith'           => 'startsWith',
  'assertTag'                        => 'tag',
  'assertTrue'                       => 'ok',
));

