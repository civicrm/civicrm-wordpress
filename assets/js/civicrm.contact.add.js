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
 * CiviCRM "Contact Quick Add" Javascript.
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
      if ('undefined' !== typeof CiviCRM_Quick_Add_Vars) {
        me.localisation = CiviCRM_Quick_Add_Vars.localisation;
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
      if ('undefined' !== typeof CiviCRM_Quick_Add_Vars) {
        me.settings = CiviCRM_Quick_Add_Vars.settings;
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
   * Create Quick Add class.
   *
   * @since 5.34
   */
  function CRM_Quick_Add() {

    // Prevent reference collisions.
    var me = this;

    /**
     * Initialise Quick Add.
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

      // Assign properties.
      me.quick_add_submit = $('#civicrm_quick_add_submit');
      //me.quick_add_submit.prop('disabled', true);

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
       * Add a click event listener to the "Clear Caches" section submit button.
       *
       * @param {Object} event The event object.
       */
      me.quick_add_submit.on('click', function(event) {

        // Define vars.
        var ajax_nonce = me.quick_add_submit.data('security'),
            adding = CiviCRM_Options_Settings.get_localisation('adding'),
            value, first_name, last_name, email;

        // Prevent form submission.
        if (event.preventDefault) {
          event.preventDefault();
        }

        // Modify button and select, then show spinner.
        me.quick_add_submit.val(adding);
        me.quick_add_submit.prop('disabled', true);
        $(this).next('.spinner').css('visibility', 'visible');

        // Read form data.
        first_name = $('#civicrm_quick_add_first_name').val();
        last_name = $('#civicrm_quick_add_last_name').val();
        email = $('#civicrm_quick_add_email').val();

        // Gather form data.
        value = {
          'first_name': first_name,
          'last_name': last_name,
          'email': email,
        };

        console.log('value', value);

        // Submit request to server.
        me.send('civicrm_contact_add', value, ajax_nonce);

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

      console.log('url', url);

      // Data received by WordPress.
      data = {
        action: action,
        value: value,
        _ajax_nonce: token
      };

      console.log('data OUT', data);

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

      console.log('data IN', data);

      // Declare vars.
      var add = CiviCRM_Options_Settings.get_localisation('add');

      if (data.saved) {

        // Success!
        me.quick_add_submit.val(add);
        $('.civicrm_quick_add_error').hide();
        $('.civicrm_quick_add_success').show();
        $('.civicrm_quick_add_success p').html(data.notice);
        me.quick_add_submit.prop('disabled', false);
        me.quick_add_submit.next('.spinner').css('visibility', 'hidden');

        // Clear form data.
        $('#civicrm_quick_add_first_name').val('');
        $('#civicrm_quick_add_last_name').val('');
        $('#civicrm_quick_add_email').val('');

        // Show "Recently Added".
        $('.contacts-added-wrap').show();

        // Add Contact link to top of list.
        $('.contacts-added-list').prepend(data.link);

        // Drop last item when list exceeds 5 items.
        if ($('.contacts-added-list li').length > 5) {
          $('.contacts-added-list li:last-child').remove();
        }


      } else {

        // Failure.
        me.quick_add_submit.val(add);
        me.quick_add_submit.next('.spinner').css('visibility', 'hidden');
        me.quick_add_submit.prop('disabled', false);
        $('.civicrm_quick_add_success').hide();
        $('.civicrm_quick_add_error').show();
        $('.civicrm_quick_add_error p').html(data.notice);

      }

    };

  }

  // Init Settings and Quick Add classes.
  var CiviCRM_Options_Settings = new CRM_Settings();
  var CiviCRM_Options_Quick_Add = new CRM_Quick_Add();
  CiviCRM_Options_Settings.init();
  CiviCRM_Options_Quick_Add.init();

  /**
   * Trigger dom_ready methods where necessary.
   *
   * @since 5.34
   *
   * @param {Object} $ The jQuery object.
   */
  $(document).ready(function($) {
    CiviCRM_Options_Settings.dom_ready();
    CiviCRM_Options_Quick_Add.dom_ready();
  }); // End document.ready()

} )( jQuery );

