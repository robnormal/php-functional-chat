/*
 * Message format:
msgs = [
  { message: "hely, what's up?", time: test_time, chatter: "jerk" }
];
*/

var newChat;

(function($) {
	newChat = function (options) {
		if (!options.get_url) {
			throw 'Must provide options.get_url in argument to newChat';
		} else if (!options.post_url) {
			throw 'Must provide options.post_url in argument to newChat';
		} else if (!options.identity) {
			throw 'Must provide options.identity in argument to newChat';
		}

		var
			get_url     = options.get_url,
			post_url    = options.post_url,
			chat_div_id = options.display_selector || '#chat_div',
			textarea_id = options.input_selector || '#chat_input',
			max_length  = options.max_length || 512, // standard max for chat rooms
			chat_div    = $(chat_div_id),
			textarea    = $(textarea_id),
			last_fetch  = 0,
			identity, timeout,

			fetchMessages, pollServer, postMessage, validateMessage,
			inputHandler, warnAboutLength, stopPolling, startPolling,

			// these are visible at this scope, for use w/automatic messages
			// i.e., from the client-side script itself
			showMessage, formatMessage
		;

		$.pluck = function (arr, member) {
			var
				members = [],
				len = arr.length,
				i
			;

			for (i = 0; i < len; ++i) {
				members[i] = arr[i][member];
			}

			return members;
		};


		if ($.isFunction(options.identity)) {
			identity = options.identity();
		} else {
			identity = options.identity;
		}

		showMessage = options.showMessage || function (msg) {
			if (msg.message && msg.user) {
				chat_div.append(formatMessage(msg));
			}
		};

		(function () {
			var
				last_id = 0,

				checkMessage = function (msg) {
					return msg && msg.id > last_id;
				},

				processMessage = function (msg) {
					if (checkMessage(msg)) {
						showMessage(msg);

						return true;
					} else {
						return false;
					}
				},

				test_time = $.now()
			;

			fetchMessages = function () {
				var msg_data, msgs, len, i;

				$.getJSON(get_url, null, function (msgs) {

					if (msgs) {
						len = msgs.length;
						for (i = 0; i < len; i++) {
							processMessage(msgs[i]);
						}

						last_id = 0;
						for (i = 0; i < len; i++) {
							if (msgs[i] && msgs[i].id && msgs[i].id > last_id) {
								last_id = msgs[i].id ;
							}
						}

						return last_id;
					}
				});

			};
		})();

		stopPolling = options.stopPolling || function () {
			clearInterval(timeout);
		};

		startPolling = options.startPolling || function () {
			var polling_rate = options.polling_rate || 1000; // default to 1s

			timeout = setInterval(fetchMessages, polling_rate);

			// listen on textarea
			if (inputHandler) {
				inputHandler();
			}

			return timeout;
		};


		/*** Posting ***/

		// helper function
		var holdingShift = function (e) {
			// standard || IE
			return e.shiftKey || (e.modifiers & window.Event.SHIFT_MASK);
		};

		inputHandler = options.inputHandler || function () {
			textarea.keydown(function (e) {
				var ENTER = 13, chars_sent;

				if (e.which === ENTER && !holdingShift(e)) {
					chars_sent = postMessage();
					textarea.val(textarea.val().slice(chars_sent));

					return false;
				}
			});
		};

		var confirmLongMessage = options.confirmLongMessage || function (text) {
			return confirm('Your message is too long by ' + (text.length - max_length) +
				' characters. Do you want to send the first ' + max_length + ' characters, ' +
				'ending in "' + text.slice(max_length - 15, max_length) + '"?');
		};

		postMessage = options.postMessage || function () {
			var text = textarea.val(), cropped_text;

			if (text.length > max_length) {
				if (confirmLongMessage(text)) {
					cropped_text = text.slice(0, max_length);
				} else {
					return 0;
				}
			} else {
				cropped_text = text;
			}

			if (validateMessage(cropped_text)) {
				$.post(post_url, {
					message: cropped_text,
					user: identity,
					room: options.room
				});
				return cropped_text.length;
			} else {
				return 0;
			}
		};

		validateMessage = options.validateMessage || function (text) {
			return text && text.length <= max_length;
		};

		formatMessage = options.formatMessage || function (msg) {
			return ['<div>',
							'<div class="chat_userame">', msg.user, '</div>',
							'<div class="chat_msg">', msg.message, '</div>',
							'</div>'
						 ].join('');
		};

		return {
			start: startPolling,
			stop:  stopPolling,
			post:  postMessage
		};
	};
})(jQuery);
