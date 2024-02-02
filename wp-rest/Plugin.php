<?php
/**
 * Main plugin class.
 *
 * @since 5.25
 */

namespace CiviCRM_WP_REST;

use CiviCRM_WP_REST\Civi\Mailing_Hooks;

class Plugin {

  /**
   * Constructor.
   *
   * @since 5.25
   */
  public function __construct() {

    $this->register_hooks();

    $this->setup_objects();

  }

  /**
   * Register hooks.
   *
   * @since 5.25
   */
  protected function register_hooks() {

    add_action('rest_api_init', [$this, 'register_rest_routes']);

    add_filter('rest_pre_dispatch', [$this, 'bootstrap_civi'], 10, 3);

    add_filter('rest_post_dispatch', [$this, 'maybe_reset_php_timezone'], 10, 3);

  }

  /**
   * Bootstrap CiviCRM when hitting a the 'civicrm' namespace.
   *
   * @since 5.25
   *
   * @param mixed $result
   * @param WP_REST_Server $server REST server instance.
   * @param WP_REST_Request $request The request.
   * @return mixed $result
   */
  public function bootstrap_civi($result, $server, $request) {

    if (FALSE !== strpos($request->get_route(), 'civicrm')) {

      $this->maybe_set_php_timezone($request);

      civi_wp()->initialize();

      // rest calls need a wp user, do login
      if (FALSE !== strpos($request->get_route(), 'rest')) {

        $logged_in_wp_user = $this->do_user_login($request);

        // Return error.
        if (is_wp_error($logged_in_wp_user)) {
          return $logged_in_wp_user;
        }
      }

    }

    return $result;

  }

  /**
   * Setup objects.
   *
   * @since 5.25
   */
  private function setup_objects() {

    /**
      * Filter to replace the mailing tracking URLs.
      *
      * @since 5.25
      *
      * @param bool $replace_mailing_tracking_urls
      */
    $replace_mailing_tracking_urls = apply_filters('civi_wp_rest/plugin/replace_mailing_tracking_urls', FALSE);

    // Keep CIVICRM_WP_REST_REPLACE_MAILING_TRACKING for backwards compatibility.
    if (
      $replace_mailing_tracking_urls
      || (defined('CIVICRM_WP_REST_REPLACE_MAILING_TRACKING')
      && CIVICRM_WP_REST_REPLACE_MAILING_TRACKING)
    ) {
      // Register mailing hooks.
      $mailing_hooks = (new Mailing_Hooks)->register_hooks();

    }

  }

  /**
   * Registers Rest API routes.
   *
   * @since 5.25
   */
  public function register_rest_routes() {

    // Rest endpoint.
    $rest_controller = new Controller\Rest();
    $rest_controller->register_routes();

    // URL controller.
    $url_controller = new Controller\Url();
    $url_controller->register_routes();

    // Open controller.
    $open_controller = new Controller\Open();
    $open_controller->register_routes();

    // AuthorizeNet controller.
    $authorizeIPN_controller = new Controller\AuthorizeIPN();
    $authorizeIPN_controller->register_routes();

    // PayPal controller.
    $paypalIPN_controller = new Controller\PayPalIPN();
    $paypalIPN_controller->register_routes();

    // PxPay controller.
    $paypalIPN_controller = new Controller\PxIPN();
    $paypalIPN_controller->register_routes();

    // CiviConnect controller.
    $cxn_controller = new Controller\Cxn();
    $cxn_controller->register_routes();

    // Widget controller.
    $widget_controller = new Controller\Widget();
    $widget_controller->register_routes();

    /**
     * Opportunity to add more rest routes.
     *
     * @since 5.25
     */
    do_action('civi_wp_rest/plugin/rest_routes_registered');

  }

  /**
   * Sets the PHP timezone to the timezone of the WordPress site when calling
   * the civicrm/v3/rest endpoint.
   *
   * @since 5.25
   *
   * @param WP_REST_Request $request The request.
   */
  private function maybe_set_php_timezone($request) {

    if ($request->get_route() != '/civicrm/v3/rest') {
      return;
    }

    $timezones = [
      'original_timezone' => date_default_timezone_get(),
      'site_timezone' => $this->get_timezone_string(),
    ];

    // Filter timezones - retrieved in `maybe_reset_php_timezone()` below.
    add_filter('civi_wp_rest/plugin/timezones', function() use ($timezones) {
      return $timezones;
    });

    if (empty($timezones['site_timezone'])) {
      return;
    }

    /*
     * CRM-12523
     * CRM-18062
     * CRM-19115
     */
    date_default_timezone_set($timezones['site_timezone']);
    \CRM_Core_Config::singleton()->userSystem->setMySQLTimeZone();

  }

  /**
   * Resets the PHP timezone to the original timezone after calling the
   * civicrm/v3/rest endpoint.
   *
   * @since 5.25
   *
   * @param mixed $result
   * @param WP_REST_Server $server REST server instance.
   * @param WP_REST_Request $request The request.
   * @return mixed $result
   */
  public function maybe_reset_php_timezone($result, $server, $request) {

    if ($request->get_route() != '/civicrm/v3/rest') {
      return $result;
    }

    /**
     * Filters this plugin's timezones.
     *
     * This is actually just a neat way to retrieve the values assigned to
     * the `$timezones` array in `maybe_set_php_timezone()` above.
     *
     * @since 5.25
     *
     * @param null Passes `null` because return will be populated.
     */
    $timezones = apply_filters('civi_wp_rest/plugin/timezones', NULL);

    if (empty($timezones['original_timezone'])) {
      return $result;
    }

    // Reset original timezone.
    date_default_timezone_set($timezones['original_timezone']);

    return $result;

  }

  /**
   * Returns the timezone string for the current site.
   *
   * If a timezone identifier is used, return that.
   * If an offset is used, try to build a suitable timezone.
   * If all else fails, uses UTC.
   *
   * @since 5.64
   *
   * @return string $tzstring The site timezone string.
   */
  private function get_timezone_string() {

    // Return the timezone string when set.
    $tzstring = get_option('timezone_string');
    if (!empty($tzstring)) {
      return $tzstring;
    }

    /*
     * Try and build a deprecated (but currently valid) timezone string
     * from the GMT offset value.
     *
     * Note: manual offsets should be discouraged. WordPress works more
     * reliably when setting an actual timezone (e.g. "Europe/London")
     * because of support for Daylight Saving changes.
     *
     * Note: the IANA timezone database that provides PHP's timezone
     * support uses (reversed) POSIX style signs.
     *
     * @see https://www.php.net/manual/en/timezones.others.php
     */
    $offset = get_option('gmt_offset');
    if (0 != $offset && floor($offset) == $offset) {
      $offset_string = $offset > 0 ? "-$offset" : '+' . absint($offset);
      $tzstring = 'Etc/GMT' . $offset_string;
    }

    // Default to "UTC" if the timezone string is still empty.
    if (empty($tzstring)) {
      $tzstring = 'UTC';
    }

    return $tzstring;

  }

  /**
   * Performs the necessary checks and data retrieval to login a WordPress user.
   *
   * @since 5.25
   *
   * @param \WP_REST_Request $request The request.
   * @return \WP_User|\WP_Error|void $logged_in_wp_user The logged in WordPress user object, \Wp_Error, or nothing.
   */
  public function do_user_login($request) {

    /**
     * Filter and opportunity to bypass the default user login.
     *
     * @since 5.25
     *
     * @param bool $login
     */
    $logged_in = apply_filters('civi_wp_rest/plugin/do_user_login', FALSE, $request);

    if ($logged_in) {
      return;
    }

    // Default login based on Contact's api_key.
    if (!(new Controller\Rest)->is_valid_api_key($request)) {
      return new \WP_Error(
        'civicrm_rest_api_error',
        __('Missing or invalid param "api_key".', 'civicrm')
      );
    }

    $contact_id = \CRM_Core_DAO::getFieldValue(
      'CRM_Contact_DAO_Contact',
      $request->get_param('api_key'),
      'id',
      'api_key'
    );

    $wp_user = $this->get_wp_user($contact_id);

    if (is_wp_error($wp_user)) {
      return $wp_user;
    }

    return $this->login_wp_user($wp_user, $request);

  }

  /**
   * Get WordPress user data.
   *
   * @since 5.25
   *
   * @param int $contact_id The Contact ID.
   * @return WP_User|WP_Error $user The WordPress user data or WP_Error object.
   */
  public function get_wp_user(int $contact_id) {

    try {

      // Call API.
      $uf_match = civicrm_api3('UFMatch', 'getsingle', [
        'contact_id' => $contact_id,
        'domain_id' => $this->get_civi_domain_id(),
      ]);

    }
    catch (\CRM_Core_Exception $e) {

      return new \WP_Error(
        'civicrm_rest_api_error',
        __('A WordPress user must be associated with the contact for the provided API key.', 'civicrm')
      );

    }

    // Filter uf_match.
    add_filter('civi_wp_rest/plugin/uf_match', function() use ($uf_match) {

      return !empty($uf_match) ? $uf_match : NULL;

    });

    return get_userdata($uf_match['uf_id']);

  }

  /**
   * Logs in the WordPress user, and syncs it with it's CiviCRM Contact.
   *
   * @since 5.25
   *
   * @param \WP_User $wp_user The WordPress user object.
   * @param \WP_REST_Request|NULL $request The request object or NULL.
   * @return \WP_User|void $wp_user The logged in WordPress user object or nothing.
   */
  public function login_wp_user(\WP_User $wp_user, $request = NULL) {

    /**
     * Filter the user about to be logged in.
     *
     * @since 5.25
     *
     * @param \WP_User $user The WordPress user object
     * @param \WP_REST_Request|NULL $request The request object or NULL
     */
    $wp_user = apply_filters('civi_wp_rest/plugin/wp_user_login', $wp_user, $request);

    wp_set_current_user($wp_user->ID, $wp_user->user_login);

    wp_set_auth_cookie($wp_user->ID);

    do_action('wp_login', $wp_user->user_login, $wp_user);

    $this->set_civi_user_session($wp_user);

    return $wp_user;

  }

  /**
   * Sets the necessary user session variables for CiviCRM.
   *
   * @since 5.25
   *
   * @param \WP_User $wp_user The WordPress user.
   */
  public function set_civi_user_session($wp_user): void {

    $uf_match = apply_filters('civi_wp_rest/plugin/uf_match', NULL);

    if (!$uf_match) {

      // Call API.
      $uf_match = civicrm_api3('UFMatch', 'getsingle', [
        'uf_id' => $wp_user->ID,
        'domain_id' => $this->get_civi_domain_id(),
      ]);
    }

    // Set necessary session variables.
    $session = \CRM_Core_Session::singleton();
    $session->set('ufID', $wp_user->ID);
    $session->set('userID', $uf_match['contact_id']);

  }

  /**
   * Retrieves the CiviCRM domain_id.
   *
   * @since 5.25
   *
   * @return int $domain_id The Domain ID.
   */
  public function get_civi_domain_id(): int {

    // Get CiviCRM domain group ID from constant, if set.
    $domain_id = defined('CIVICRM_DOMAIN_ID') ? CIVICRM_DOMAIN_ID : 0;

    // If this fails, get it from config.
    if ($domain_id === 0) {
      $domain_id = \CRM_Core_Config::domainID();
    }

    return $domain_id;

  }

}
