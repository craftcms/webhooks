(function($) {
    /** global: Craft */
    /** global: Garnish */
    var ActivityTable = Garnish.Base.extend(
        {
            init: function() {
                $('#activity a')
            }
        }
    );

    Garnish.$doc.ready(function() {
        $('#activity a').on('click', function() {
            var $a = $(this);
            var requestId = $a.data('id');
            Craft.postActionRequest('webhooks/activity/details', {
                requestId: requestId
            }, function(response, textStatus) {
                if (textStatus === 'success') {
                    var hud = new Garnish.HUD($a, response.html);
                    initHud(hud, requestId);
                }
            })
        });
    });

    function initHud(hud, requestId) {
        var $redeliverBtn = hud.$main.find('.redeliver-btn');
        $redeliverBtn.on('click', function() {
            if (!$redeliverBtn.hasClass('disabled') && confirm(Craft.t('webhooks', 'Are you sure you want to resend this request?'))) {
                $redeliverBtn.addClass('disabled');
                var $spinner = $(this).next('.spinner').removeClass('hidden');
                Craft.postActionRequest('webhooks/activity/redeliver', {
                    requestId: requestId
                }, function(response, textStatus) {
                    $spinner.addClass('hidden');
                    if (textStatus === 'success') {
                        hud.updateBody(response.html);
                        initHud(hud, requestId);
                    }
                })
            }
        });
    }
})(jQuery);
