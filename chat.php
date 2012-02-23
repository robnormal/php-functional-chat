<?php

require_once(__DIR__.'/util.php');

class PhpFunctionalChat
{
	/**
	 * @return Either([String]) Validated and possibly modified request data
	 */
	static function validateRequest($params) {
		// by default, do nothing
		return Either::right($params);
	}

	/**
	 * @return Either(Post)
	 */
	static function postFromRequest($data, $data_map, $time)
	{
		$valid_e = static::validateRequest($data);

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
	}

	static function oldPostsFromString($string, $data_map) {
		return json_decode($string);
	}

	static function lastId(array $old_posts) {
		$_last = 0;

		foreach ($old_posts as $_post) {
			// skip post if no id is defined
			if (isset($_post->id) && $_post->id > $_last) {
				$_last = $_post->id;
			}
		}

		return $_last;
	}

	static function giveNewPostId($post, $old_posts)
	{
		$new_id            = static::lastId($old_posts) + 1;
		$_post_with_id     = clone $post;
		$_post_with_id->id = $new_id;

		return $_post_with_id;
	}

	static function postsToString(array $posts, $data_map) {
		return json_encode($posts);
	}

	/**
	 * @return boolean
	 */
	static function retainMessage($message) {

		// by default, ignore messages older than 5 seconds (or ones without a time)
		return !isset($message->time) ||
			$message->time > $_SERVER['REQUEST_TIME'] - 5;
	}


	/**
	 * @return [Post]
	 */
	static function postsToKeep(array $posts, $max_posts)
	{
		if (count($posts) >= $max_posts) {
			return array_filter($posts, array(get_called_class(), 'retainMessage'));
		} else {
			return $posts;
		}
	}

	/**
	 * @return [Post]
	 */
	static function postsToWrite(array $old_posts, $incoming, $max_posts)
	{
		// get ID before filtering messages, so we can be sure it is up-to-date
		$post = static::giveNewPostId($incoming, $old_posts);
		$keeping = static::postsToKeep($old_posts, $max_posts);

		return array_merge($keeping, array($post));
	}


	/**
	 * @return Either(resource)
	 */
	static function acquireLockIO($lock_file)
	{
		if (! file_exists($lock_file)) {
			touch($lock_file);
			if (! file_exists($lock_file)) {
				return Either::left('could not create lock file');
			}
		}

		$file = fopen($lock_file, 'c');

		if ($file) {
			if (flock($file, LOCK_EX)) {
				return Either::right($file);
			} else {
				fclose($file);
				return Either::left('could not acquire lock');
			}

		} else {
			return Either::left('could not open lock file');
		}
	}

	/**
	 * @return void
	 */
	static function releaseLockIO($lock_file)
	{
		flock($lock_file, LOCK_UN);
		fclose($lock_file);
	}

	/**
	 * @return Either(array) current messages
	 */
	static function readChatFileIO($file, $data_map)
	{
		if ( ($json = file_get_contents($file)) !== false ) {
			$messages = static::oldPostsFromString($json, $data_map);

			if (empty($messages)) {
				return Either::right(array());
			} else {
				return Either::right($messages);
			}

		} else {
			return Either::left('could not read chat file');
		}
	}

	/**
	 * @return Either(void)
	 */
	static function writeChatFileIO($messages, $data_map, $file)
	{
		$text = static::postsToString($messages, $data_map);

		if ($text) {
			if (@ file_put_contents($file, $text)) {
				return Either::right(null);
			} else {
				return Either::left("could not write to chat file: $file");
			}
		} else {
			return Either::left('could not json_encode messages');
		}
	}

	/**
	 * @return Either(boolean)
	 */
	static function addMessageIO($incoming, $chat_file, $max_messages, $data_map)
	{
		$messages_e = static::readChatFileIO($chat_file, $data_map);
		if ($messages_e->isLeft()) {

			return $messages_e;

		} else {
			$old = $messages_e->fromRight();
			$writing = static::postsToWrite($old, $incoming, $max_messages);

			return static::writeChatFileIO($writing, $data_map, $chat_file);
		}
	}

	/**
	 * @return Either(boolean)
	 */
	static function receivePostIO($params, $data_map, $chat_file, $lock_file, $max_messages)
	{
		$post = static::postFromRequest($params, $data_map, $_SERVER['REQUEST_TIME']);

		if ($post->isLeft()) {
			return $post;
		} else {
			$lock_m = static::acquireLockIO($lock_file);

			if ($lock_m->isLeft()) {

				return $lock_m;

			} else {
				$result = static::addMessageIO($post->fromRight(), $chat_file, $max_messages, $data_map);
				static::releaseLockIO($lock_m->fromRight());

				return $result;
			}
		}
	}

	static function checkSecttings($settings) {
		$necessary = array(
			'CHAT_FILE',
			'LOCK_FILE',
			'MAX_MESSAGES',
			'MSG_DATA'
		);

		// make sure $settings have all necessary keys
		return count( array_intersect(array_keys($settings), $necessary) ) ==
			count($necessary);
	}




	static function main($params, $settings)
	{
		assert('static::checkSecttings($settings)');

		$msg_data = $settings['MSG_DATA'];

		if (
			isset($params[$msg_data['user']]) &&
			isset($params[$msg_data['message']]) &&
			isset($params[$msg_data['room']])
		) {

			$c_file = $settings['CHAT_FILE'];
			$l_file = $settings['LOCK_FILE'];
			$max    = $settings['MAX_MESSAGES'];

			return static::receivePostIO($params, $msg_data, $c_file, $l_file, $max);
		} else {
			return Either::left('invalid request');
		}
	}

}

