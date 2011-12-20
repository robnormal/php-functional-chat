<?php

require_once(__DIR__.'/util.php');

function PhpFunctionalChatModule($options = array())
{
  extract(PhpFunctionalChatUtilityModule());

  /**
   * @return Either([String]) Validated and possibly modified request data
   */
  $validateRequest = function ($params) {
    // by default, do nothing
    return Either::right($params);
  };

  /**
   * @return Either(Post)
   */
  $postFromRequest = function ($data, $data_map, $time) use ($validateRequest)
  {
    $valid_e = $validateRequest($data);

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

  $oldPostsFromString = function ($string, $data_map) {
    return json_decode($string);
  };

  $lastId = function (array $old_posts) {
    $_last = 0;

    foreach ($old_posts as $_post) {
      // skip post if no id is defined
      if (isset($_post->id) && $_post->id > $_last) {
        $_last = $_post->id;
      }
    }

    return $_last;
  };

  $giveNewPostId = function ($post, $old_posts) use ($lastId) {
    $new_id            = $lastId($old_posts) + 1;
    $_post_with_id     = clone $post;
    $_post_with_id->id = $new_id;

    return $_post_with_id;
  };

  $postsToString = function(array $posts, $data_map) {
    return json_encode($posts);
  };

  /**
   * @return boolean
   */
  $retainMessage = function ($message) {

    // by default, ignore messages older than 5 seconds (or ones without a time)
    return !isset($message->time) ||
      $message->time > $_SERVER['REQUEST_TIME'] - 5;
  };


  /**
   * @return [Post]
   */
  $postsToKeep = function (array $posts, $max_posts) use ($retainMessage) {
    if (count($posts) >= $max_posts) {
      $keeping = array_filter($posts, $retainMessage);
    } else {
      $keeping = $posts;
    }

    return $keeping;
  };

  /**
   * @return [Post]
   */
  $postsToWrite = function (array $old_posts, $incoming, $max_posts)
    use ($giveNewPostId, $postsToKeep)
  {
    // get ID before filtering messages, so we can be sure it is up-to-date
    $post = $giveNewPostId($incoming, $old_posts);
    $keeping = $postsToKeep($old_posts, $max_posts);

    return array_merge($keeping, array($post));
  };


  /**
   * @return Either(resource)
   */
  $acquireLockIO = function ($lock_file)
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
  };

  /**
   * @return void
   */
  $releaseLockIO = function ($lock_file)
  {
    flock($lock_file, LOCK_UN);
    fclose($lock_file);
  };

  /**
   * @return Either(array) current messages
   */
  $readChatFileIO = function ($file, $data_map) use ($oldPostsFromString)
  {
    if ( ($json = file_get_contents($file)) !== false ) {
      $messages = $oldPostsFromString($json, $data_map);

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
  $writeChatFileIO = function ($messages, $data_map, $file) use ($postsToString)
  {
    $text = $postsToString($messages, $data_map);

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
  $addMessageIO = function ($incoming, $chat_file, $max_messages, $data_map)
    use ($postsToWrite, $readChatFileIO, $writeChatFileIO)
  {
    $messages_e = $readChatFileIO($chat_file, $data_map);
    if ($messages_e->isLeft()) {

      return $messages_e;

    } else {
      $old = $messages_e->fromRight();
      $writing = $postsToWrite($old, $incoming, $max_messages);

      return $writeChatFileIO($writing, $data_map, $chat_file);
    }
  };

  /**
   * @return Either(boolean)
   */
  $receivePostIO = function ($params, $data_map, $chat_file, $lock_file, $max_messages)
    use ($acquireLockIO, $releaseLockIO, $addMessageIO, $postFromRequest)
  {
    $post = $postFromRequest($params, $data_map, $_SERVER['REQUEST_TIME']);

    if ($post->isLeft()) {
      return $post;
    } else {
      $lock_m = $acquireLockIO($lock_file);

      if ($lock_m->isLeft()) {

        return $lock_m;

      } else {
        $result = $addMessageIO($post->fromRight(), $chat_file, $max_messages, $data_map);
        $releaseLockIO($lock_m->fromRight());

        return $result;
      }
    }
  };

  $checkSecttings = function ($settings) {
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




  $main = function ($params, $settings) use ($checkSecttings, $receivePostIO)
  {
    assert('$checkSecttings($settings)');

    $msg_data = $settings['MSG_DATA'];

    if (
      isset($params[$msg_data['user']]) &&
      isset($params[$msg_data['message']]) &&
      isset($params[$msg_data['room']])
    ) {

      $c_file = $settings['CHAT_FILE'];
      $l_file = $settings['LOCK_FILE'];
      $max    = $settings['MAX_MESSAGES'];

      return $receivePostIO($params, $msg_data, $c_file, $l_file, $max);
    } else {
      return Either::left('invalid request');
    }
  };

  if ($val($options['test'])) {
    $exports = array(
      'validateRequest',
      'postFromRequest',
      'oldPostsFromString',
      'lastId',
      'giveNewPostId',
      'postsToString',
      'retainMessage',
      'postsToKeep',
      'postsToWrite',
      'acquireLockIO',
      'releaseLockIO',
      'readChatFileIO',
      'writeChatFileIO',
      'addMessageIO',
      'receivePostIO',
      'checkSecttings',
      'main',
    );

  } else {
    $exports = array('main');
  }

  return compact($exports);
}

