<?php
/**
 * Plugin Name: CiviCRM
 * Description: CiviCRM - Growing and Sustaining Relationships
 * Version: 4.7
 * Requires at least: 4.9
 * Requires PHP:      8.0
 * Author: CiviCRM LLC
 * Author URI: https://civicrm.org/
 * Plugin URI: https://docs.civicrm.org/sysadmin/en/latest/install/wordpress/
 * License: AGPL3
 * Text Domain: civicrm
 * Domain Path: /languages
 */

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
 *
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 *
 */

// This file must not accessed directly.
if (!defined('ABSPATH')) {
  exit;
}

// Set version here: changing it forces Javascript and CSS to reload.
define('CIVICRM_PLUGIN_VERSION', '4.7');

// Store reference to this file.
if (!defined('CIVICRM_PLUGIN_FILE')) {
  define('CIVICRM_PLUGIN_FILE', __FILE__);
}

// Store URL to this plugin's directory.
if (!defined('CIVICRM_PLUGIN_URL')) {
  define('CIVICRM_PLUGIN_URL', plugin_dir_url(CIVICRM_PLUGIN_FILE));
}

// Store PATH to this plugin's directory.
if (!defined('CIVICRM_PLUGIN_DIR')) {
  define('CIVICRM_PLUGIN_DIR', plugin_dir_path(CIVICRM_PLUGIN_FILE));
}

/*
 * Minimum required PHP.
 *
 * Note: This duplicates `CRM_Upgrade_Incremental_General::MIN_INSTALL_PHP_VER`.
 * The duplication helps avoid dependency issues. (Reading
 * `CRM_Upgrade_Incremental_General::MIN_INSTALL_PHP_VER` requires loading
 * `civicrm.settings.php`, but that triggers a parse-error on PHP 5.x.)
 *
 * @see CRM_Upgrade_Incremental_General::MIN_INSTALL_PHP_VER
 * @see CiviWP\PhpVersionTest::testConstantMatch()
 */
if (!defined('CIVICRM_WP_PHP_MINIMUM')) {
  define('CIVICRM_WP_PHP_MINIMUM', '8.0.0');
}

/*
 * The constant `CIVICRM_SETTINGS_PATH` is also defined in `civicrm.config.php`
 * and may already have been defined there - e.g. by cron or external scripts.
 * These legacy routes should not be used because they try to bootstrap WordPress
 * in unreliable ways. Use WP-CLI or WP-REST routes instead.
 */
if (!defined('CIVICRM_SETTINGS_PATH')) {

  /*
   * Test where the settings file exists.
   *
   * If the settings file is found in the 4.6 and prior location, use that as
   * `CIVICRM_SETTINGS_PATH`, otherwise use the new location.
   */
  $wp_civi_settings_deprecated = CIVICRM_PLUGIN_DIR . 'civicrm.settings.php';
  if (file_exists($wp_civi_settings_deprecated)) {
    define('CIVICRM_SETTINGS_PATH', $wp_civi_settings_deprecated);
  }
  else {
    $upload_dir = wp_upload_dir();
    $wp_civi_settings = implode(DIRECTORY_SEPARATOR, [$upload_dir['basedir'], 'civicrm', 'civicrm.settings.php']);
    define('CIVICRM_SETTINGS_PATH', $wp_civi_settings);
  }

}

// Test if CiviCRM is installed.
if (file_exists(CIVICRM_SETTINGS_PATH)) {
  define('CIVICRM_INSTALLED', TRUE);
}
else {
  define('CIVICRM_INSTALLED', FALSE);
}

// Prevent CiviCRM from rendering its own header.
define('CIVICRM_UF_HEAD', TRUE);

/**
 * Setting this to 'TRUE' will replace all mailing URLs calls to 'extern/url.php'
 * and 'extern/open.php' with their REST counterpart 'civicrm/v3/url' and
 * 'civicrm/v3/open'.
 *
 * Use for test purposes, may affect mailing performance.
 *
 * @see CiviCRM_WP_REST\Plugin::replace_tracking_urls()
 */
if (!defined('CIVICRM_WP_REST_REPLACE_MAILING_TRACKING')) {
  define('CIVICRM_WP_REST_REPLACE_MAILING_TRACKING', FALSE);
}

/**
 * Define CiviCRM_For_WordPress Class.
 *
 * @since 4.4
 */
class CiviCRM_For_WordPress {

  /**
   * @var object
   * Plugin instance.
   * @since 4.4
   * @access private
   */
  private static $instance;

  /**
   * @var bool
   * Plugin context (broad).
   * @since 4.4
   * @access public
   */
  public static $in_wordpress;

  /**
   * @var string
   * Plugin context (specific).
   * @since 4.4
   * @access public
   */
  public static $context;

  /**
   * @var object
   * Shortcodes management object.
   * @since 4.4
   * @access public
   */
  public $shortcodes;

  /**
   * @var object
   * Modal dialog management object.
   * @since 4.4
   * @access public
   */
  public $modal;

  /**
   * @var object
   * Base Page management object.
   * @since 4.4
   * @access public
   */
  public $basepage;

  /**
   * @var object
   * User management object.
   * @since 4.4
   * @access public
   */
  public $users;

  /**
   * @var object
   * The plugin compatibility object.
   * @since 5.24
   * @access public
   */
  public $compat;

  /**
   * @var object
   * Admin object.
   * @since 5.33
   * @access public
   */
  public $admin;

  /**
   * @var array
   * Reference to the original $_GET value.
   * @since 4.6
   * @access protected
   */
  protected $wp_get;

  /**
   * @var array
   * Reference to the original $_POST value.
   * @since 4.6
   * @access protected
   */
  protected $wp_post;

  /**
   * @var array
   * Reference to the original $_COOKIE value.
   * @since 4.6
   * @access protected
   */
  protected $wp_cookie;

  /**
   * @var array
   * Reference to the original $_REQUEST value.
   * @since 4.6
   * @access protected
   */
  protected $wp_request;

  // ---------------------------------------------------------------------------
  // Setup
  // ---------------------------------------------------------------------------

  /**
   * Getter method which returns the CiviCRM instance and optionally creates one
   * if it does not already exist. Standard CiviCRM singleton pattern.
   *
   * @since 4.4
   *
   * @return object CiviCRM_For_WordPress The CiviCRM plugin instance.
   */
  public static function singleton() {

    // If instance doesn't already exist.
    if (!isset(self::$instance)) {

      // Create instance.
      self::$instance = new CiviCRM_For_WordPress();

      // Include global scope functions.
      include_once CIVICRM_PLUGIN_DIR . 'includes/civicrm.functions.php';

      // Add WP-CLI commands.
      if (defined('WP_CLI') && WP_CLI) {
        include_once CIVICRM_PLUGIN_DIR . 'wp-cli/wp-cli-civicrm.php';
      }

      define('CIVICRM_IFRAME', self::$instance->is_iframe());
      if (CIVICRM_IFRAME) {
        // Must run before WP starts processing cookies.
        self::$instance->activate_iframe();
      }

      // Delay setup until 'plugins_loaded' to allow other plugins to load as well.
      add_action('plugins_loaded', [self::$instance, 'setup_instance']);

    }

    // Return instance.
    return self::$instance;

  }

  /**
   * Dummy instance constructor.
   *
   * @since 4.4
   */
  public function __construct() {}

  /**
   * Dummy magic method to prevent CiviCRM_For_WordPress from being cloned.
   *
   * @since 4.4
   */
  public function __clone() {
    _doing_it_wrong(__FUNCTION__, __('Only one instance of CiviCRM_For_WordPress please', 'civicrm'), '4.4');
  }

  /**
   * Dummy magic method to prevent CiviCRM_For_WordPress from being unserialized.
   *
   * @since 4.4
   */
  public function __wakeup() {
    _doing_it_wrong(__FUNCTION__, __('Please do not serialize CiviCRM_For_WordPress', 'civicrm'), '4.4');
  }

  protected function is_iframe(): bool {
    if (is_admin()) {
      // We intend to process IFRAMEs through WP-frontend not WP-backend.
      // This is separate from the actual content of the page (which isn't really WP-frontend or WP-backend).
      return FALSE;
    }

    // return (1 == get_query_var('_cvwpif')); // Too Early
    return !empty($_REQUEST['_cvwpif']);
    // return 'iframe' === $_SERVER['HTTP_SEC_FETCH_DEST']; // Harder to test. And Safari only gained support a year ago.
  }

  protected function activate_iframe(): void {
    // By default, internal links should stay in the iframe.
    $GLOBALS['civicrm_url_defaults'][]['scheme'] = 'iframe';

    // Strict browsers (eg Safari) will quietly disregard cookies when loading a page within an IFRAME.
    // But it's quite awkward to test behavior with two variables (browser-type and page-context).
    // For consistent testing/UX, we force similar behavior for any request with IFRAME-style URL.

    // Variant A: Disregard specific pieces
    remove_filter('determine_current_user', 'wp_validate_auth_cookie');
    remove_filter('determine_current_user', 'wp_validate_logged_in_cookie', 20);
    remove_filter('determine_current_user', 'wp_validate_application_password', 20);

    // Variant B: This sounds more thorough, but interferes with co-session. Probably a timing issue.
    // $_COOKIE = [];
    // self::$instance->wp_cookie = [];

    // Variant C: Filter $_COOKIE by name. However, this might not work if local site has renamed the cookies.
    // $ignoreKeys = preg_grep('/^(wp|wordpress)/', array_keys($_COOKIE));
    // foreach ($ignoreKeys as $key) {
    //   unset($_COOKIE[$key]);
    // }
  }

  /**
   * Plugin activation.
   *
   * This method is called only when CiviCRM plugin is activated. Other plugins
   * are able to interact with CiviCRM's activation because "plugins_loaded" has
   * already fired.
   *
   * Since CiviCRM has an Installer UI when activated via the WordPress Plugins
   * screen, this method sets an option that can be read on the next page load
   * allowing `self::activation()` to redirect to it when possible.
   *
   * @since 4.4
   */
  public function activate() {

    // Set a one-time-only option.
    add_option('civicrm_activation_in_progress', 'true');

    // Include and init classes because "plugins_loaded" has already fired.
    include_once CIVICRM_PLUGIN_DIR . 'includes/civicrm.users.php';
    $this->users = new CiviCRM_For_WordPress_Users();

    /**
     * Broadcast that the CiviCRM plugin has been activated.
     *
     * Used internally by:
     *
     * - CiviCRM_For_WordPress_Users::activate()
     *
     * @since 5.44
     */
    do_action('civicrm_activate');

  }

  /**
   * Runs the CiviCRM activation procedure when activated via the WordPress UI.
   *
   * @since 4.4
   */
  public function activation() {

    // Bail if not activating.
    if (get_option('civicrm_activation_in_progress') !== 'true') {
      return;
    }

    // Bail if not in WordPress admin.
    if (!is_admin()) {
      return;
    }

    /**
     * Broadcast that activation via the WordPress UI has happened.
     *
     * This fires on the admin page load that happens directly after the CiviCRM
     * plugin has been activated via the WordPress UI.
     *
     * @since 5.6
     */
    do_action('civicrm_activation');

    // Change option so this action never fires again.
    update_option('civicrm_activation_in_progress', 'false');

    // When installed via the WordPress UI, try and redirect to the Installer page.
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $activate_multi = isset($_GET['activate-multi']) ? sanitize_text_field(wp_unslash($_GET['activate-multi'])) : '';
    if (!is_multisite() && empty($activate_multi) && !CIVICRM_INSTALLED) {
      wp_safe_redirect(admin_url('admin.php?page=civicrm-install'));
      exit;
    }

  }

  /**
   * Plugin deactivation.
   *
   * This method is called only when CiviCRM plugin is deactivated. In order for
   * other plugins to be able to interact with CiviCRM's activation, we need to
   * remove any options that are set in activate() above.
   *
   * @since 4.4
   */
  public function deactivate() {

    // Delete any options we hay have set.
    delete_option('civicrm_activation_in_progress');

    /**
     * Broadcast that deactivation actions need to happen now.
     *
     * Used internally by:
     *
     * - CiviCRM_For_WordPress_Users::deactivate()
     *
     * @since 5.6
     */
    do_action('civicrm_deactivation');

  }

  // ---------------------------------------------------------------------------
  // Plugin set up
  // ---------------------------------------------------------------------------

  /**
   * Set up the CiviCRM plugin instance.
   *
   * @since 4.4
   */
  public function setup_instance() {

    // Kick out if another instance is being inited.
    if (isset(self::$in_wordpress)) {
      wp_die(__('Only one instance of CiviCRM_For_WordPress please', 'civicrm'));
    }

    // Maybe start session.
    $this->maybe_start_session();

    /*
     * AJAX calls do not set the 'cms.root' item, so make sure it is set here so
     * the CiviCRM doesn't fall back on flaky directory traversal code.
     */
    global $civicrm_paths;
    if (empty($civicrm_paths['cms.root']['path'])) {
      $civicrm_paths['cms.root']['path'] = untrailingslashit(ABSPATH);
    }
    if (empty($civicrm_paths['cms.root']['url'])) {
      $civicrm_paths['cms.root']['url'] = home_url();
    }

    // Include class files and instantiate.
    $this->include_files();
    $this->setup_objects();

    // Do plugin activation when activated via the WordPress UI.
    $this->activation();

    // Use translation files.
    $this->enable_translation();

    // Register all hooks on init.
    add_action('init', [$this, 'register_hooks']);

    /**
     * Broadcast that this plugin is now loaded.
     *
     * Used internally by:
     *
     * - CiviCRM_For_WordPress_Basepage::maybe_create_basepage()
     *
     * @since 4.4
     */
    do_action('civicrm_instance_loaded');

  }

  /**
   * Maybe start a session for CiviCRM.
   *
   * There is no session handling in WordPress so start it for CiviCRM pages.
   *
   * Not needed when running:
   *
   * - via WP-CLI
   * - via wp-cron.php
   * - via PHP on the command line
   *
   * none of which require sessions.
   *
   * @since 5.28
   */
  public function maybe_start_session() {

    // Get existing session ID.
    $session_id = session_id();

    // Check WordPress pseudo-cron.
    $wp_cron = FALSE;
    if (function_exists('wp_doing_cron') && wp_doing_cron()) {
      $wp_cron = TRUE;
    }

    // Check WP-CLI.
    $wp_cli = FALSE;
    if (defined('WP_CLI') && WP_CLI) {
      $wp_cli = TRUE;
    }

    // Check PHP on the command line - e.g. `cv`.
    $php_cli = TRUE;
    if (PHP_SAPI !== 'cli') {
      $php_cli = FALSE;
    }

    // Maybe start session.
    if (empty($session_id) && !$wp_cron && !$wp_cli && !$php_cli) {
      session_start();
    }

  }

  /**
   * Include files.
   *
   * @since 4.4
   */
  public function include_files() {

    // Include class files.
    include_once CIVICRM_PLUGIN_DIR . 'includes/civicrm.admin.php';
    include_once CIVICRM_PLUGIN_DIR . 'includes/civicrm.users.php';
    include_once CIVICRM_PLUGIN_DIR . 'includes/civicrm.shortcodes.php';
    include_once CIVICRM_PLUGIN_DIR . 'includes/civicrm.shortcodes.modal.php';
    include_once CIVICRM_PLUGIN_DIR . 'includes/civicrm.basepage.php';
    include_once CIVICRM_PLUGIN_DIR . 'includes/civicrm.compat.php';

    // Maybe include REST API autoloader class.
    if (!class_exists('CiviCRM_WP_REST\Autoloader')) {
      require_once CIVICRM_PLUGIN_DIR . 'wp-rest/Autoloader.php';
    }

  }

  /**
   * Instantiate objects.
   *
   * @since 5.33
   */
  public function setup_objects() {

    // Instantiate objects.
    $this->admin = new CiviCRM_For_WordPress_Admin();
    $this->users = new CiviCRM_For_WordPress_Users();
    $this->shortcodes = new CiviCRM_For_WordPress_Shortcodes();
    $this->modal = new CiviCRM_For_WordPress_Shortcodes_Modal();
    $this->basepage = new CiviCRM_For_WordPress_Basepage();
    $this->compat = new CiviCRM_For_WordPress_Compat();

  }

  /**
   * Load translation files.
   *
   * A good reference on how to implement translation in WordPress:
   * http://ottopress.com/2012/internationalization-youre-probably-doing-it-wrong/
   *
   * Also see:
   * https://developer.wordpress.org/plugins/internationalization/
   *
   * @since 4.4
   */
  public function enable_translation() {

    // Load translations.
    // phpcs:ignore WordPress.WP.DeprecatedParameters.Load_plugin_textdomainParam2Found
    load_plugin_textdomain(
      // Unique name.
      'civicrm',
      // Deprecated argument.
      FALSE,
      // Relative path to translation files.
      dirname(plugin_basename(__FILE__)) . '/languages/'
    );

  }

  // ---------------------------------------------------------------------------
  // Context
  // ---------------------------------------------------------------------------

  /**
   * Set broad CiviCRM context.
   *
   * Setter for determining if CiviCRM is currently being displayed in WordPress.
   * This becomes true whe CiviCRM is called in the following contexts:
   *
   * (a) In the WordPress back-end.
   * (b) When CiviCRM content is being displayed on the front-end via the Base Page.
   * (c) When an AJAX request is made to CiviCRM.
   *
   * It is NOT true when CiviCRM is called via a Shortcode.
   *
   * @since 4.4
   */
  public function civicrm_in_wordpress_set() {

    // Store identifying query var.
    $page = get_query_var('civiwp');
    self::$in_wordpress = ($page === 'CiviCRM') ? TRUE : FALSE;

  }

  /**
   * Getter for testing if CiviCRM is currently being displayed in WordPress.
   *
   * @see $this->civicrm_in_wordpress_set()
   *
   * @since 4.4
   *
   * @return bool $in_wordpress True if CiviCRM is displayed in WordPress, false otherwise.
   */
  public function civicrm_in_wordpress() {

    /**
     * Allow broad context to be filtered.
     *
     * @since 4.4
     *
     * @param bool $in_wordpress True if CiviCRM is displayed in WordPress, false otherwise.
     */
    return apply_filters('civicrm_in_wordpress', self::$in_wordpress);

  }

  /**
   * Set specific CiviCRM context.
   *
   * Setter for determining how CiviCRM is currently being displayed in WordPress.
   * This can be one of the following contexts:
   *
   * (a) In the WordPress back-end.
   * (b) When CiviCRM content is being displayed on the front-end via the Base Page.
   * (c) When a "non-page" request is made to CiviCRM.
   * (d) When CiviCRM is called via a Shortcode.
   *
   * The following codes correspond to the different contexts:
   *
   * (a) 'admin'
   * (b) 'basepage'
   * (c) 'nonpage'
   * (d) 'shortcode'
   *
   * @since 4.4
   *
   * @param string $context One of the four context codes above.
   */
  public function civicrm_context_set($context) {

    // Store.
    self::$context = $context;

  }

  /**
   * Get specific CiviCRM context.
   *
   * Getter for determining how CiviCRM is currently being displayed in WordPress.
   *
   * @see $this->civicrm_context_set()
   *
   * @since 4.4
   *
   * @return string The context in which CiviCRM is displayed in WordPress.
   */
  public function civicrm_context_get() {

    /**
     * Allow specific context to be filtered.
     *
     * Used internally by:
     *
     * - CiviCRM_For_WordPress_Shortcodes::get_context()
     *
     * @since 4.4
     *
     * @param bool $context The existing context in which CiviCRM is displayed in WordPress.
     */
    return apply_filters('civicrm_context', self::$context);

  }

  // ---------------------------------------------------------------------------
  // Hooks
  // ---------------------------------------------------------------------------

  /**
   * Register hooks on init.
   *
   * @since 4.4
   */
  public function register_hooks() {

    // Always add the common hooks.
    $this->register_hooks_common();

    // When in WordPress admin.
    if (is_admin()) {

      // Set context.
      $this->civicrm_context_set('admin');

      // Handle WordPress Admin context.
      $this->admin->register_hooks();

      // Enable Shortcode modal.
      $this->modal->register_hooks();

      return;

    }

    // Go no further if CiviCRM not installed yet.
    if (!CIVICRM_INSTALLED) {
      return;
    }

    // Attempt to replace 'page' query arg with 'civiwp'.
    add_filter('request', [$this, 'maybe_replace_page_query_var']);

    // Add our query vars.
    add_filter('query_vars', [$this, 'query_vars']);

    // Delay everything else until query has been parsed.
    add_action('parse_query', [$this, 'register_hooks_front_end']);

  }

  /**
   * Register hooks for the front end.
   *
   * @since 5.6
   *
   * @param WP_Query $query The WP_Query instance (passed by reference).
   */
  public function register_hooks_front_end($query) {

    // Bail if $query is not the main loop.
    if (!$query->is_main_query()) {
      return;
    }

    // Bail if filters are suppressed on this query.
    if (TRUE === $query->get('suppress_filters')) {
      return;
    }

    // Prevent multiple calls.
    static $alreadyRegistered = FALSE;
    if ($alreadyRegistered) {
      return;
    }
    $alreadyRegistered = TRUE;

    // Redirect if old query var is present.
    if ('CiviCRM' === get_query_var('page') && 'CiviCRM' !== get_query_var('civiwp')) {
      $redirect_url = remove_query_arg('page', FALSE);
      $redirect_url = add_query_arg('civiwp', 'CiviCRM', $redirect_url);
      wp_safe_redirect($redirect_url, 301);
      exit();
    }

    // Store context.
    $this->civicrm_in_wordpress_set();

    // When the CiviCRM query var is detected.
    if ($this->civicrm_in_wordpress()) {

      /*
       * Directly output CiviCRM html only in a few cases and skip WordPress
       * templating:
       *
       * (a) when a snippet is set
       * (b) when there is an AJAX call
       * (c) for an iCal feed (unless 'html' is specified)
       * (d) for file download URLs
       */
      if (!$this->is_page_request()) {

        // Set context.
        $this->civicrm_context_set('nonpage');

        // Add core resources for front end.
        add_action('wp', [$this, 'front_end_page_load']);

        // Echo all output when WordPress has been set up but nothing has been rendered.
        add_action('wp', [$this, 'invoke']);
        return;

      }

    }

    // Let the classes decide how to handle other requests.
    $this->basepage->register_hooks();
    $this->shortcodes->register_hooks();

  }

  /**
   * Register hooks that must always be present.
   *
   * @since 4.4
   */
  public function register_hooks_common() {

    // Register user hooks.
    $this->users->register_hooks();

    // Register hooks for clean URLs.
    $this->register_hooks_clean_urls();

    if (!class_exists('CiviCRM_WP_REST\Plugin')) {

      // Set up REST API.
      CiviCRM_WP_REST\Autoloader::add_source($source_path = trailingslashit(CIVICRM_PLUGIN_DIR . 'wp-rest'));

      // Init REST API.
      new CiviCRM_WP_REST\Plugin();

    }

  }

  /**
   * Register hooks to handle Clean URLs.
   *
   * @since 5.12
   */
  public function register_hooks_clean_urls() {

    // Bail if CiviCRM is not installed.
    if (!CIVICRM_INSTALLED) {
      return;
    }

    // Bail if we can't initialize CiviCRM.
    if (!$this->initialize()) {
      return;
    }

    // Bail if CiviCRM is not using Clean URLs.
    if (!defined('CIVICRM_CLEANURL') || CIVICRM_CLEANURL !== 1) {
      return;
    }

    // Have we flushed rewrite rules?
    if (get_option('civicrm_rules_flushed') !== 'true') {

      // Apply custom rewrite rules, then flush rules afterwards.
      $this->rewrite_rules(TRUE);

      // Set a one-time-only option to flag that this has been done.
      add_option('civicrm_rules_flushed', 'true');

    }
    else {

      // Apply custom rewrite rules normally.
      $this->rewrite_rules();

    }

  }

  // ---------------------------------------------------------------------------
  // Construction of URLs
  // ---------------------------------------------------------------------------

  /**
   * Add our rewrite rules.
   *
   * @since 5.7
   *
   * @param bool $flush_rewrite_rules True if rules should be flushed, false otherwise.
   */
  public function rewrite_rules($flush_rewrite_rules = FALSE) {

    // Kick out if not CiviCRM.
    if (!$this->initialize()) {
      return;
    }

    // Get config.
    $config = CRM_Core_Config::singleton();

    // Get Base Page object.
    $basepage = get_page_by_path($config->wpBasePage);

    // Sanity check.
    if (!is_object($basepage)) {
      return;
    }

    // Let's add Rewrite Rule when viewing the Base Page.
    add_rewrite_rule(
      '^' . $config->wpBasePage . '/([^?]*)?',
      'index.php?page_id=' . $basepage->ID . '&civiwp=CiviCRM&q=civicrm%2F$matches[1]',
      'top'
    );

    // Maybe force flush.
    if ($flush_rewrite_rules) {
      flush_rewrite_rules();
    }

    /**
     * Broadcast the rewrite rules event.
     *
     * Used internally by:
     *
     * - CiviCRM_For_WordPress_Compat::rewrite_rules_polylang()
     *
     * @since 5.7
     * @since 5.24 Added $basepage parameter.
     *
     * @param bool $flush_rewrite_rules True if rules flushed, false otherwise.
     * @param WP_Post $basepage The Base Page post object.
     */
    do_action('civicrm_after_rewrite_rules', $flush_rewrite_rules, $basepage);

  }

  /**
   * Add our query vars.
   *
   * @since 5.7
   *
   * @param array $query_vars The existing query vars.
   * @return array $query_vars The modified query vars.
   */
  public function query_vars($query_vars) {

    // Sanity check.
    if (!is_array($query_vars)) {
      $query_vars = [];
    }

    // Build our query vars.
    $civicrm_query_vars = [
      // URL query vars.
      'civiwp', 'q', 'reset', 'id', 'html', 'snippet',
      // Shortcode query vars.
      'action', 'mode', 'cid', 'gid', 'sid', 'cs', 'force',
    ];

    /**
     * Filter the default CiviCRM query vars.
     *
     * Use in combination with `civicrm_query_vars_assigned` action to ensure
     * that any other query vars are included in the assignment to the
     * super-global arrays.
     *
     * @since 5.7
     *
     * @param array $civicrm_query_vars The default set of query vars.
     */
    $civicrm_query_vars = apply_filters('civicrm_query_vars', $civicrm_query_vars);

    // Now add them to WordPress.
    foreach ($civicrm_query_vars as $civicrm_query_var) {
      $query_vars[] = $civicrm_query_var;
    }

    return $query_vars;

  }

  /**
   * Filters the request right after WordPress has parsed it and replaces the
   * 'page' query variable with 'civiwp' if applicable.
   *
   * Prevents old URLs like example.org/civicrm/?page=CiviCRM&q=what/ever/path&reset=1
   * being redirected to example.org/civicrm/?civiwp=CiviCRM&q=what/ever/path&reset=1
   *
   * @see https://lab.civicrm.org/dev/wordpress/-/issues/49
   *
   * @since 5.26
   *
   * @param array $query_vars The existing query vars.
   * @return array $query_vars The modified query vars.
   */
  public function maybe_replace_page_query_var($query_vars) {

    $civi_query_arg = array_search('CiviCRM', $query_vars);

    // Bail if the query var is not 'page'.
    if (FALSE === $civi_query_arg || $civi_query_arg !== 'page') {
      return $query_vars;
    }

    unset($query_vars['page']);
    $query_vars['civiwp'] = 'CiviCRM';

    return $query_vars;

  }

  // ---------------------------------------------------------------------------
  // CiviCRM Initialisation
  // ---------------------------------------------------------------------------

  /**
   * Initialize CiviCRM.
   *
   * This method has been moved to "includes/civicrm.admin.php"
   *
   * @since 4.4
   * @since 5.33 Placeholder for backwards (and semantic) compatibility.
   *
   * @return bool True if CiviCRM is initialized, false otherwise.
   */
  public function initialize() {

    // Pass to method in admin class.
    return $this->admin->initialize();

  }

  // ---------------------------------------------------------------------------
  // Load Resources
  // ---------------------------------------------------------------------------

  /**
   * Perform necessary stuff prior to CiviCRM being loaded on the front end.
   *
   * This needs to be a method because it can then be hooked into WordPress at
   * the right time.
   *
   * @since 4.6
   */
  public function front_end_page_load() {

    static $frontend_loaded = FALSE;
    if ($frontend_loaded) {
      return;
    }

    // Add resources for front end.
    $this->add_core_resources(TRUE);

    // Merge CiviCRM's HTML header with the WordPress theme's header.
    add_action('wp_head', [$this, 'wp_head']);

    // Set flag so this only happens once.
    $frontend_loaded = TRUE;

  }

  /**
   * Load only the CiviCRM CSS.
   *
   * This is needed because $this->front_end_page_load() is only called when
   * there is a single CiviCRM entity present on a page or archive and, whilst
   * we don't want all the Javascript to load, we do want stylesheets.
   *
   * @since 4.6
   */
  public function front_end_css_load() {

    static $frontend_css_loaded = FALSE;
    if ($frontend_css_loaded) {
      return;
    }

    if (!$this->initialize()) {
      return;
    }

    $config = CRM_Core_Config::singleton();

    // Default custom CSS to standalone.
    $dependent = NULL;

    // Load core CSS.
    if (!CRM_Core_BAO_Setting::getItem(CRM_Core_BAO_Setting::SYSTEM_PREFERENCES_NAME, 'disable_core_css')) {

      // Enqueue stylesheet.
      wp_enqueue_style(
        'civicrm_css',
        $config->resourceBase . 'css/civicrm.css',
        // Dependencies.
        NULL,
        // Version.
        CIVICRM_PLUGIN_VERSION,
        // Media.
        'all'
      );

      // Custom CSS is dependent.
      $dependent = ['civicrm_css'];

    }

    // Load custom CSS.
    if (!empty($config->customCSSURL)) {
      wp_enqueue_style(
        'civicrm_custom_css',
        $config->customCSSURL,
        // Dependencies.
        $dependent,
        // Version.
        CIVICRM_PLUGIN_VERSION,
        // Media.
        'all'
      );
    }

    // Set flag so this only happens once.
    $frontend_css_loaded = TRUE;

  }

  /**
   * Add CiviCRM core resources.
   *
   * @since 4.6
   *
   * @param bool $front_end True if on WordPress front end, false otherwise.
   */
  public function add_core_resources($front_end = TRUE) {

    if (!$this->initialize()) {
      return;
    }

    $config = CRM_Core_Config::singleton();
    $config->userFrameworkFrontend = $front_end;

    // Add CiviCRM core resources.
    CRM_Core_Resources::singleton()->addCoreResources();

  }

  /**
   * Merge CiviCRM's HTML header with the WordPress theme's header.
   *
   * Callback from WordPress 'admin_head' and 'wp_head' hooks.
   *
   * @since 4.4
   */
  public function wp_head() {

    if (!$this->initialize()) {
      return;
    }

    /*
     * CRM-11823
     * If CiviCRM bootstrapped, then merge its HTML header with the CMS's header.
     */
    global $civicrm_root;
    if (empty($civicrm_root)) {
      return;
    }

    $region = CRM_Core_Region::instance('html-header', FALSE);
    if ($region) {
      echo '<!-- CiviCRM html header -->';
      echo $region->render('');
    }

  }

  // ---------------------------------------------------------------------------
  // CiviCRM Invocation (this outputs CiviCRM's markup)
  // ---------------------------------------------------------------------------

  /**
   * Invoke CiviCRM in a WordPress context.
   *
   * Callback function from add_menu_page()
   * Callback from WordPress 'init' and 'the_content' hooks
   * Also called by shortcode_render() and _civicrm_update_user()
   *
   * @since 4.4
   */
  public function invoke() {

    static $alreadyInvoked = FALSE;
    if ($alreadyInvoked) {
      return;
    }

    // Bail if this is called via a content-preprocessing plugin.
    if ($this->is_page_request() && !in_the_loop() && !is_admin()) {
      return;
    }

    if (!$this->initialize()) {
      return;
    }

    /*
     * CRM-12523
     * WordPress has it's own timezone calculations. CiviCRM relies on the PHP
     * default timezone which WordPress overrides with UTC in wp-settings.php
     */
    $original_timezone = date_default_timezone_get();
    $wp_site_timezone = $this->get_timezone_string();
    if ($wp_site_timezone) {
      // phpcs:ignore WordPress.DateTime.RestrictedFunctions.timezone_change_date_default_timezone_set
      date_default_timezone_set($wp_site_timezone);
      CRM_Core_Config::singleton()->userSystem->setMySQLTimeZone();
    }

    /*
     * CRM-95XX
     * At this point we are in a CiviCRM context. WordPress always quotes the
     * request, so CiviCRM needs to reverse what it just did.
     */
    $this->remove_wp_magic_quotes();

    // Required for AJAX calls.
    if ($this->civicrm_in_wordpress()) {
      $_REQUEST['noheader'] = $_GET['noheader'] = TRUE;
    }

    // Code inside invoke() requires the current user to be set up.
    $current_user = wp_get_current_user();

    /*
     * Bypass synchronize if running upgrade to avoid any serious non-recoverable
     * error which might hinder the upgrade process.
     */
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (CRM_Utils_Array::value('q', $_GET) !== 'civicrm/upgrade') {
      $this->users->sync_user($current_user);
    }

    // Set flag.
    $alreadyInvoked = TRUE;

    // Get args.
    $argdata = $this->get_request_args();

    // Set dashboard as default if args are empty.
    if (empty($argdata['argString'])) {
      $_GET['q'] = 'civicrm/dashboard';
      $_GET['reset'] = 1;
      $argdata['args'] = ['civicrm', 'dashboard'];
    }

    // Do the business.
    if (CIVICRM_IFRAME && \Civi::service('iframe.router')->getLayout() !== 'cms') {
      \Civi::service('iframe.router')->invoke([
        'route' => implode('/', $argdata['args']),
        'printPage' => function ($content) {
          echo $content;
          \CRM_Utils_System::civiExit();
        },
      ]);
    }
    else {
      CRM_Core_Invoke::invoke($argdata['args']);
    }

    // Restore original timezone.
    if ($original_timezone) {
      // phpcs:ignore WordPress.DateTime.RestrictedFunctions.timezone_change_date_default_timezone_set
      date_default_timezone_set($original_timezone);
    }

    // Restore WordPress's arrays.
    $this->restore_wp_magic_quotes();

    /**
     * Broadcast that CiviCRM has been invoked.
     *
     * @since 4.4
     */
    do_action('civicrm_invoked');

  }

  /**
   * Returns the timezone string for the current WordPress site.
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
    // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
    if (0 != $offset && floor($offset) == $offset) {
      $offset_int = (int) $offset;
      $offset_string = $offset > 0 ? "-$offset" : '+' . abs($offset_int);
      $tzstring = 'Etc/GMT' . $offset_string;
    }

    // Default to "UTC" if the timezone string is still empty.
    if (empty($tzstring)) {
      $tzstring = 'UTC';
    }

    return $tzstring;

  }

  /**
   * Non-destructively override WordPress magic quotes.
   *
   * Only called by invoke() to undo WordPress default behaviour.
   *
   * @since 4.4
   * @since 5.7 Rewritten to work with query vars.
   */
  private function remove_wp_magic_quotes() {

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    // phpcs:disable WordPress.Security.NonceVerification.Missing

    // Save original arrays.
    $this->wp_get     = $_GET;
    $this->wp_post    = $_POST;
    $this->wp_cookie  = $_COOKIE;
    $this->wp_request = $_REQUEST;

    // Reassign globals.
    $_GET     = stripslashes_deep($_GET);
    $_POST    = stripslashes_deep($_POST);
    $_COOKIE  = stripslashes_deep($_COOKIE);
    $_REQUEST = stripslashes_deep($_REQUEST);

    // phpcs:enable WordPress.Security.NonceVerification.Recommended
    // phpcs:enable WordPress.Security.NonceVerification.Missing

    // Test for query var.
    $q = get_query_var('q');
    if (!empty($q)) {

      $page = get_query_var('civiwp');
      $reset = get_query_var('reset');
      $id = get_query_var('id');
      $html = get_query_var('html');
      $snippet = get_query_var('snippet');

      $action = get_query_var('action');
      $mode = get_query_var('mode');
      $cid = get_query_var('cid');
      $gid = get_query_var('gid');
      $sid = get_query_var('sid');
      $cs = get_query_var('cs');
      $force = get_query_var('force');

      $_REQUEST['q'] = $_GET['q'] = $q;
      $_REQUEST['civiwp'] = $_GET['civiwp'] = 'CiviCRM';
      if (!empty($reset)) {
        $_REQUEST['reset'] = $_GET['reset'] = $reset;
      }
      if (!empty($id)) {
        $_REQUEST['id'] = $_GET['id'] = $id;
      }
      if (!empty($html)) {
        $_REQUEST['html'] = $_GET['html'] = $html;
      }
      if (!empty($snippet)) {
        $_REQUEST['snippet'] = $_GET['snippet'] = $snippet;
      }

      if (!empty($action)) {
        $_REQUEST['action'] = $_GET['action'] = $action;
      }
      if (!empty($mode)) {
        $_REQUEST['mode'] = $_GET['mode'] = $mode;
      }
      if (!empty($cid)) {
        $_REQUEST['cid'] = $_GET['cid'] = $cid;
      }
      if (!empty($gid)) {
        $_REQUEST['gid'] = $_GET['gid'] = $gid;
      }
      if (!empty($sid)) {
        $_REQUEST['sid'] = $_GET['sid'] = $sid;
      }
      if (!empty($cs)) {
        $_REQUEST['cs'] = $_GET['cs'] = $cs;
      }
      if (!empty($force)) {
        $_REQUEST['force'] = $_GET['force'] = $force;
      }

      /**
       * Broadcast that CiviCRM query vars have been assigned.
       *
       * Use in combination with `civicrm_query_vars` filter to ensure that any
       * other query vars are included in the assignment to the super-global
       * arrays.
       *
       * @since 5.7
       */
      do_action('civicrm_query_vars_assigned');

    }

  }

  /**
   * Restore WordPress magic quotes.
   *
   * Only called by invoke() to redo WordPress default behaviour.
   *
   * @since 4.4
   */
  private function restore_wp_magic_quotes() {

    // Restore original arrays.
    $_GET     = $this->wp_get;
    $_POST    = $this->wp_post;
    $_COOKIE  = $this->wp_cookie;
    $_REQUEST = $this->wp_request;

    unset($this->wp_get, $this->wp_post, $this->wp_cookie, $this->wp_request);

  }

  /**
   * Detect Ajax, snippet, or file requests.
   *
   * @since 4.4
   *
   * @return boolean $is_page True if request is for a CiviCRM page, false otherwise.
   */
  public function is_page_request() {

    // Assume not a CiviCRM page.
    $is_page = FALSE;

    // Bail if no CiviCRM.
    if (!$this->initialize()) {
      return $return;
    }

    // Get request args.
    $argdata = $this->get_request_args();

    // Try and populate "html" query var for testing snippet requests.
    $html = get_query_var('html');
    if (empty($html)) {
      // We do not use $html apart to test for empty.
      // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
      $html = isset($_GET['html']) ? wp_unslash($_GET['html']) : '';
    }

    /*
     * FIXME: It's not sustainable to hardcode a whitelist of all of non-HTML
     * pages. Maybe the menu-XML should include some metadata to make this
     * unnecessary?
     */

    // Is this an AJAX request?
    $is_ajax = (CRM_Utils_Array::value('HTTP_X_REQUESTED_WITH', $_SERVER) === 'XMLHttpRequest') ? TRUE : FALSE;

    // Is this a non-page CiviCRM path?
    $paths = ['ajax', 'file', 'asset'];
    $is_civicrm_path = ($argdata['args'][0] === 'civicrm' && in_array($argdata['args'][1], $paths)) ? TRUE : FALSE;

    // Is this a CiviCRM "snippet" request?
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $is_snippet = !empty($_REQUEST['snippet']) ? TRUE : FALSE;

    // Is this a CiviCRM iCal file request?
    $is_ical = (strpos($argdata['argString'], 'civicrm/event/ical') === 0 && empty($html)) ? TRUE : FALSE;

    // Is this a CiviCRM image file request?
    $is_image = (strpos($argdata['argString'], 'civicrm/contact/imagefile') === 0) ? TRUE : FALSE;

    // Any one of the above conditions being true means this is a "non-page" request.
    $non_page = ($is_ajax || $is_civicrm_path || $is_snippet || $is_ical || $is_image) ? TRUE : FALSE;

    /**
     * Filter the result of the "non-page" checks.
     *
     * This filter can be used to force CiviCRM into considering a given request to be
     * a "non-page" request (return TRUE) or a "page" request (return FALSE).
     *
     * @since 5.74
     *
     * @param bool $non_page Boolean TRUE for requests that CiviCRM should not render as a "page".
     * @param array $argdata The arguments and request string from query vars.
     */
    $non_page = apply_filters('civicrm_is_non_page_request', $non_page, $argdata);

    if ($non_page) {
      $is_page = FALSE;
    }
    else {
      $is_page = TRUE;
    }

    return $is_page;

  }

  /**
   * Get arguments and request string from query vars.
   *
   * @since 4.6
   *
   * @return array{args: array, argString: string}
   */
  public function get_request_args() {

    $argString = '';
    $args = [];

    // Get path from query vars.
    $q = get_query_var('q');
    if (empty($q)) {
      // phpcs:disable WordPress.Security.NonceVerification.Recommended
      $q = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
      // phpcs:enable WordPress.Security.NonceVerification.Recommended
    }

    /*
     * Fix 'civicrm/civicrm' elements derived from CRM:url()
     * @see https://lab.civicrm.org/dev/rc/issues/5#note_16205
     */
    if (defined('CIVICRM_CLEANURL') && CIVICRM_CLEANURL) {
      if (substr($q, 0, 16) === 'civicrm/civicrm/') {
        $q = str_replace('civicrm/civicrm/', 'civicrm/', $q);
        $_REQUEST['q'] = $_GET['q'] = $q;
        set_query_var('q', $q);
      }
    }

    if (!empty($q)) {
      $argString = trim($q);
      // Remove / from the beginning and ending of query string.
      $argString = trim($argString, '/');
      $args = explode('/', $argString);
    }
    $args = array_pad($args, 2, '');

    return [
      'args' => $args,
      'argString' => $argString,
    ];

  }

  /**
   * Get base URL.
   *
   * Clone of CRM_Utils_System_WordPress::getBaseUrl() whose access was set to
   * private. Now that it is public, we can access that method instead.
   *
   * @since 4.4
   *
   * @param bool $absolute Passing TRUE prepends the scheme and domain, FALSE doesn't.
   * @param bool $frontend Passing FALSE returns the admin URL.
   * @param bool $forceBackend Passing TRUE overrides $frontend and returns the admin URL.
   * @return mixed|null|string
   */
  public function get_base_url($absolute, $frontend, $forceBackend) {
    _deprecated_function(__METHOD__, '5.69', 'CRM_Utils_System::getBaseUrl');
    $config = CRM_Core_Config::singleton();
    if ((is_admin() && !$frontend) || $forceBackend) {
      return Civi::paths()->getUrl('[wp.backend]/.', $absolute ? 'absolute' : 'relative');
    }
    else {
      return Civi::paths()->getUrl('[wp.frontend]/.', $absolute ? 'absolute' : 'relative');
    }
  }

}

/*
 * -----------------------------------------------------------------------------
 * Procedures start here
 * -----------------------------------------------------------------------------
 */

/**
 * The main function responsible for returning the CiviCRM_For_WordPress instance
 * to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing to
 * declare the global.
 *
 * Example: $civi = civi_wp();
 *
 * @since 4.4
 *
 * @return CiviCRM_For_WordPress The plugin instance.
 */
function civi_wp() {
  return CiviCRM_For_WordPress::singleton();
}

/*
 * Instantiate CiviCRM_For_WordPress immediately.
 *
 * @see CiviCRM_For_WordPress::setup_instance()
 */
civi_wp();

/*
 * Tell WordPress to call plugin activation method - no longer calls legacy
 * global scope function.
 */
register_activation_hook(CIVICRM_PLUGIN_FILE, [civi_wp(), 'activate']);

/*
 * Tell WordPress to call plugin deactivation method - needed in order to reset
 * the option that is set on activation.
 */
register_deactivation_hook(CIVICRM_PLUGIN_FILE, [civi_wp(), 'deactivate']);

/*
 * Uninstall uses the 'uninstall.php' method.
 *
 * @see https://developer.wordpress.org/reference/functions/register_uninstall_hook/
 */
