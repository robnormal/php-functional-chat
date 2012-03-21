<?php

class FunctionalChatSettings
{
	public $chat_file;
	public $lock_file;
	public $max_messages;

	public $postModule;

	function __construct(FunctionalChatPostModule $postModule, $chat_file, $lock_file, $max_messages)
	{
		$this->chat_file    = $chat_file;
		$this->lock_file    = $lock_file;
		$this->max_messages = $max_messages;

		// this allows us to call this w/o using user_call_func_array
		$this->postModule = $postModule;
	}
}

