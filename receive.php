<?php

require_once(__DIR__.'/chat.php');
extract(PhpFunctionalChatModule());
extract(PhpFunctionalChatUtilityModule());

if (!empty($_POST)) {
	$_POST['room'] = 'myplace';

	$result = $main($_POST, array(
		'CHAT_FILE' =>    '/var/www/php-functional-chat/chat.json',
		'LOCK_FILE' =>    '/var/www/php-functional-chat/tmp/php_chat.lock',
		'MAX_MESSAGES' => 10,
		'MSG_DATA' =>     array(
			// internal variable  =>  POST request variable to get its value from
			'user'    => 'chatter',
			'message' => 'message',
			'room'    => 'room'
		)
	));

	if ($result->isLeft()) {
		header('HTTP/1.0 500 Internal Server Error');
  }

}


