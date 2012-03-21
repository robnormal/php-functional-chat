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

			function(FunctionalChatRequest $r, $time) {
				return FunctionalChatPost::fromRequest($r, $time);
			},

			function($json) {
				return FunctionalChatPost::fromJson($json);
			}

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

	static function fromRequest(FunctionalChatRequest $request, $time)
	{
		return Either::right(new FunctionalChatPost(
			$request->user,
			$request->room,
			$request->message,
			$time
		));
	}

	static function fromJson($json)
	{
		return new FunctionalChatPost(
			$json->user,
			$json->room,
			$json->message,
			$json->time,
			$json->id
		);
	}
}

