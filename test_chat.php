<?php

require_once('RR_PHPUnit_TestCase.class.php');
require_once('chat.php');

class MyChatRequest extends FunctionalChatRequest
{
	/**
	 * @return Either([String]) Validated and possibly modified request data
	 */
	static function validate(array $params)
	{
		return validateFromList(array('chatter', 'room', 'message'), $params);
	}

	static function fillFromValidPost(array $params)
	{
		return new MyChatRequest(
      $params['chatter'],
      $params['room'],
      $params['message']
		);
	}
}

class PhpFunctionalChatTest extends RR_PHPUnit_TestCase
{
  function setUp()
  {
		$this->postModule = FunctionalChatPostModule::standard();

    $this->settings = new FunctionalChatSettings(
			$this->postModule,
      __DIR__.'/test_chat.json',
      __DIR__.'/test_chat.lock',
      10
    );

		$this->params1 = array(
			'room'    => 'myplace',
			'chatter' => 'Joe',
			'message' => 'Test message'
		);

    $this->now = $_SERVER['REQUEST_TIME'];
		$this->post1 = new FunctionalChatPost(
			'Ramona',
			'myroom',
			'Hello',
			$this->now
		);
		$this->chat = new PhpFunctionalChat();

		$request1_e = MyChatRequest::fromPost($this->params1);
		if ($request1_e->isRight()) {
			$this->request1 = $request1_e->fromRight();
		}
  }

  function testValidateRequest()
  {
		$x = MyChatRequest::validate($this->params1);

    $this->instanceOf('Either', $x);
  }

  function testPostFromRequest()
  {
		$fillPost = $this->postModule->fromRequest;
		$x = $fillPost($this->request1, time());

    $this->instanceOf('Either', $x);
    $this->ok($x->isRight());
  }

  function testMain()
  {
		$x = $this->chat->main($this->request1, $this->settings);

    $this->instanceOf('Either', $x);
    $this->ok($x->isRight());
  }

  function testRetainMessage()
  {
    $this->post1->time = $this->now - 30;
		$this->not($this->chat->retainMessage($this->post1));

    $this->post1->time = $this->now;
		$this->ok($this->chat->retainMessage($this->post1));
  }

  function testPostToKeep()
  {
    $posts = $this->getPosts(10);

		$filtered = $this->chat->postsToKeep($posts, 8);
    $this->equals(5, count($filtered));
  }

  function testPostToWrite()
  {
    $posts = $this->getPosts(10);

		$outgoing = $this->chat->postsToWrite($posts, $this->post1, 8);
    $this->equals(6, count($outgoing));
  }


  private function getPosts($n)
  {
    $alphabet = range('a', 'z');

    $ps = array();
    for ($i = 0; $i < $n; $i++) {
      $p = new stdClass();

      $ps []= new FunctionalChatPost(
				array_rand($alphabet, 6),
				'myroom',
				array_rand($alphabet, 12),
				$this->now - $i,
				$n - $i
			);
    }

    return $ps;
  }
}

