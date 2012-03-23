<?php

class FunctionalChatPostModule
{
	public $fromRequest;
	public $fromJson;

	function __construct($fromRequest, $fromJson)
	{
		$this->fromRequest = $fromRequest;
		$this->fromJson    = $fromJson;
	}

	static function standard()
	{
		return new FunctionalChatPostModule(
			FunctionalChatPost::$fromRequest,
			FunctionalChatPost::$fromJson
		);
	}
}

class FunctionalChatPost
{
	public $id;

	public $user;
	public $room;
	public $message;
	public $time;

	public static $fromRequest;
	public static $fromJson;

	function __construct($user, $room, $message, $time, $id = null)
	{
		$this->user    = $user;
		$this->room    = $room;
		$this->message = $message;
		$this->time    = $time;
		$this->id      = $id;
	}

	function toJson()
	{
		return json_encode($this);
	}

}

FunctionalChatPost::$fromRequest = function(FunctionalChatRequest $request, $time) {
	if (
		isset($request->user) &&
		isset($request->room) &&
		isset($request->message)
	) {
		return Either::right(new FunctionalChatPost(
			$request->user,
			$request->room,
			$request->message,
			$time
		));
	} else {
		return Either::left('invalid request');
	}
};

FunctionalChatPost::$fromJson = function($json) {
	return new FunctionalChatPost(
		$json->user,
		$json->room,
		$json->message,
		$json->time,
		$json->id
	);
};

