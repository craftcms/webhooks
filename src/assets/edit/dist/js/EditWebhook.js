(function($) {
    /** global: Craft */
    /** global: Garnish */
    var EditWebhook = Garnish.Base.extend(
        {
            $nameInput: null,

            init: function() {
                this.$nameInput = $('#name');

                this.addListener(this.$nameInput, 'change, keyup', 'handleTextChange');
            },

            handleTextChange: function() {
                var input = this.$nameInput.get(0);

                // does it look like they just typed -> or => ?
                if (typeof input.selectionStart !== 'undefined' && input.selectionStart === input.selectionEnd) {
                    var pos = input.selectionStart;
                    var last2 = input.value.substring(pos - 2, pos);
                    if (last2 === '->' || last2 === '=>') {
                        input.value = input.value.substring(0, pos - 2) + '➡️' + input.value.substring(pos);
                        input.setSelectionRange(pos, pos);
                    }
                }
            }
        })


    Garnish.$doc.ready(function() {
        new EditWebhook();
    });
})(jQuery);
