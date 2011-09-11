<?php

require('util.php');

function PhpFunctionalChatModule($overrides = array())
{
	/**
	 * @return Either([String]) Validated and possibly modified request data
	 */
	$validate_request = function ($params) {
		// by default, do nothing
		return Either::right($params);
	};

	/**
	 * @return Either(Post)
	 */
	$post_from_request = function ($data, $data_map, $time) use ($validate_request)
	{
		$valid_e = $validate_request($data);

		if ($valid_e->isLeft()) {
			return $valid_e;
		} else {
			$valid = $valid_e->fromRight();

			$_post = new stdClass();

			foreach ($data_map as $_member => $_post_var) {
				$_post->$_member = $valid[$_post_var];
			}

			$_post->time = $time;

			return Either::right($_post);
		}
	};

	$old_posts_from_string = function ($string, $data_map) {
		return json_decode($string);
	};

	$last_id = function (array $old_posts) {
		$_last = 0;

		foreach ($old_posts as $_post) {
			// skip post if no id is defined
			if (isset($_post->id) && $_post->id > $_last) {
				$_last = $_post->id;
			}
		}

		return $_last;
	};

	$posts_to_string = function(array $old_posts, $new_post, $data_map) use ($last_id)
	{
		$_str = '';
		$_last_id = null;

		$_post_with_id = clone $new_post;
		$_post_with_id->id = $last_id($old_posts) + 1;

		$posts = array_merge($old_posts, array($_post_with_id));

		return json_encode($posts);
	};

	/**
	 * @return boolean
	 */
	$retain_message = function ($message) {

		// by default, ignore messages older than 5 seconds
		return $message->time > $_SERVER['REQUEST_TIME'] - 5;
	};

	/**
	 * @return [string]
	 */
	$messages_to_write = function (array $messages, $max_messages) use ($retain_message)
	{
		if (count($messages) > $max_messages) {
			return array_filter($messages, $retain_message);
		} else {
			return $messages;
		}
	};

	/**
	 * @return [string]
	 */
	$messages_to_write_ = function (array $messages, $max_messages) use ($retain_message)
	{
		if (count($messages) > $max_messages) {
			$_outgoing = array();

			foreach ($messages as $_message) {
				if ($retain_message($_message)) {
					$_outgoing []= $message;
				}
			}

			return $_outgoing;
		} else {
			return $messages;
		}
	};


	/**
	 * @return Maybe(resource)
	 */
	$acquire_lock_IO = function ($lock_file)
	{
		if (! file_exists($lock_file)) {
			touch($lock_file);
		}

		$file = fopen($lock_file, 'c');

		if ($file) {
			if (flock($file, LOCK_EX)) {
				return Maybe::just($file);
			} else {
				fclose($file);
				return Maybe::nothing();
			}

		} else {
			return Maybe::nothing();
		}
	};

	/**
	 * @return void
	 */
	$release_lock_IO = function ($lock_file)
	{
		flock($lock_file, LOCK_UN);
		fclose($lock_file);
	};

	/**
	 * @return Either(array) current messages
	 */
	$read_chat_file_IO = function ($file, $data_map) use ($old_posts_from_string)
	{
		if ( ($json = file_get_contents($file)) !== false ) {
			$messages = $old_posts_from_string($json, $data_map);

			if (empty($messages)) {
				return Either::right(array());
			} else {
				return Either::right($messages);
			}

		} else {
			return Either::left('could not read chat file');
		}
	};

	/**
	 * @return Either(void)
	 */
	$write_chat_file_IO = function ($old_messages, $new_post, $data_map, $file) use ($posts_to_string)
	{
		$text = $posts_to_string($old_messages, $new_post, $data_map);

		if ($text) {
			if (@ file_put_contents($file, $text)) {
				return Either::right(null);
			} else {
				return Either::left('could not write to chat file');
			}
		} else {
			return Either::left('could not json_encode messages');
		}
	};

	/**
	 * @return Either(boolean)
	 */
	$add_message_IO = function ($message, $chat_file, $max_messages, $data_map)
		use ($messages_to_write, $read_chat_file_IO, $write_chat_file_IO)
	{
		$messages_e = $read_chat_file_IO($chat_file, $data_map);
		if ($messages_e->isLeft()) {

			return $messages_e;

		} else {
			$old     = $messages_e->fromRight();
			$keeping = $messages_to_write($old, $max_messages);

			return $write_chat_file_IO($keeping, $message, $data_map, $chat_file);
		}
	};

	/**
	 * @return Either(boolean)
	 */
	$receive_post_IO = function ($params, $data_map, $chat_file, $lock_file, $max_messages)
		use ($acquire_lock_IO, $release_lock_IO, $add_message_IO, $post_from_request)
	{
		$post = $post_from_request($params, $data_map, $_SERVER['REQUEST_TIME']);

		if ($post->isLeft()) {
			return $post;
		} else {
			$lock_m = $acquire_lock_IO($lock_file);

			if ($lock_m->isNothing()) {

				return Either::left('could not acquire lock');

			} else {
				$result = $add_message_IO($post->fromRight(), $chat_file, $max_messages, $data_map);
				$release_lock_IO($lock_m->fromJust());

				return $result;
			}
		}
	};

	$check_secttings = function ($settings) {
		$necessary = array(
			'CHAT_FILE',
			'LOCK_FILE',
			'MAX_MESSAGES',
			'MSG_DATA'
		);

		// make sure $settings have all necessary keys
		return count( array_intersect(array_keys($settings), $necessary) ) ==
			count($necessary);
	};




	$main = function ($params, $settings) use ($check_secttings, $receive_post_IO)
	{
		assert('$check_secttings($settings)');

		$msg_data = $settings['MSG_DATA'];

		if (
			isset($params[$msg_data['user']]) &&
			isset($params[$msg_data['message']]) &&
			isset($params[$msg_data['room']])
		) {

			$c_file = $settings['CHAT_FILE'];
			$l_file = $settings['LOCK_FILE'];
			$max    = $settings['MAX_MESSAGES'];

			return $receive_post_IO($params, $msg_data, $c_file, $l_file, $max);
		} else {
			return Either::left('invalid request');
		}
	};

	return compact(
		'main',
		'validate_request',
		'post_from_request'
	);
}

