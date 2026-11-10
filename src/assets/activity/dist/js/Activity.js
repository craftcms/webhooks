(function ($) {
  /** global: Craft */
  /** global: Garnish */
  var ActivityTable = Garnish.Base.extend({
    init: function () {
      $('#activity a');
    },
  });

  // Craft 6 bridged screens mount their markup after document ready, and provide Craft.whenReady() for that
  const whenReady =
    Craft.whenReady || ((callback) => Garnish.$doc.ready(callback));

  whenReady(function () {
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
    // Craft 6 slideouts expose their container as a plain element
    const $container = slideout.$container || $(slideout.container);
    const $sendBtn = $container.find('.send-btn');
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
              $container.html(response.html);
              initSlideout(slideout, requestId);
            }
          }
        );
      }
    });
  }
})(jQuery);
