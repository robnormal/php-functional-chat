<?php

require_once('RR_PHPUnit_TestCase.class.php');
require_once('chat.php');

class PhpFunctionalChatTest extends RR_PHPUnit_TestCase
{
  function setUp()
  {
    $this->module = PhpFunctionalChatModule(array('test' => true));
    $this->utility = PhpFunctionalChatUtilityModule();
    $this->data_map =  $data_map = array(
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
  }

  function testValidateRequest()
  {
    $validateRequest = $this->module['validateRequest'];
    $x = $validateRequest($this->params1);

    $this->instanceOf('Either', $x);
  }

  function testPostFromRequest()
  {
    $postFromRequest = $this->module['postFromRequest'];
    $x = $postFromRequest($this->params1, $this->data_map, time());

    $this->instanceOf('Either', $x);
    $this->ok($x->isRight());
  }

  function testMain()
  {
    $main = $this->module['main'];
    $x = $main($this->params1, $this->settings);

    $this->instanceOf('Either', $x);
    $this->ok($x->isRight());
  }

  function testRetainMessage()
  {
    $retainMessage = $this->module['retainMessage'];

    $this->post1->time = $this->now - 30;
    $this->not($retainMessage($this->post1));

    $this->post1->time = $this->now;
    $this->ok($retainMessage($this->post1));
  }

  function testPostToKeep()
  {
    $posts = $this->getPosts(10);
    $postsToKeep = $this->module['postsToKeep'];

    $filtered = $postsToKeep($posts, 8);
    $this->equals(5, count($filtered));
  }

  function testPostToWrite()
  {
    $posts = $this->getPosts(10);
    $postsToWrite = $this->module['postsToWrite'];

    $outgoing = $postsToWrite($posts, $this->post1, 8);
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

