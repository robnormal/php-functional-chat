<?php
require_once(__DIR__.'/chat.php');

class MyChatRequest extends FunctionalChatRequest
{
	/**
	 * @return Either([String]) Validated and possibly modified request data
	 */
	static function validate(array $params)
	{
		return validateFromList(array('chatter', 'room', 'message'), $params);
	}

	protected static function fillFromValidPost(array $params)
	{
		return new MyChatRequest(
      $params['chatter'],
      $params['room'],
      $params['message']
		);
	}
}

if (!empty($_POST)) {
  $_POST['room'] = 'myplace';

	$chat = new PhpFunctionalChat();

	$settings = new FunctionalChatSettings(
		FunctionalChatPostModule::standard(),
    __DIR__ . '/chat.json',
    __DIR__ . '/tmp/php_chat.lock',
    10
	);

	$request_e = MyChatRequest::fromPost($_POST);

	if ($request_e->isRight()) {
		$result_e = $chat->main($request_e->fromRight(), $settings);
	} else {
		$result_e = $request_e;
	}

  if ($result_e->isLeft()) {
    header('HTTP/1.0 500 Internal Server Error');
		echo $result_e->fromLeft();
	}

}

