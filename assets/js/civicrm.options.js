/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

/**
 * CiviCRM "Settings" Javascript.
 *
 * Implements functionality on the CiviCRM "Settings" page.
 *
 * @since 5.34
 */

/**
 * Pass the jQuery shortcut in.
 *
 * @since 5.34
 *
 * @param {Object} $ The jQuery object.
 */
(function($) {

  /**
   * Create Settings class.
   *
   * @since 5.34
   */
  function CRM_Settings() {

    // Prevent reference collisions.
    var me = this;

    /**
     * Initialise Settings.
     *
     * This method should only be called once.
     *
     * @since 5.34
     */
    this.init = function() {

      // Init localisation.
      me.init_localisation();

      // Init settings.
      me.init_settings();

    };

    /**
     * Do setup when jQuery reports that the DOM is ready.
     *
     * This method should only be called once.
     *
     * @since 5.34
     */
    this.dom_ready = function() {

    };

    // Init localisation array.
    me.localisation = [];

    /**
     * Init localisation from settings object.
     *
     * @since 5.34
     */
    this.init_localisation = function() {
      if ('undefined' !== typeof CiviCRM_Options_Vars) {
        me.localisation = CiviCRM_Options_Vars.localisation;
      }
    };

    /**
     * Getter for localisation.
     *
     * @since 5.34
     *
     * @param {String} identifier The identifier for the desired localisation string.
     * @return {String} The localised string.
     */
    this.get_localisation = function(identifier) {
      return me.localisation[identifier];
    };

    // Init settings array.
    me.settings = [];

    /**
     * Init settings from settings object.
     *
     * @since 5.34
     */
    this.init_settings = function() {
      if ('undefined' !== typeof CiviCRM_Options_Vars) {
        me.settings = CiviCRM_Options_Vars.settings;
      }
    };

    /**
     * Getter for retrieving a setting.
     *
     * @since 5.34
     *
     * @param {String} The identifier for the desired setting.
     * @return The value of the setting.
     */
    this.get_setting = function(identifier) {
      return me.settings[identifier];
    };

  }

  /**
   * Create Buttons class.
   *
   * @since 5.34
   */
  function CRM_Buttons() {

    // Prevent reference collisions.
    var me = this;

    /**
     * Initialise Buttons.
     *
     * This method should only be called once.
     *
     * @since 5.34
     */
    this.init = function() {};

    /**
     * Do setup when jQuery reports that the DOM is ready.
     *
     * This method should only be called once.
     *
     * @since 5.34
     */
    this.dom_ready = function() {

      // Set up methods.
      me.setup();
      me.listeners();

    };

    /**
     * Do initial setup.
     *
     * This method should only be called once.
     *
     * @since 5.34
     */
    this.setup = function() {

      // Get "Saved" text.
      var text = CiviCRM_Options_Settings.get_localisation('saved');

      // Assign properties.
      me.basepage_submit = $('#civicrm_basepage_submit');
      me.shortcode_submit = $('#civicrm_shortcode_submit');
      me.email_submit = $('#civicrm_email_submit');
      me.permissions_submit = $('#civicrm_permissions_submit');
      me.cache_submit = $('#civicrm_cache_submit');
      me.basepage_select = $('#page_id');
      me.shortcode_select = $('#shortcode_mode');
      me.email_select = $('#sync_email');
      me.permissions_select = $('#permissions_role');
      me.basepage_selected = me.basepage_select.val();
      me.shortcode_selected = me.shortcode_select.val();
      me.email_selected = me.email_select.val();

      // Set status of Base Page submit button.
      me.basepage_submit.prop('disabled', true);
      if (me.basepage_select !== '') {
        me.basepage_submit.val(text);
      }

      // Set status of Shortcode Mode submit button.
      me.shortcode_submit.val(text);
      me.shortcode_submit.prop('disabled', true);

      // Set status of Email Sync submit button.
      me.email_submit.val(text);
      me.email_submit.prop('disabled', true);

    };

    /**
     * Initialise listeners.
     *
     * This method should only be called once.
     *
     * @since 5.34
     */
    this.listeners = function() {

      /**
       * Add an onchange event listener to the "Basepage" section select.
       *
       * @param {Object} event The event object.
       */
      me.basepage_select.on('change', function(event) {

        var text;

        // Disable submit button if nothing selected.
        if (!me.basepage_select.val()) {
          text = CiviCRM_Options_Settings.get_localisation('update');
          me.basepage_submit.val(text);
          me.basepage_submit.prop('disabled', true);
          return;
        }

        // Enable/disable submit button.
        if (me.basepage_select.val() == me.basepage_selected) {
          text = CiviCRM_Options_Settings.get_localisation('saved');
          me.basepage_submit.val(text);
          me.basepage_submit.prop('disabled', true);
        } else {
          text = CiviCRM_Options_Settings.get_localisation('update');
          me.basepage_submit.val(text);
          me.basepage_submit.prop('disabled', false);
        }

      });

      /**
       * Add an onchange event listener to the "Shortcode Mode" section select.
       *
       * @param {Object} event The event object.
       */
      me.shortcode_select.on('change', function(event) {

        var text;

        // Enable/disable submit button.
        if (me.shortcode_select.val() == me.shortcode_selected) {
          text = CiviCRM_Options_Settings.get_localisation('saved');
          me.shortcode_submit.val(text);
          me.shortcode_submit.prop('disabled', true);
        } else {
          text = CiviCRM_Options_Settings.get_localisation('update');
          me.shortcode_submit.val(text);
          me.shortcode_submit.prop('disabled', false);
        }

      });

      /**
       * Add an onchange event listener to the "Email Sync" section select.
       *
       * @param {Object} event The event object.
       */
      me.email_select.on('change', function(event) {

        var text;

        // Enable/disable submit button.
        if (me.email_select.val() == me.email_selected) {
          text = CiviCRM_Options_Settings.get_localisation('saved');
          me.email_submit.val(text);
          me.email_submit.prop('disabled', true);
        } else {
          text = CiviCRM_Options_Settings.get_localisation('update');
          me.email_submit.val(text);
          me.email_submit.prop('disabled', false);
        }

      });

      /**
       * Add a click event listener to the "Basepage" section submit button.
       *
       * @param {Object} event The event object.
       */
      me.basepage_submit.on('click', function(event) {

        // Define vars.
        var value = me.basepage_select.val(),
            ajax_nonce = me.basepage_submit.data('security'),
            saving = CiviCRM_Options_Settings.get_localisation('saving');

        // Prevent form submission.
        if (event.preventDefault) {
          event.preventDefault();
        }

        // Modify button and select, then show spinner.
        me.basepage_submit.val(saving);
        me.basepage_submit.prop('disabled', true);
        me.basepage_select.prop('disabled', true);
        $(this).next('.spinner').css('visibility', 'visible');

        // Submit setting to server.
        me.send('civicrm_basepage', value, ajax_nonce);

      });

      /**
       * Add a click event listener to the "Shortcode Mode" section submit button.
       *
       * @param {Object} event The event object.
       */
      me.shortcode_submit.on('click', function(event) {

        // Define vars.
        var value = me.shortcode_select.val(),
            ajax_nonce = me.shortcode_submit.data('security'),
            saving = CiviCRM_Options_Settings.get_localisation('saving');

        // Prevent form submission.
        if (event.preventDefault) {
          event.preventDefault();
        }

        // Modify button and select, then show spinner.
        me.shortcode_submit.val(saving);
        me.shortcode_submit.prop('disabled', true);
        me.shortcode_select.prop('disabled', true);
        $(this).next('.spinner').css('visibility', 'visible');

        // Submit setting to server.
        me.send('civicrm_shortcode', value, ajax_nonce);

      });

      /**
       * Add a click event listener to the "Email Sync" section submit button.
       *
       * @param {Object} event The event object.
       */
      me.email_submit.on('click', function(event) {

        // Define vars.
        var value = me.email_select.val(),
            ajax_nonce = me.email_submit.data('security'),
            saving = CiviCRM_Options_Settings.get_localisation('saving');

        // Prevent form submission.
        if (event.preventDefault) {
          event.preventDefault();
        }

        // Modify button and select, then show spinner.
        me.email_submit.val(saving);
        me.email_submit.prop('disabled', true);
        me.email_select.prop('disabled', true);
        $(this).next('.spinner').css('visibility', 'visible');

        // Submit setting to server.
        me.send('civicrm_email_sync', value, ajax_nonce);

      });

      /**
       * Add a click event listener to the "Permissions and Capabilities" section submit button.
       *
       * @param {Object} event The event object.
       */
      me.permissions_submit.on('click', function(event) {

        // Define vars.
        var value = me.permissions_select.val(),
            ajax_nonce = me.permissions_submit.data('security'),
            refreshing = CiviCRM_Options_Settings.get_localisation('refreshing');

        // Prevent form submission.
        if (event.preventDefault) {
          event.preventDefault();
        }

        // Modify button and select, then show spinner.
        me.permissions_submit.val(refreshing);
        me.permissions_submit.prop('disabled', true);
        me.permissions_select.prop('disabled', true);
        $(this).next('.spinner').css('visibility', 'visible');

        // Submit request to server.
        me.send('civicrm_refresh_permissions', value, ajax_nonce);

      });

      /**
       * Add a click event listener to the "Clear Caches" section submit button.
       *
       * @param {Object} event The event object.
       */
      me.cache_submit.on('click', function(event) {

        // Define vars.
        var ajax_nonce = me.cache_submit.data('security'),
            clearing = CiviCRM_Options_Settings.get_localisation('clearing');

        // Prevent form submission.
        if (event.preventDefault) {
          event.preventDefault();
        }

        // Modify button and select, then show spinner.
        me.cache_submit.val(clearing);
        me.cache_submit.prop('disabled', true);
        $(this).next('.spinner').css('visibility', 'visible');

        // Submit request to server.
        me.send('civicrm_clear_caches', 1, ajax_nonce);

      });

    };

    /**
     * Send AJAX request.
     *
     * @since 5.34
     *
     * @param {String} action The AJAX action.
     * @param {Mixed} value The value to send.
     * @param {String} token The AJAX security token.
     */
    this.send = function(action, value, token) {

      // Define vars.
      var url, data;

      // URL to post to.
      url = CiviCRM_Options_Settings.get_setting('ajax_url');

      // Data received by WordPress.
      data = {
        action: action,
        value: value,
        _ajax_nonce: token
      };

      // Use jQuery post method.
      $.post( url, data,

        /**
         * AJAX callback which receives response from the server.
         *
         * Calls feedback method on success or shows an error in the console.
         *
         * @since 5.34
         *
         * @param {Mixed} value The value to send.
         * @param {String} token The AJAX security token.
         */
        function(data, textStatus) {
          if (textStatus == 'success') {
            me.feedback(data);
          } else {
            if (console.log) {
              console.log(textStatus);
            }
          }
        },

        // Expected format.
        'json'

      );

    };

    /**
     * Provide feedback given a set of data from the server.
     *
     * @since 5.34
     *
     * @param {Array} data The data received from the server.
     */
    this.feedback = function(data) {

      // Declare vars.
      var saved = CiviCRM_Options_Settings.get_localisation('saved'),
          update = CiviCRM_Options_Settings.get_localisation('update'),
          clearing = CiviCRM_Options_Settings.get_localisation('clearing'),
          refresh = CiviCRM_Options_Settings.get_localisation('refresh'),
          cache = CiviCRM_Options_Settings.get_localisation('cache');

      if (data.saved) {

		// Success!
        if (data.section == 'basepage') {

          // Base Page section.
          me.basepage_submit.val(saved);
          me.basepage_submit.next('.spinner').css('visibility', 'hidden');
          me.basepage_select.val(data.result);
          me.basepage_select.prop('disabled', false);
          if (me.basepage_selected == '' && typeof data.result === 'number') {
            $('.basepage_notice').hide();
            $('.basepage_feedback').html(data.message);
          }
          me.basepage_selected = data.result;

        } else if (data.section == 'shortcode') {

          // Shortcode Mode section.
          me.shortcode_submit.val(saved);
          me.shortcode_submit.next('.spinner').css('visibility', 'hidden');
          me.shortcode_select.val(data.result);
          me.shortcode_selected = data.result;
          me.shortcode_select.prop('disabled', false);

        } else if (data.section == 'email_sync') {

          // Email Sync section.
          me.email_submit.val(saved);
          me.email_submit.next('.spinner').css('visibility', 'hidden');
          me.email_select.val(data.result);
          me.email_selected = data.result;
          me.email_select.prop('disabled', false);

        } else if (data.section == 'refresh_permissions') {

          // Permissions and Capabilities section.
          me.permissions_submit.val(refresh);
          $('.permissions_error').hide();
          $('.permissions_success').show();
          $('.permissions_success p').html(data.notice);
          me.permissions_select.prop('disabled', false);
          me.permissions_submit.prop('disabled', false);
          me.permissions_submit.next('.spinner').css('visibility', 'hidden');

        } else if (data.section == 'clear_caches') {

          // Clear Caches section.
          me.cache_submit.val(cache);
          $('.caches_error').hide();
          $('.caches_success').show();
          $('.caches_success p').html(data.notice);
          me.cache_submit.prop('disabled', false);
          me.cache_submit.next('.spinner').css('visibility', 'hidden');

        }

      } else {

		// Failure.
        if (data.section == 'basepage') {

          // Base Page section.
          me.basepage_submit.val(update);
          me.basepage_submit.next('.spinner').css('visibility', 'hidden');
          me.basepage_select.val(data.result);
          me.basepage_select.prop('disabled', false);
          $('.basepage_notice').show();
          $('.basepage_notice p').html(data.notice);
          $('.basepage_feedback').html(data.message);
          me.basepage_selected = data.result;

        } else if (data.section == 'shortcode') {

          // Shortcode Mode section.
          me.shortcode_submit.val(update);
          me.shortcode_submit.next('.spinner').css('visibility', 'hidden');
          me.shortcode_select.val(data.result);
          me.shortcode_select.prop('disabled', false);
          $('.shortcode_notice').show();
          $('.shortcode_notice p').html(data.notice);
          $('.shortcode_feedback').html(data.message);
          me.shortcode_selected = data.result;

        } else if (data.section == 'email_sync') {

          // Email Sync section.
          me.email_submit.val(update);
          me.email_submit.next('.spinner').css('visibility', 'hidden');
          me.email_select.val(data.result);
          me.email_select.prop('disabled', false);
          $('.email_notice').show();
          $('.email_notice p').html(data.notice);
          $('.email_feedback').html(data.message);
          me.email_selected = data.result;

        } else if (data.section == 'refresh_permissions') {

          // Permissions and Capabilities section.
          me.permissions_submit.val(refresh);
          me.permissions_submit.next('.spinner').css('visibility', 'hidden');
          me.permissions_submit.prop('disabled', false);
          $('.permissions_success').hide();
          $('.permissions_error').show();
          $('.permissions_error p').html(data.notice);

        } else if (data.section == 'clear_caches') {

          // Clear Caches section.
          me.cache_submit.val(cache);
          me.cache_submit.next('.spinner').css('visibility', 'hidden');
          me.cache_submit.prop('disabled', false);
          $('.caches_success').hide();
          $('.caches_error').show();
          $('.caches_error p').html(data.notice);

        }

      }

    };

  }

  // Init Settings and Buttons classes.
  var CiviCRM_Options_Settings = new CRM_Settings();
  var CiviCRM_Options_Buttons = new CRM_Buttons();
  CiviCRM_Options_Settings.init();
  CiviCRM_Options_Buttons.init();

  /**
   * Trigger dom_ready methods where necessary.
   *
   * @since 5.34
   *
   * @param {Object} $ The jQuery object.
   */
  $(document).ready(function($) {
    CiviCRM_Options_Settings.dom_ready();
    CiviCRM_Options_Buttons.dom_ready();
  }); // End document.ready()

} )( jQuery );

