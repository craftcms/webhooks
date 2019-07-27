(function($) {
    /** global: Craft */
    /** global: Garnish */
    var EditWebhook = Garnish.Base.extend(
        {
            $nameInput: null,
            $classInput: null,
            $eventInput: null,
            $filterSpinner: null,
            $noFiltersMessage: null,
            $filtersTable: null,
            filters: null,

            classVal: null,
            eventVal: null,
            filterTimeout: null,

            init: function() {
                this.$nameInput = $('#name');
                this.$classInput = $('#class');
                this.$eventInput = $('#event');
                this.$filterSpinner = $('#filter-spinner');
                this.$noFiltersMessage = $('#no-filters');
                this.$filtersTable = $('#filters');

                this.filters = {};
                var $filterRows = this.$filtersTable.find('tr');
                var filter;
                for (var i = 0; i < $filterRows.length; i++) {
                    filter = new EditWebhook.Filter($filterRows.eq(i));
                    this.filters[filter.class] = filter;
                }

                this.addListener(this.$nameInput, 'change, keyup', 'handleTextChange');
                this.addListener(this.$classInput, 'change, keyup, blur', 'handleEventChange');
                this.addListener(this.$eventInput, 'change, keyup, blur', 'handleEventChange');
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
            },

            handleEventChange: function() {
                var classChanged = this.classVal !== (this.classVal = this.$classInput.val());
                var eventChanged = this.eventVal !== (this.eventVal = this.$eventInput.val());
                if (classChanged || eventChanged) {
                    clearTimeout(this.filterTimeout);
                    this.filterTimeout = setTimeout(this.updateFilters.bind(this), 500);
                }
            },

            updateFilters: function() {
                if (!this.classVal || !this.eventVal) {
                    return;
                }
                this.$filterSpinner.removeClass('hidden');
                Craft.postActionRequest('webhooks/webhooks/filters', {
                    senderClass: this.classVal,
                    event: this.eventVal,
                }, function(response, textStatus) {
                    this.$filterSpinner.addClass('hidden');
                    if (textStatus === 'success') {
                        this.resetFilters();
                        if (response.filters.length) {
                            this.$noFiltersMessage.addClass('hidden');
                            this.$filtersTable.removeClass('hidden');
                            for (var i = 0; i < response.filters.length; i++) {
                                this.filters[response.filters[i]].$tr.removeClass('hidden');
                            }
                        } else {
                            this.$noFiltersMessage.removeClass('hidden');
                            this.$filtersTable.addClass('hidden');
                        }
                    }
                }.bind(this));
            },

            resetFilters: function() {
                for (var filter in this.filters) {
                    if (!this.filters.hasOwnProperty(filter)) {
                        continue;
                    }
                    this.filters[filter].$tr.addClass('hidden');
                    this.filters[filter].selectIgnore();
                }
            }
        })

    EditWebhook.Filter = Garnish.Base.extend({
        $tr: null,
        $input: null,
        'class': null,
        $noBtn: null,
        $ignoreBtn: null,
        $yesBtn: null,
        value: null,

        init: function($tr) {
            this.$tr = $tr;
            this.class = $tr.data('class');
            this.$noBtn = $tr.find('.filter-no');
            this.$ignoreBtn = $tr.find('.filter-ignore');
            this.$yesBtn = $tr.find('.filter-yes');
            this.$input = $tr.find('input');

            switch (this.$input.val()) {
                case 'yes':
                    this.selectYes();
                    break;
                case 'no':
                    this.selectNo();
                    break;
            }

            this.addListener(this.$noBtn, 'click', 'selectNo');
            this.addListener(this.$ignoreBtn, 'click', 'selectIgnore');
            this.addListener(this.$yesBtn, 'click', 'selectYes');
            this.addListener($tr.find('.btngroup'), 'keydown', 'handleKeydown');
        },

        selectNo: function() {
            this.clear();
            this.$noBtn.addClass('active');
            this.value = false;
            this.$input.val('no');
        },

        selectIgnore: function() {
            this.clear();
            this.$ignoreBtn.addClass('active');
            this.value = null;
            this.$input.val('');
        },

        selectYes: function() {
            this.clear();
            this.$yesBtn.addClass('active');
            this.value = true;
            this.$input.val('yes');
        },

        clear: function() {
            this.$yesBtn.removeClass('active');
            this.$ignoreBtn.removeClass('active');
            this.$noBtn.removeClass('active');
        },

        handleKeydown: function(ev) {
            switch (ev.keyCode) {
                case Garnish.LEFT_KEY:
                    ev.preventDefault();
                    if (this.value === null) {
                        this.selectNo();
                    } else if (this.value === true) {
                        this.selectIgnore();
                    }
                    break;
                case Garnish.RIGHT_KEY:
                    ev.preventDefault();
                    if (this.value === null) {
                        this.selectYes();
                    } else if (this.value === false) {
                        this.selectIgnore();
                    }
            }
        }
    });

    Garnish.$doc.ready(function() {
        new EditWebhook();
    });

})(jQuery);
