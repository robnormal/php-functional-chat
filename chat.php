<?php

require_once(__DIR__.'/util.php');

class PhpFunctionalChat
{
	static function oldPostsFromJson($string, $postFromJson)
	{
		$post_objs = json_decode($string);

		if (empty($post_objs)) {
			return array();
		} else {
			return array_map($postFromJson, $post_objs);
		}
	}

	static function lastId(array $old_posts)
	{
		$_last = 0;

		foreach ($old_posts as $_post) {
			// skip post if no id is defined
			if (isset($_post->id) && $_post->id > $_last) {
				$_last = $_post->id;
			}
		}

		return $_last;
	}

	static function giveNewPostId(FunctionalChatPost $post, $old_posts)
	{
		$new_id            = static::lastId($old_posts) + 1;
		$_post_with_id     = clone $post;
		$_post_with_id->id = $new_id;

		return $_post_with_id;
	}

	/**
	 * @param [FunctionalChatPost] $posts
	 *
	 * @return JSON
	 */
	static function postsToJson(array $posts)
	{
		return json_encode($posts);
	}

	/**
	 * @return boolean
	 */
	static function retainMessage(FunctionalChatPost $message)
	{
		// by default, ignore messages older than 5 seconds (or ones without a time)
		return !isset($message->time) ||
			$message->time > $_SERVER['REQUEST_TIME'] - 5;
	}


	/**
	 * @param [FunctionalChatPost] $posts
	 * @param int                  $max_posts
	 *
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
	 * Escape text, etc.
	 *
	 * @return Either(Post)
	 */
	static function transformPost(FunctionalChatPost $post)
	{
		$_post          = clone $post;
		$_post->message = htmlentities($post->message);

		return $_post;
	}

	/**
	 * @param [FunctionalChatPost] $old_posts
	 * @param FunctionalChatPost   $incoming
	 * @param int                  $max_posts
	 *
	 * @return Either([Post])
	 */
	static function postsToWrite(array $posts, FunctionalChatPost $incoming, $max_posts)
	{
		// get ID before filtering messages, so we can be sure it is up-to-date
		$ePost = static::transformPost(static::giveNewPostId($incoming, $posts));

		if ($ePost->isLeft()) {
			return $ePost;
		} else {
			$posts []= $ePost->fromRight();

			return Either::right(
				static::postsToKeep($posts, $max_posts)
			);
		}
	}

	/**
	 * @return Either(resource)
	 */
	static function acquireLockIO($lock_file)
	{
		if (! file_exists($lock_file)) {
			@ touch($lock_file);
			if (! file_exists($lock_file)) {
				return Either::left("could not create lock file $lock_file");
			}
		}

		$file = @ fopen($lock_file, 'c');

		if ($file) {
			if (@ flock($file, LOCK_EX)) {
				return Either::right($file);
			} else {
				fclose($file);
				return Either::left("could not acquire lock on $file");
			}

		} else {
			return Either::left("could not open lock file $lock_file");
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
	 * @param file_path
	 * @param (JSON -> FunctionalChatPost)
	 *
	 * @return Either([FunctionalChatPost]) current messages
	 */
	static function readChatFileIO($file, $postFromJson)
	{
		if ( ($json = @ file_get_contents($file)) !== false ) {
			$posts = static::oldPostsFromJson($json, $postFromJson);

			if (empty($posts)) {
				return Either::right(array());
			} else {
				return Either::right($posts);
			}

		// if file doesn't exist, return empty
		} else if (! file_exists($file)) {
			return Either::right(array());

		} else {
			return Either::left("could not read chat file $file");
		}
	}

	/**
	 * @param [FunctionalChatPost]
	 *
	 * @return Either(void)
	 */
	static function writeChatFileIO($posts, $file, $lock_file)
	{
		$text = static::postsToJson($posts);

		if ($text) {
			$lock_m = static::acquireLockIO($lock_file);
			if ($lock_m->isLeft()) {

				return $lock_m;

			} else {

				$rLock_file = $lock_m->fromRight();

				// first write contents, then copy file over. Copying is atomic
				if (@ fwrite($rLock_file, $text)) {
					if (@ rename($lock_file, $file)) {
						$result = Either::right(null);
					} else {
						$result = Either::left("could not write to chat file: $file");
					}
				} else {
					$result = Either::left("could not write to temp file: $tmp_file");
				}

				static::releaseLockIO($rLock_file);

				return $result;
			}
		} else {
			return Either::left('could not json_encode messages');
		}
	}

	/**
	 * @return Either(boolean)
	 */
	static function addMessageIO(FunctionalChatPost $incoming, FunctionalChatSettings $settings)
	{
		$messages_e = static::readChatFileIO($settings->chat_file, $settings->postModule->fromJson);
		if ($messages_e->isLeft()) {

			return $messages_e;

		} else {
			$old = $messages_e->fromRight();
			$eWriting = static::postsToWrite($old, $incoming, $settings->max_messages);

			if ($eWriting->isLeft()) {
				return $eWriting;
			} else {
				return static::writeChatFileIO($eWriting->fromRight(), $settings->chat_file, $settings->lock_file);
			}
		}
	}

	/**
	 * @return Either(boolean)
	 */
	static function receivePostIO(FunctionalChatRequest $req, FunctionalChatSettings $settings)
	{
		$fillRequest = $settings->postModule->fromRequest;
		$post = $fillRequest($req, $_SERVER['REQUEST_TIME']);

		if ($post->isLeft()) {
			return $post;
		} else {
			return static::addMessageIO($post->fromRight(), $settings);
		}
	}
}

include_once(__DIR__.'/PostModule.php');
include_once(__DIR__.'/SettingsModule.php');

class FunctionalChatRequest
{
	public $user;
	public $room;
	public $message;

	function __construct($user, $room, $message)
	{
		$this->user    = $user;
		$this->room    = $room;
		$this->message = $message;
	}

	/**
	 * @return Either([String]) Validated and possibly modified request data
	 */
	static function validate(array $params) {
		return self::validateFromList(array('user', 'room', 'message'), $params);
	}

	static function fromParams(array $params)
	{
		$new_params_e = static::validate($params);

		if ($new_params_e->isLeft()) {
			return $new_params_e;
		} else {
			$new_params = $new_params_e->fromRight();

			return Either::right(static::fromValidParams($new_params));
		}
	}

	protected static function fromValidParams(array $params)
	{
		new FunctionalChatRequest(
			$new_params['user'],
			$new_params['room'],
			$new_params['message']
		);
	}

	static function validateFromList(array $required, array $params)
	{
		foreach ($required as $name) {
			if (!isset($params[$name])) {
				return Either::left("Bad request: missing required parameter '$name'");
			}
		}

		return Either::right($params);
	}
}

