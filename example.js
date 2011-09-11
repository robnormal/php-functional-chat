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
      $.tmpl('chatPost', msg, {timeStr: time_str}).appendTo('#chat_div');
    },

    chat = newChat({
      post_url: '/php-functional-chat/receive.php',
      get_url:  '/php-functional-chat/chat.json.php',
      showMessage: showMessage,
      identity: 'joe'
    }
  );

  chat.start();

  $('#post_message').click(function () {
    chat.post();
    return false;
  });
});

