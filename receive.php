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

	protected static function fromValidParams(array $params)
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

	$request = MyChatRequest::fromParams($_POST);

	if ($request->isLeft()) {
		$result = $request;
	} else {
		$result = $chat->receivePostIO($request->fromRight(), $settings);
	}

  if ($result->isLeft()) {
    header('HTTP/1.0 500 Internal Server Error');
		echo $result_e->fromLeft();

	} else {

		header('Cache-Control: no-cache, must-revalidate');
		header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
		header('Content-type: application/json');
		readfile($settings->chat_file);
	}

}

