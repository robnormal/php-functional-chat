<?php

require_once('RR_PHPUnit_TestCase.class.php');
require_once('chat.php');

class PhpFunctionalChatTest extends RR_PHPUnit_TestCase
{
  function setUp()
  {
    $this->data_map = array(
      'user'    => 'chatter',
      'message' => 'message',
      'room'    => 'room'
    );

    $this->settings = array(
      'CHAT_FILE' =>    __DIR__.'/test_chat.json',
      'LOCK_FILE' =>    __DIR__.'/test_chat.lock',
      'MAX_MESSAGES' => 10,
      'MSG_DATA' =>     $this->data_map
    );

    $r['room'] = 'myplace';
    $r['chatter'] = 'Joe';
    $r['message'] = 'Test message';

    $this->params1 = $r;

    $p = new stdClass();
    $p->user = 'Ramona';
    $p->message = 'Hello';
    $p->room = 'myroom';

    $this->post1 = $p;

    $this->now = $_SERVER['REQUEST_TIME'];

		$this->chat = new PhpFunctionalChat();
  }

  function testValidateRequest()
  {
		$x = $this->chat->validateRequest($this->params1);

    $this->instanceOf('Either', $x);
  }

  function testPostFromRequest()
  {
		$x = $this->chat->postFromRequest($this->params1, $this->data_map, time());

    $this->instanceOf('Either', $x);
    $this->ok($x->isRight());
  }

  function testMain()
  {
		$x = $this->chat->main($this->params1, $this->settings);

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
      $p->user = array_rand($alphabet, 6);
      $p->message = array_rand($alphabet, 12);
      $p->room = 'myroom';
      $p->time = $this->now - $i;
      $p->id = $n - $i;

      $ps []= $p;
    }

    return $ps;
  }
}

