<?php
/**
 * Main plugin class.
 *
 * @since 0.1
 */

namespace CiviCRM_WP_REST;

use CiviCRM_WP_REST\Civi\Mailing_Hooks;

class Plugin {

  /**
   * Constructor.
   *
   * @since 0.1
   */
  public function __construct() {

    $this->register_hooks();

    $this->setup_objects();

  }

  /**
   * Register hooks.
   *
   * @since 1.0
   */
  protected function register_hooks() {

    add_action('rest_api_init', [$this, 'register_rest_routes']);

    add_filter('rest_pre_dispatch', [$this, 'bootstrap_civi'], 10, 3);

    add_filter('rest_post_dispatch', [$this, 'maybe_reset_wp_timezone'], 10, 3);

  }

  /**
   * Bootstrap CiviCRM when hitting a the 'civicrm' namespace.
   *
   * @since 0.1
   * @param mixed $result
   * @param WP_REST_Server $server REST server instance
   * @param WP_REST_Request $request The request
   * @return mixed $result
   */
  public function bootstrap_civi($result, $server, $request) {

    if (FALSE !== strpos($request->get_route(), 'civicrm')) {

      $this->maybe_set_user_timezone($request);

      civi_wp()->initialize();

      // rest calls need a wp user, do login
      if (FALSE !== strpos($request->get_route(), 'rest')) {

        $logged_in_wp_user = $this->do_user_login($request);

        // return error
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
   * @since 0.1
   */
  private function setup_objects() {

    /**
      * Filter to replace the mailing tracking URLs.
      *
      * @since 0.1
      * @param bool $replace_mailing_tracking_urls
      */
    $replace_mailing_tracking_urls = apply_filters('civi_wp_rest/plugin/replace_mailing_tracking_urls', FALSE);

    // keep CIVICRM_WP_REST_REPLACE_MAILING_TRACKING for backwards compatibility
    if (
      $replace_mailing_tracking_urls
      || (defined('CIVICRM_WP_REST_REPLACE_MAILING_TRACKING')
      && CIVICRM_WP_REST_REPLACE_MAILING_TRACKING)
    ) {
      // register mailing hooks
      $mailing_hooks = (new Mailing_Hooks)->register_hooks();

    }

  }

  /**
   * Registers Rest API routes.
   *
   * @since 0.1
   */
  public function register_rest_routes() {

    // rest endpoint
    $rest_controller = new Controller\Rest();
    $rest_controller->register_routes();

    // url controller
    $url_controller = new Controller\Url();
    $url_controller->register_routes();

    // open controller
    $open_controller = new Controller\Open();
    $open_controller->register_routes();

    // authorizenet controller
    $authorizeIPN_controller = new Controller\AuthorizeIPN();
    $authorizeIPN_controller->register_routes();

    // paypal controller
    $paypalIPN_controller = new Controller\PayPalIPN();
    $paypalIPN_controller->register_routes();

    // pxpay controller
    $paypalIPN_controller = new Controller\PxIPN();
    $paypalIPN_controller->register_routes();

    // civiconnect controller
    $cxn_controller = new Controller\Cxn();
    $cxn_controller->register_routes();

    // widget controller
    $widget_controller = new Controller\Widget();
    $widget_controller->register_routes();

    // soap controller
    $soap_controller = new Controller\Soap();
    $soap_controller->register_routes();

    /**
     * Opportunity to add more rest routes.
     *
     * @since 0.1
     */
    do_action('civi_wp_rest/plugin/rest_routes_registered');

  }

  /**
   * Sets the timezone to the users timezone when
   * calling the civicrm/v3/rest endpoint.
   *
   * @since 0.1
   * @param WP_REST_Request $request The request
   */
  private function maybe_set_user_timezone($request) {

    if ($request->get_route() != '/civicrm/v3/rest') {
      return;
    }

    $timezones = [
      'wp_timezone' => date_default_timezone_get(),
      'user_timezone' => get_option('timezone_string', FALSE),
    ];

    // filter timezones
    add_filter('civi_wp_rest/plugin/timezones', function() use ($timezones) {

      return $timezones;

    });

    if (empty($timezones['user_timezone'])) {
      return;
    }

    /**
     * CRM-12523
     * CRM-18062
     * CRM-19115
     */
    date_default_timezone_set($timezones['user_timezone']);
    \CRM_Core_Config::singleton()->userSystem->setMySQLTimeZone();

  }

  /**
   * Resets the timezone to the original WP
   * timezone after calling the civicrm/v3/rest endpoint.
   *
   * @since 0.1
   * @param mixed $result
   * @param WP_REST_Server $server REST server instance
   * @param WP_REST_Request $request The request
   * @return mixed $result
   */
  public function maybe_reset_wp_timezone($result, $server, $request) {

    if ($request->get_route() != '/civicrm/v3/rest') {
      return $result;
    }

    $timezones = apply_filters('civi_wp_rest/plugin/timezones', NULL);

    if (empty($timezones['wp_timezone'])) {
      return $result;
    }

    // reset wp timezone
    date_default_timezone_set($timezones['wp_timezone']);

    return $result;

  }

  /**
   * Performs the necessary checks and
   * data retrieval to login a WordPress user.
   *
   * @since 0.1
   * @param \WP_REST_Request $request The request
   * @return \WP_User|\WP_Error|void $logged_in_wp_user The logged in WordPress user object, \Wp_Error, or nothing
   */
  public function do_user_login($request) {

    /**
     * Filter and opportunity to bypass
     * the default user login.
     *
     * @since 0.1
     * @param bool $login
     */
    $logged_in = apply_filters('civi_wp_rest/plugin/do_user_login', FALSE, $request);

    if ($logged_in) {
      return;
    }

    // default login based on contact's api_key
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
   * @since 0.1
   * @param int $contact_id The contact id
   * @return WP_User|WP_Error $user The WordPress user data or WP_Error object
   */
  public function get_wp_user(int $contact_id) {

    try {

      // Call API.
      $uf_match = civicrm_api3('UFMatch', 'getsingle', [
        'contact_id' => $contact_id,
        'domain_id' => $this->get_civi_domain_id(),
      ]);

    }
    catch (\CiviCRM_API3_Exception $e) {

      return new \WP_Error(
        'civicrm_rest_api_error',
        __('A WordPress user must be associated with the contact for the provided API key.', 'civicrm')
      );

    }

    // filter uf_match
    add_filter('civi_wp_rest/plugin/uf_match', function() use ($uf_match) {

      return !empty($uf_match) ? $uf_match : NULL;

    });

    return get_userdata($uf_match['uf_id']);

  }

  /**
   * Logs in the WordPress user, and
   * syncs it with it's CiviCRM contact.
   *
   * @since 0.1
   * @param \WP_User $wp_user The WordPress user object
   * @param \WP_REST_Request|NULL $request The request object or NULL
   * @return \WP_User|void $wp_user The logged in WordPress user object or nothing
   */
  public function login_wp_user(\WP_User $wp_user, $request = NULL) {

    /**
     * Filter the user about to be logged in.
     *
     * @since 0.1
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
   * Sets the necessary user
   * session variables for CiviCRM.
   *
   * @since 0.1
   * @param \WP_User $wp_user The WordPress user
   * @return void
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
   * @since 0.1
   * @return int $domain_id The domain id
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
