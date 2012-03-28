jQuery(function ($) {
  $('#msgTemplate').template('chatPost');

  var
    formatTime = function (t) {
      var
        d = new Date(t * 1000),
        secs = d.getSeconds(),
        s = secs < 10 ? "0" + secs.toString() : secs;

      return [d.getHours(), ':', d.getMinutes(), ':', s].join('');
    },

    showMessage = function (msg) {
      var time_str = formatTime(msg.time);
      $.tmpl('chatPost', msg, {timeStr: time_str}).appendTo('#chat_area');
    },

    chat = new Chat({
      post_url: '/new-chat/receive.php',
      get_url:  '/new-chat/chat.json.php',
      showMessage: showMessage,
      identity: 'joe'
    });
	
	chat.showMessage = showMessage;

  chat.start();

  $('#post_message').click(function () {
    chat.post();
    return false;
  });
});

