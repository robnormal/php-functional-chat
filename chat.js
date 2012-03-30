/*
 * Message format:
msgs = [
  { message: "hely, what's up?", time: test_time, chatter: "jerk" }
];
*/

var Chat, curry, curryThis, __slice = Array.prototype.slice;

(function($) {
	var holdingShift = function (e) {
		// standard || IE
		return e.shiftKey || (e.modifiers & window.Event.SHIFT_MASK);
	};



	Chat = function(options) {
		if (!options.get_url) {
			throw 'Must provide options.get_url in argument to newChat';
		} else if (!options.post_url) {
			throw 'Must provide options.post_url in argument to newChat';
		} else if (!options.identity) {
			throw 'Must provide options.identity in argument to newChat';
		}

		this.options    = options;

		this.identity   = options.identity;
		this.get_url    = options.get_url;
		this.post_url   = options.post_url;
		this.room       = options.room;

		this.max_length = options.max_length || 512; // standard max for chat rooms
		this.chat_area  = $(options.chat_area || '#chat_area');
		this.input_area = $(options.input_area || '#chat_input');
	};

	Chat.prototype = {
		showMessage: function (msg) {
			if (msg.message && msg.user) {
				this.chat_area.append(this.formatMessage(msg));
			}
		},

		checkMessage: function (msg, last_id) {
			return msg && msg.id > last_id;
		},

		processMessage: function (msg, last_id) {
			if (this.checkMessage(msg, last_id)) {
				this.showMessage(msg);

				return true;
			} else {
				return false;
			}
		},

		processMessages: (function() {
			// last_id - stateful variable required for async stuff
			var last_id = 0;

			return function(msgs) {
				var len, i;

				if (msgs) {
					len = msgs.length;
					for (i = 0; i < len; i++) {
						this.processMessage(msgs[i], last_id);
					}

					last_id = 0;
					for (i = 0; i < len; i++) {
						if (msgs[i] && msgs[i].id && msgs[i].id > last_id) {
							last_id = msgs[i].id ;
						}
					}

					return last_id;
				}
			};
		})(),

		fetchMessages: function() {
			var self = this;
			$.getJSON(this.get_url, null, function(msgs) {
				self.processMessages(msgs);
			});
		},

		stop: clearInterval,

		start: function (polling_rate, inputHandler) {
			polling_rate = polling_rate || 1000;
			var self = this;

			// do it now
			self.fetchMessages();

			// and set a timeout
			var timeout = setInterval(function() {
				self.fetchMessages();
			}, polling_rate);

			// listen on input_area
			if (this.inputHandler) {
				this.inputHandler();
			}

			return timeout;
		},

		inputHandler: function () {
			var self = this;
			self.input_area.keydown(function (e) {
				var ENTER = 13, chars_sent;

				if (e.which === ENTER && !holdingShift(e)) {
					chars_sent = self.postMessage();
					self.input_area.val(self.input_area.val().slice(chars_sent));

					return false;
				}
			});
		},

		confirmLongMessage: function (text) {
			return confirm('Your message is too long by ' + (text.length - this.max_length) +
				' characters. Do you want to send the first ' + this.max_length + ' characters, ' +
				'ending in "' + text.slice(this.max_length - 15, this.max_length) + '"?');
		},

		postMessage: function () {
			var text = this.input_area.val(), cropped_text;

			if (text.length > this.max_length) {
				if (this.confirmLongMessage(text)) {
					cropped_text = text.slice(0, this.max_length);
				} else {
					return 0;
				}
			} else {
				cropped_text = text;
			}

			if (this.validateMessage(cropped_text)) {
				var self = this;

				$.ajax(this.post_url, {
					type: 'POST',
					data: {
						message: cropped_text,
						user: this.identity,
						room: this.room
					},
					success: function(msgs) {
						self.processMessages(msgs);
					},
					error: this.handlePostError
				});
				return cropped_text.length;
			} else {
				return 0;
			}
		},

		handlePostError: function(err) {
			alert(err.responseText);
		},

		validateMessage: function (text) {
			return text && text.length <= this.max_length;
		},

		formatMessage: function (msg) {
			return [
				'<div>',
				'<div class="chat_userame">', msg.user, '</div>',
				'<div class="chat_msg">', msg.message, '</div>',
				'</div>'
			].join('');
		}
	};
})(jQuery);

