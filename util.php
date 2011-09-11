<?php

function PhpFunctionalChatUtilityModule()
{
  $disable_browser_cache_IO = function () {
    header('Cache-Control: no-cache');
    header('Pragma: no-cache');
  };

  // encapsulates common "if (isset())" pattern
  $val = function(& $x, $default = null) {
    if (isset($x)) return $x;
    else return $default;
  };

  return compact(
    'disable_browser_cache_IO',
    'val'
  );

}

class Maybe
{
  private $thing;

  private function __construct($thing)
  {
    $this->thing = $thing;
  }

  function isNothing()
  {
    return null === $this->thing;
  }

  function fromJust()
  {
    if ($this->isNothing()) {
      trigger_error("fromJust called on Maybe::nothing.", E_ERROR);
    } else {
      return $this->thing;
    }
  }

  function map($function)
  {
    if ($this->isNothing) {
      return $this;
    } else {
      return self::just($function($this->thing));
    }
  }

  static function nothing()
  {
    return new Maybe(null);
  }

  static function just($thing)
  {
    return new Maybe($thing);
  }

	function __get($var)
	{
    trigger_error('Maybe has no properties', E_ERROR);
	}
}

class Either
{
	private $left;
	private $right;

	private $data;

	private function __construct() { }

  static function left($data)
  {
		$e        = new Either;
		$e->left  = true;
		$e->right = false;
		$e->data  = $data;

		return $e;
  }

  static function right($data)
  {
		$e        = new Either;
		$e->left  = false;
		$e->right = true;
		$e->data  = $data;

		return $e;
  }

	function isLeft()
	{
		return $this->left;
	}

	function isRight()
	{
		return $this->right;
	}

	function fromLeft()
	{
		if ($this->isRight()) {
      trigger_error("fromLeft called on Either::right.", E_ERROR);
		} else {
			return $this->data;
		}
	}

	function fromRight()
	{
		if ($this->isLeft()) {
      trigger_error("fromRight called on Either::right.", E_ERROR);
		} else {
			return $this->data;
		}
	}

	function __get($var)
	{
    trigger_error('Either has no properties', E_ERROR);
	}
}

