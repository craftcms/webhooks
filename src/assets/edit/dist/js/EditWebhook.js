(function ($) {
  /** global: Craft */
  /** global: Garnish */
  const EditWebhook = Garnish.Base.extend({
    $nameInput: null,
    $classInput: null,
    $eventInput: null,
    $filterSpinner: null,
    $noFiltersMessage: null,
    $filtersTable: null,
    filters: null,
    matchingFilters: null,

    classVal: null,
    eventVal: null,
    filterTimeout: null,

    init: function () {
      this.$nameInput = $('#name');
      this.$classInput = $('#class');
      this.$eventInput = $('#event');
      this.$filterSpinner = $('#filter-spinner');
      this.$noFiltersMessage = $('#no-filters');
      this.$filtersTable = $('#filters');

      this.filters = {};
      this.matchingFilters = [];

      const $filterRows = this.$filtersTable.find('tr');
      for (let i = 0; i < $filterRows.length; i++) {
        const filter = new EditWebhook.Filter(this, $filterRows.eq(i));
        this.filters[filter.class] = filter;
        if (!filter.$tr.hasClass('hidden')) {
          this.matchingFilters.push(filter);
        }
      }

      this.applyExclusions();

      this.addListener(this.$nameInput, 'change, keyup', 'handleTextChange');
      this.addListener(
        this.$classInput,
        'change, keyup, blur',
        'handleEventChange'
      );
      this.addListener(
        this.$eventInput,
        'change, keyup, blur',
        'handleEventChange'
      );
    },

    handleTextChange: function () {
      const input = this.$nameInput.get(0);

      // does it look like they just typed -> or => ?
      if (
        typeof input.selectionStart !== 'undefined' &&
        input.selectionStart === input.selectionEnd
      ) {
        const pos = input.selectionStart;
        const last2 = input.value.substring(pos - 2, pos);
        if (last2 === '->' || last2 === '=>') {
          input.value =
            input.value.substring(0, pos - 2) +
            '➡️' +
            input.value.substring(pos);
          input.setSelectionRange(pos, pos);
        }
      }
    },

    handleEventChange: function () {
      const classChanged =
        this.classVal !== (this.classVal = this.$classInput.val());
      const eventChanged =
        this.eventVal !== (this.eventVal = this.$eventInput.val());
      if (classChanged || eventChanged) {
        clearTimeout(this.filterTimeout);
        this.filterTimeout = setTimeout(this.updateFilters.bind(this), 500);
      }
    },

    updateFilters: function () {
      if (!this.classVal || !this.eventVal) {
        return;
      }
      this.$filterSpinner.removeClass('hidden');
      Craft.sendActionRequest('POST', 'webhooks/webhooks/filters', {
        data: {
          senderClass: this.classVal,
          event: this.eventVal,
        },
      })
        .then((response) => {
          this.resetFilters();
          if (response.data.filters.length) {
            this.$noFiltersMessage.addClass('hidden');
            this.$filtersTable.removeClass('hidden');
            for (let i = 0; i < response.data.filters.length; i++) {
              const filter = this.filters[response.data.filters[i]];
              this.matchingFilters.push(filter);
              filter.enable();
              filter.$tr.removeClass('hidden');
            }
            this.applyExclusions();
          } else {
            this.$noFiltersMessage.removeClass('hidden');
            this.$filtersTable.addClass('hidden');
          }
        })
        .finally(() => {
          this.$filterSpinner.addClass('hidden');
        });
    },

    resetFilters: function () {
      this.matchingFilters = [];
      for (let filter in this.filters) {
        if (!this.filters.hasOwnProperty(filter)) {
          continue;
        }
        this.filters[filter].$tr.addClass('hidden');
        this.filters[filter].selectIgnore(false);
      }
    },

    applyExclusions: function () {
      this.matchingFilters.forEach((f) => {
        f.enable();
      });
      this.matchingFilters
        .filter((f) => f.value === true)
        .forEach((f) => {
          f.excludes.forEach((e) => {
            this.filters[e].disable();
          });
        });
    },
  });

  EditWebhook.Filter = Garnish.Base.extend({
    manager: null,
    $tr: null,
    $input: null,
    class: null,
    $btnGroup: null,
    $noBtn: null,
    $ignoreBtn: null,
    $yesBtn: null,
    value: null,
    enabled: false,

    init: function (manager, $tr) {
      this.manager = manager;
      this.$tr = $tr;
      this.class = $tr.data('class');
      this.$btnGroup = this.$tr.find('.btngroup');
      this.$noBtn = this.$btnGroup.find('.filter-no');
      this.$ignoreBtn = this.$btnGroup.find('.filter-ignore');
      this.$yesBtn = this.$btnGroup.find('.filter-yes');
      this.$input = $tr.find('input');

      switch (this.$input.val()) {
        case 'yes':
          this.selectYes();
          break;
        case 'no':
          this.selectNo();
          break;
      }

      this.enable();
    },

    /**
     * @returns {string[]}
     */
    get excludes() {
      return this.$tr.data('excludes');
    },

    enable: function () {
      if (!this.enabled) {
        this.addListener(this.$btnGroup, 'keydown', 'handleKeydown');
        this.addListener(this.$noBtn, 'click', 'selectNo');
        this.addListener(this.$ignoreBtn, 'click', 'selectIgnore');
        this.addListener(this.$yesBtn, 'click', 'selectYes');
        this.$tr.removeClass('disabled');
        this.$btnGroup.attr('tabindex', '0');
        this.enabled = true;
      }
    },

    disable: function () {
      if (this.enabled) {
        this.selectIgnore(false);
        this.removeAllListeners(this.$btnGroup);
        this.removeAllListeners(this.$noBtn);
        this.removeAllListeners(this.$ignoreBtn);
        this.removeAllListeners(this.$yesBtn);
        this.$tr.addClass('disabled');
        this.$btnGroup.attr('tabindex', '-1');
        this.enabled = false;
      }
    },

    /**
     * @param {boolean|null} value
     * @param {boolean} [applyExclusions=true]
     */
    setValue: function (value, applyExclusions) {
      this.value = value;
      if (applyExclusions !== false) {
        this.manager.applyExclusions();
      }
    },

    /**
     * @param {boolean} [applyExclusions=true]
     */
    selectNo: function (applyExclusions) {
      this.clear();
      this.$noBtn.addClass('active');
      this.setValue(false, applyExclusions);
      this.$input.val('no');
    },

    /**
     * @param {boolean} [applyExclusions=true]
     */
    selectIgnore: function (applyExclusions) {
      this.clear();
      this.$ignoreBtn.addClass('active');
      this.setValue(null, applyExclusions);
      this.$input.val('');
    },

    /**
     * @param {boolean} [applyExclusions=true]
     */
    selectYes: function (applyExclusions) {
      this.clear();
      this.$yesBtn.addClass('active');
      this.setValue(true, applyExclusions);
      this.$input.val('yes');
    },

    clear: function () {
      this.$yesBtn.removeClass('active');
      this.$ignoreBtn.removeClass('active');
      this.$noBtn.removeClass('active');
    },

    handleKeydown: function (ev) {
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
    },
  });

  Garnish.$doc.ready(function () {
    new EditWebhook();
  });
})(jQuery);
