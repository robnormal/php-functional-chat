<?php

require_once(__DIR__.'/chat.php');

function PhpFunctionalChatFasterModule()
{
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
}

