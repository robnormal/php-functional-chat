<?php

require_once('chat.php');
extract(PhpFunctionalChatModule(array('test' => true)));

$params['room'] = 'myplace';
$params['chatter'] = 'Joe';
$params['message'] = 'Test message';

$data_map = array(
	// internal variable  =>  POST request variable to get its value from
	'user'    => 'chatter',
	'message' => 'message',
	'room'    => 'room'
);

$settings = array(
	'CHAT_FILE' =>    '/var/www/php-functional-chat/chat.json',
	'LOCK_FILE' =>    '/var/www/php-functional-chat/tmp/php_chat.lock',
	'MAX_MESSAGES' => 10,
	'MSG_DATA' =>     $data_map
);

if ($validateRequest($params) instanceof Either) {
	echo "validateRequest() returns Either\n";
} else {
	echo "ERROR: validateRequest() returns Either\n";
}

$post = $postFromRequest($params, $data_map, time());
if (! ($post instanceof Either)) {
	echo "ERROR: postFromRequest() returns Either\n";
} else if ($post->isLeft()) {
	echo "ERROR: postFromRequest() returns Right on valid data\n";
} else {
	echo "postFromRequest() returns Right on valid data\n";
}


$result = $main($params, $settings);

if ($result instanceof Either) {
	if ($result->isRight()) {
		echo "main() returns Right on valid data\n";
	} else {
		echo "ERROR: main() returns Either\n";
	}
} else {
	echo "ERROR: main() returns Either\n";
}



