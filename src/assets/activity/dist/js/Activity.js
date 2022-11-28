(function ($) {
  /** global: Craft */
  /** global: Garnish */
  var ActivityTable = Garnish.Base.extend({
    init: function () {
      $('#activity a');
    },
  });

  Garnish.$doc.ready(function () {
    $('#activity a').on('click', function () {
      var $a = $(this);
      var requestId = $a.data('id');
      Craft.postActionRequest(
        'webhooks/activity/details',
        {
          requestId: requestId,
        },
        function (response, textStatus) {
          if (textStatus === 'success') {
            const slideout = new Craft.Slideout(response.html, {
              containerAttributes: {
                class: 'webhook-activity-slideout',
              },
            });
            initSlideout(slideout, requestId);
          }
        }
      );
    });
  });

  function initSlideout(slideout, requestId) {
    const $sendBtn = slideout.$container.find('.send-btn');
    $sendBtn.on('click', function () {
      if (!$sendBtn.hasClass('disabled')) {
        $sendBtn.addClass('disabled');
        const $spinner = $(this).next('.spinner').removeClass('hidden');
        Craft.postActionRequest(
          'webhooks/activity/redeliver',
          {
            requestId: requestId,
          },
          function (response, textStatus) {
            $spinner.addClass('hidden');
            if (textStatus === 'success') {
              slideout.$container.html(response.html);
              initSlideout(slideout, requestId);
            }
          }
        );
      }
    });
  }
})(jQuery);
