<?php

include('chat.php');
extract(getPhpChatMethods());

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
	'CHAT_FILE' =>    '/home/rob/Sites/localhost/Chat/test_chat.json',
	'LOCK_FILE' =>    '/home/rob/Sites/localhost/Chat/tmp/test_php_chat.lock',
	'MAX_MESSAGES' => 10,
	'MSG_DATA' =>     $data_map
);

if ($validate_request($params) instanceof Either) {
	echo "validate_request() returns Either\n";
} else {
	echo "ERROR: validate_request() returns Either\n";
}

$post = $post_from_request($params, $data_map, time());
if (! ($post instanceof Either)) {
	echo "ERROR: post_from_request() returns Either\n";
} else if ($post->isLeft()) {
	echo "ERROR: post_from_request() returns Right on valid data\n";
} else {
	echo "post_from_request() returns Right on valid data\n";
}


$result = $main($params, $settings);

if ($result instanceof Either) {
	if ($result->isRight()) {
		echo "validate_request() returns Right on valid data\n";
	} else {
		echo "ERROR: validate_request() returns Either\n";
	}
} else {
	echo "ERROR: validate_request() returns Either\n";
}



