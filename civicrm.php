<?php
/*
Plugin Name: CiviCRM
Description: CiviCRM - Growing and Sustaining Relationships
Version: 4.7
Requires at least: 4.9
Requires PHP:      7.1
Author: CiviCRM LLC
Author URI: https://civicrm.org/
Plugin URI: https://docs.civicrm.org/sysadmin/en/latest/install/wordpress/
License: AGPL3
Text Domain: civicrm
Domain Path: /languages
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


/*
--------------------------------------------------------------------------------
WordPress resources for developers
--------------------------------------------------------------------------------
Not that they're ever adhered to anywhere other than core, but people do their
best to comply...

WordPress core coding standards:
http://make.wordpress.org/core/handbook/coding-standards/php/

WordPress HTML standards:
http://make.wordpress.org/core/handbook/coding-standards/html/

WordPress JavaScript standards:
http://make.wordpress.org/core/handbook/coding-standards/javascript/
--------------------------------------------------------------------------------
*/


// This file must not accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;


// Set version here: when it changes, will force JS to reload
define( 'CIVICRM_PLUGIN_VERSION', '4.7' );

// Store reference to this file
if (!defined('CIVICRM_PLUGIN_FILE')) {
  define( 'CIVICRM_PLUGIN_FILE', __FILE__ );
}

// Store URL to this plugin's directory
if (!defined( 'CIVICRM_PLUGIN_URL')) {
  define( 'CIVICRM_PLUGIN_URL', plugin_dir_url(CIVICRM_PLUGIN_FILE) );
}

// Store PATH to this plugin's directory
if (!defined( 'CIVICRM_PLUGIN_DIR')) {
  define( 'CIVICRM_PLUGIN_DIR', plugin_dir_path(CIVICRM_PLUGIN_FILE) );
}

if ( !defined( 'CIVICRM_WP_PHP_MINIMUM' ) ) {
  /**
   * Minimum required PHP
   *
   * Note: This duplicates CRM_Upgrade_Incremental_General::MIN_INSTALL_PHP_VER.
   * The duplication helps avoid dependency issues. (Reading
   * `CRM_Upgrade_Incremental_General::MIN_INSTALL_PHP_VER` requires loading
   * `civicrm.settings.php`, but that triggers a parse-error
   * on PHP 5.x.)
   *
   * @see CRM_Upgrade_Incremental_General::MIN_INSTALL_PHP_VER
   * @see CiviWP\PhpVersionTest::testConstantMatch()
   */
  define( 'CIVICRM_WP_PHP_MINIMUM', '7.1.0' );
}

/*
 * The constant CIVICRM_SETTINGS_PATH is also defined in civicrm.config.php and
 * may already have been defined there - e.g. by cron or external scripts.
 */
if ( !defined( 'CIVICRM_SETTINGS_PATH' ) ) {

  /*
   * Test where the settings file exists.
   *
   * If the settings file is found in the 4.6 and prior location, use that as
   * CIVICRM_SETTINGS_PATH, otherwise use the new location.
   */
  $upload_dir    = wp_upload_dir();
  $wp_civi_settings = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'civicrm' . DIRECTORY_SEPARATOR . 'civicrm.settings.php' ;
  $wp_civi_settings_deprectated = CIVICRM_PLUGIN_DIR . 'civicrm.settings.php';

  if (file_exists($wp_civi_settings_deprectated)) {
    define( 'CIVICRM_SETTINGS_PATH', $wp_civi_settings_deprectated );
  }
  else  {
    define( 'CIVICRM_SETTINGS_PATH', $wp_civi_settings );
  }

}

// Test if CiviCRM is installed
if ( file_exists( CIVICRM_SETTINGS_PATH )  ) {
    define( 'CIVICRM_INSTALLED', TRUE );
  } else {
    define( 'CIVICRM_INSTALLED', FALSE );
}

// Prevent CiviCRM from rendering its own header
define( 'CIVICRM_UF_HEAD', TRUE );

/**
 * Setting this to 'true' will replace all mailing URLs calls to 'extern/url.php'
 * and 'extern/open.php' with their REST counterpart 'civicrm/v3/url' and 'civicrm/v3/open'.
 *
 * Use for test purposes, may affect mailing
 * performance, see Plugin->replace_tracking_urls() method.
 */
if ( ! defined( 'CIVICRM_WP_REST_REPLACE_MAILING_TRACKING' ) ) {
  define( 'CIVICRM_WP_REST_REPLACE_MAILING_TRACKING', false );
}


/**
 * Define CiviCRM_For_WordPress Class.
 *
 * @since 4.4
 */
class CiviCRM_For_WordPress {

  /**
   * Plugin instance.
   *
   * @since 4.4
   * @access private
   * @var object $instance The plugin instance.
   */
  private static $instance;

  /**
   * Plugin context (broad).
   *
   * @since 4.4
   * @access public
   * @var bool $in_wordpress The broad plugin context.
   */
  static $in_wordpress;

  /**
   * Plugin context (specific).
   *
   * @since 4.4
   * @access public
   * @var str $context The specific plugin context.
   */
  static $context;

  /**
   * Shortcodes management object.
   *
   * @since 4.4
   * @access public
   * @var object CiviCRM_For_WordPress_Shortcodes The shortcodes management object.
   */
  public $shortcodes;

  /**
   * Modal dialog management object.
   *
   * @since 4.4
   * @access public
   * @var object CiviCRM_For_WordPress_Shortcodes_Modal The modal dialog management object.
   */
  public $modal;

  /**
   * Basepage management object.
   *
   * @since 4.4
   * @access public
   * @var object CiviCRM_For_WordPress_Basepage The basepage management object.
   */
  public $basepage;

  /**
   * User management object.
   *
   * @since 4.4
   * @access public
   * @var object CiviCRM_For_WordPress_Users The user management object.
   */
  public $users;

  /**
   * Compatibility object.
   *
   * @since 5.24
   * @access public
   * @var object CiviCRM_For_WordPress_Compat The plugin compatibility object.
   */
  public $compat;


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

    // If instance doesn't already exist
    if ( ! isset( self::$instance ) ) {

      // Create instance
      self::$instance = new CiviCRM_For_WordPress;

      // Delay setup until 'plugins_loaded' to allow other plugins to load as well
      add_action( 'plugins_loaded', array( self::$instance, 'setup_instance' ) );

    }

    // Return instance
    return self::$instance;

  }


  /**
   * Dummy instance constructor.
   *
   * @since 4.4
   */
  function __construct() {}

  /**
   * Dummy magic method to prevent CiviCRM_For_WordPress from being cloned.
   *
   * @since 4.4
   */
  public function __clone() {
    _doing_it_wrong( __FUNCTION__, __( 'Only one instance of CiviCRM_For_WordPress please', 'civicrm' ), '4.4' );
  }

  /**
   * Dummy magic method to prevent CiviCRM_For_WordPress from being unserialized.
   *
   * @since 4.4
   */
  public function __wakeup() {
    _doing_it_wrong( __FUNCTION__, __( 'Please do not serialize CiviCRM_For_WordPress', 'civicrm' ), '4.4' );
  }


  /**
   * Plugin activation.
   *
   * This method is called only when CiviCRM plugin is activated. In order for
   * other plugins to be able to interact with Civi's activation, we wait until
   * after the activation redirect to perform activation actions.
   *
   * @since 4.4
   */
  public function activate() {

    // Set a one-time-only option
    add_option( 'civicrm_activation_in_progress', 'true' );

  }


  /**
   * Run CiviCRM's plugin activation procedure.
   *
   * @since 4.4
   */
  public function activation() {

    // Bail if not activating
    if ( get_option( 'civicrm_activation_in_progress' ) !== 'true' ) {
      return;
    }

    // Bail if not in WordPress admin
    if ( !is_admin() ) {
      return;
    }

    /**
     * Broadcast that activation actions need to happen now.
     *
     * @since 5.6
     */
    do_action( 'civicrm_activation' );

    // Change option so this action never fires again
    update_option( 'civicrm_activation_in_progress', 'false' );
    if ( ! is_multisite() && !isset($_GET['activate-multi']) && ! CIVICRM_INSTALLED ) {
      wp_redirect(admin_url("options-general.php?page=civicrm-install"));
      exit;
    }
  }


  /**
   * Plugin deactivation.
   *
   * This method is called only when CiviCRM plugin is deactivated. In order for
   * other plugins to be able to interact with Civi's activation, we need to
   * remove any options that are set in activate() above.
   *
   * @since 4.4
   */
  public function deactivate() {

    // Delete any options we hay have set
    delete_option( 'civicrm_activation_in_progress' );

    /**
     * Broadcast that deactivation actions need to happen now.
     *
     * @since 5.6
     */
    do_action( 'civicrm_deactivation' );

  }


  /**
   * Set up the CiviCRM plugin instance.
   *
   * @since 4.4
   */
  public function setup_instance() {

    // Kick out if another instance is being inited
    if ( isset( self::$in_wordpress ) ) {
      wp_die( __( 'Only one instance of CiviCRM_For_WordPress please', 'civicrm' ) );
    }

    // Get existing session ID
    $session_id = session_id();

    /*
     * There is no session handling in WP - hence we start it for CiviCRM pages
     * except when running via WP-CLI which does not require sessions.
     */
    if ( empty( $session_id ) && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
      session_start();
    }

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

    // Get classes and instantiate
    $this->include_files();

    // Do plugin activation
    $this->activation();

    // Use translation files
    $this->enable_translation();

    // Register all hooks on init
    add_action( 'init', array( $this, 'register_hooks' ) );

    /**
     * Broadcast that this plugin is now loaded.
     *
     * @since 4.4
     */
    do_action( 'civicrm_instance_loaded' );

  }


  /**
   * Set broad CiviCRM context.
   *
   * Setter for determining if CiviCRM is currently being displayed in WordPress.
   * This becomes true whe CiviCRM is called in the following contexts:
   *
   * (a) in the WordPress back-end
   * (b) when CiviCRM content is being displayed on the front-end via wpBasePage
   * (c) when an AJAX request is made to CiviCRM
   *
   * It is NOT true when CiviCRM is called via a shortcode.
   *
   * @since 4.4
   */
  public function civicrm_in_wordpress_set() {

    // Get identifying query var.
    $page = get_query_var( 'civiwp' );

    // Store
    self::$in_wordpress = ( $page == 'CiviCRM' ) ? TRUE : FALSE;

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
     * @return bool $in_wordpress True if CiviCRM is displayed in WordPress, false otherwise.
     */
    return apply_filters( 'civicrm_in_wordpress', self::$in_wordpress );

  }


  /**
   * Set specific CiviCRM context.
   *
   * Setter for determining how CiviCRM is currently being displayed in WordPress.
   * This can be one of the following contexts:
   *
   * (a) in the WordPress back-end
   * (b) when CiviCRM content is being displayed on the front-end via wpBasePage
   * (c) when a "non-page" request is made to CiviCRM
   * (d) when CiviCRM is called via a shortcode
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
  public function civicrm_context_set( $context ) {

    // Store
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
     * @since 4.4
     *
     * @param bool $context The existing context in which CiviCRM is displayed in WordPress.
     * @return bool $context The modified context in which CiviCRM is displayed in WordPress.
     */
    return apply_filters( 'civicrm_context', self::$context );

  }


  // ---------------------------------------------------------------------------
  // Files
  // ---------------------------------------------------------------------------


  /**
   * Include files.
   *
   * @since 4.4
   */
  public function include_files() {

    // Include users class
    include_once CIVICRM_PLUGIN_DIR . 'includes/civicrm.users.php';
    $this->users = new CiviCRM_For_WordPress_Users;

    // Include shortcodes class
    include_once CIVICRM_PLUGIN_DIR . 'includes/civicrm.shortcodes.php';
    $this->shortcodes = new CiviCRM_For_WordPress_Shortcodes;

    // Include shortcodes modal dialog class
    include_once CIVICRM_PLUGIN_DIR . 'includes/civicrm.shortcodes.modal.php';
    $this->modal = new CiviCRM_For_WordPress_Shortcodes_Modal;

    // Include basepage class
    include_once CIVICRM_PLUGIN_DIR . 'includes/civicrm.basepage.php';
    $this->basepage = new CiviCRM_For_WordPress_Basepage;

    // Include compatibility class
    include_once CIVICRM_PLUGIN_DIR . 'includes/civicrm.compat.php';
    $this->compat = new CiviCRM_For_WordPress_Compat;

    if ( ! class_exists( 'CiviCRM_WP_REST\Autoloader' ) ) {
      // Include REST API autoloader class
      require_once( CIVICRM_PLUGIN_DIR . 'wp-rest/Autoloader.php' );
    }

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

    // Always add the common hooks
    $this->register_hooks_common();

    // When in WordPress admin...
    if ( is_admin() ) {

      // Set context
      $this->civicrm_context_set( 'admin' );

      // Handle WP admin context
      $this->register_hooks_admin();
      return;

    }

    // Go no further if CiviCRM not installed yet
    if ( ! CIVICRM_INSTALLED ) return;

    // Attempt to replace 'page' query arg with 'civiwp'.
    add_filter( 'request', array( $this, 'maybe_replace_page_query_var' ) );

    // Add our query vars.
    add_filter( 'query_vars', array( $this, 'query_vars' ) );

    // Delay everything else until query has been parsed
    add_action( 'parse_query', array( $this, 'register_hooks_front_end' ) );

  }


  /**
   * Register hooks for the front end.
   *
   * @since 5.6
   *
   * @param WP_Query $query The WP_Query instance (passed by reference).
   */
  public function register_hooks_front_end( $query ) {

    // Bail if $query is not the main loop.
    if ( ! $query->is_main_query() ) {
      return;
    }

    // Bail if filters are suppressed on this query.
    if ( true == $query->get( 'suppress_filters' ) ) {
      return;
    }

    // Prevent multiple calls
    static $alreadyRegistered = FALSE;
    if ( $alreadyRegistered ) {
      return;
    }
    $alreadyRegistered = TRUE;

    // Redirect if old query var is present.
    if ( 'CiviCRM' == get_query_var( 'page' ) && 'CiviCRM' != get_query_var( 'civiwp' ) ) {
      $redirect_url = remove_query_arg( 'page', false );
      $redirect_url = add_query_arg( 'civiwp', 'CiviCRM', $redirect_url );
      wp_redirect( $redirect_url, 301 );
      exit();
    }

    // Store context
    $this->civicrm_in_wordpress_set();

    // When embedded via wpBasePage or AJAX call...
    if ( $this->civicrm_in_wordpress() ) {

      /*
       * Directly output CiviCRM html only in a few cases and skip WP templating:
       *
       * (a) when a snippet is set
       * (b) when there is an AJAX call
       * (c) for an iCal feed (unless 'html' is specified)
       * (d) for file download URLs
       */
      if ( ! $this->is_page_request() ) {

        // Set context
        $this->civicrm_context_set( 'nonpage' );

        // Add core resources for front end
        add_action( 'wp', array( $this, 'front_end_page_load' ) );

        // Echo all output when WP has been set up but nothing has been rendered
        add_action( 'wp', array( $this, 'invoke' ) );
        return;

      }

      // Set context
      $this->civicrm_context_set( 'basepage' );

      // If we get here, we must be in a wpBasePage context
      $this->basepage->register_hooks();
      return;

    }

    // Set context
    $this->civicrm_context_set( 'shortcode' );

    // That leaves us with handling shortcodes, should they exist
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

    if ( ! class_exists( 'CiviCRM_WP_REST\Plugin' ) ) {

      // Set up REST API.
      CiviCRM_WP_REST\Autoloader::add_source( $source_path = trailingslashit( CIVICRM_PLUGIN_DIR . 'wp-rest' ) );

      // Init REST API.
      new CiviCRM_WP_REST\Plugin;

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

    // Bail if CiviCRM is not using clean URLs.
    if (!defined('CIVICRM_CLEANURL') || CIVICRM_CLEANURL !== 1) {
      return;
    }

    // Have we flushed rewrite rules?
    if ( get_option( 'civicrm_rules_flushed' ) !== 'true' ) {

      // Apply custom rewrite rules, then flush rules afterwards.
      $this->rewrite_rules( true );

      // Set a one-time-only option to flag that this has been done.
      add_option( 'civicrm_rules_flushed', 'true' );

    } else {

      // Apply custom rewrite rules normally.
      $this->rewrite_rules();

    }

  }


  /**
   * Register hooks to handle CiviCRM in a WordPress admin context.
   *
   * @since 4.4
   */
  public function register_hooks_admin() {

    // Modify the admin menu
    add_action( 'admin_menu', array( $this, 'add_menu_items' ) );

    // Set page title
    add_filter( 'admin_title', array( $this, 'set_admin_title' ) );

    // Print CiviCRM's header
    add_action('admin_head', array( $this, 'wp_head' ), 50);

    // Listen for changes to the basepage setting
    add_action( 'civicrm_postSave_civicrm_setting', array( $this, 'settings_change' ), 10 );

    // If settings file does not exist, show notice with link to installer
    if ( ! CIVICRM_INSTALLED ) {
      if ( isset( $_GET['page'] ) && $_GET['page'] == 'civicrm-install' ) {
        // Set install type
        $_GET['civicrm_install_type'] = 'wordpress';
      } else {
        // Show notice
        add_action( 'admin_notices', array( $this, 'show_setup_warning' ) );
      }
    }

    // Enable shortcode modal
    $this->modal->register_hooks();

  }


  /**
   * Force rewrite rules to be recreated.
   *
   * When CiviCRM settings are saved, the method is called post-save. It checks
   * if it's the WordPress Base Page setting that has been saved and causes all
   * rewrite rules to be flushed on the next page load.
   *
   * @since 5.14
   *
   * @param obj $dao The CiviCRM database access object.
   */
  public function settings_change( $dao ) {

    // Delete the option if conditions are met
    if ( $dao instanceOf CRM_Core_DAO_Setting ) {
      if ( isset( $dao->name ) && $dao->name == 'wpBasePage' ) {
        delete_option( 'civicrm_rules_flushed' );
      }
    }

  }


  /**
   * Add our rewrite rules.
   *
   * @since 5.7
   *
   * @param bool $flush_rewrite_rules True if rules should be flushed, false otherwise.
   */
  public function rewrite_rules( $flush_rewrite_rules = false ) {

    // Kick out if not CiviCRM
    if (!$this->initialize()) {
      return;
    }

    // Get config
    $config = CRM_Core_Config::singleton();

    // Get basepage object
    $basepage = get_page_by_path( $config->wpBasePage );

    // Sanity check
    if (!is_object($basepage)) {
        return;
    }

    // Let's add rewrite rule when viewing the basepage
    add_rewrite_rule(
      '^' . $config->wpBasePage . '/([^?]*)?',
      'index.php?page_id=' . $basepage->ID . '&civiwp=CiviCRM&q=civicrm%2F$matches[1]',
      'top'
    );

    // Maybe force flush
    if ($flush_rewrite_rules) {
      flush_rewrite_rules();
    }

    /**
     * Broadcast the rewrite rules event.
     *
     * @since 5.7
     * @since 5.24 Added $basepage parameter.
     *
     * @param bool $flush_rewrite_rules True if rules flushed, false otherwise.
     * @param WP_Post $basepage The Basepage post object.
     */
    do_action( 'civicrm_after_rewrite_rules', $flush_rewrite_rules, $basepage );

  }


  /**
   * Add our query vars.
   *
   * @since 5.7
   *
   * @param array $query_vars The existing query vars.
   * @return array $query_vars The modified query vars.
   */
  public function query_vars( $query_vars ) {

    // Sanity check
    if (!is_array($query_vars)) {
      $query_vars = array();
    }

    // Build our query vars
    $civicrm_query_vars = array(
      'civiwp', 'q', 'reset', 'id', 'html', 'snippet', // URL query vars
      'action', 'mode', 'cid', 'gid', 'sid', 'cs', 'force', // Shortcode query vars
    );

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
     * @return array $civicrm_query_vars The modified set of query vars.
     */
    $civicrm_query_vars = apply_filters( 'civicrm_query_vars', $civicrm_query_vars );

    // Now add them to WordPress
    foreach( $civicrm_query_vars as $civicrm_query_var ) {
      $query_vars[] = $civicrm_query_var;
    }

    return $query_vars;

  }


  /**
   * Filters the request right after WP's
   * parsed it and replaces the 'page' query
   * variable with 'civiwp' if applicable.
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
  public function maybe_replace_page_query_var( $query_vars ) {

    $civi_query_arg = array_search( 'CiviCRM', $query_vars );

    // Bail if the query var is not 'page'.
    if ( false === $civi_query_arg || $civi_query_arg !== 'page' ) return $query_vars;

    unset( $query_vars['page'] );
    $query_vars['civiwp'] = 'CiviCRM';

    return $query_vars;

  }


  // ---------------------------------------------------------------------------
  // CiviCRM Initialisation
  // ---------------------------------------------------------------------------

  /**
   * Check that the PHP version is supported. If not, raise a fatal error with a pointed message.
   *
   * One should check this before bootstrapping Civi - after we start the class-loader, the
   * PHP-compatibility errors will become more ugly.
   */
  protected function assertPhpSupport() {
    if ( version_compare( PHP_VERSION, CIVICRM_WP_PHP_MINIMUM ) < 0 ) {
      echo '<p>' .
         sprintf(
          __( 'CiviCRM requires PHP version %1$s or greater. You are running PHP version %2$s', 'civicrm' ),
          CIVICRM_WP_PHP_MINIMUM,
          PHP_VERSION
         ) .
         '<p>';
      exit();
    }
  }

  /**
   * Initialize CiviCRM.
   *
   * @since 4.4
   *
   * @return bool $success True if CiviCRM is initialized, false otherwise.
   */
  public function initialize() {

    static $initialized = FALSE;
    static $failure = FALSE;

    if ( $failure ) {
      return FALSE;
    }

    if ( ! $initialized ) {

      $this->assertPhpSupport();

      // Check for settings
      if ( ! CIVICRM_INSTALLED ) {
        $error = FALSE;
      } elseif ( file_exists( CIVICRM_SETTINGS_PATH) ) {
        $error = include_once ( CIVICRM_SETTINGS_PATH );
      }

      // Autoload
      require_once 'CRM/Core/ClassLoader.php';
      CRM_Core_ClassLoader::singleton()->register();

      // Get ready for problems
      $installLink    = admin_url() . "options-general.php?page=civicrm-install";
      $docLinkInstall = "https://wiki.civicrm.org/confluence/display/CRMDOC/Installing+CiviCRM+for+WordPress";
      $docLinkTrouble = "https://wiki.civicrm.org/confluence/display/CRMDOC/Installation+and+Upgrades";
      $forumLink      = "https://civicrm.stackexchange.com/";


      // Construct message
      $errorMsgAdd = sprintf(
        __( 'Please review the <a href="%s">WordPress Installation Guide</a> and the <a href="%s">Trouble-shooting page</a> for assistance. If you still need help installing, you can often find solutions to your issue by searching for the error message in the <a href="%s">installation support section of the community forum</a>.', 'civicrm' ),
        $docLinkInstall,
        $docLinkTrouble,
        $forumLink
      );

      // Does install message get used?
      $installMessage = sprintf(
        __( 'Click <a href="%s">here</a> for fresh install.', 'civicrm' ),
        $installLink
      );

      if ($error == FALSE) {
        header( 'Location: ' . admin_url() . 'options-general.php?page=civicrm-install' );
        return FALSE;
      }

      // Access global defined in civicrm.settings.php
      global $civicrm_root;

      // This does pretty much all of the civicrm initialization
      if ( ! file_exists( $civicrm_root . 'CRM/Core/Config.php' ) ) {
        $error = FALSE;
      } else {
        $error = include_once ( 'CRM/Core/Config.php' );
      }

      // Have we got it?
      if ( $error == FALSE ) {

        // Set static flag
        $failure = TRUE;

        // FIX ME - why?
        wp_die(
          "<strong><p class='error'>" .
          sprintf(
            __( 'Oops! - The path for including CiviCRM code files is not set properly. Most likely there is an error in the <em>civicrm_root</em> setting in your CiviCRM settings file (%s).', 'civicrm' ),
            CIVICRM_SETTINGS_PATH
          ) .
          "</p><p class='error'> &raquo; " .
          sprintf(
            __( 'civicrm_root is currently set to: <em>%s</em>.', 'civicrm' ),
            $civicrm_root
          ) .
          "</p><p class='error'>" . $errorMsgAdd . "</p></strong>"
        );

        // Won't reach here!
        return FALSE;

      }

      // Set static flag
      $initialized = TRUE;

      // Initialize the system by creating a config object
      $config = CRM_Core_Config::singleton();

      // Sync the logged in user with WP
      global $current_user;
      if ( $current_user ) {

        // Sync procedure sets session values for logged in users
        require_once 'CRM/Core/BAO/UFMatch.php';
        CRM_Core_BAO_UFMatch::synchronize(
          $current_user, // User object
          FALSE, // Do not update
          'WordPress', // CMS
          $this->users->get_civicrm_contact_type('Individual')
        );

      }

    }

    /**
     * Broadcast that CiviCRM is now initialized.
     *
     * @since 4.4
     */
    do_action( 'civicrm_initialized' );

    // Success!
    return TRUE;

  }


  // ---------------------------------------------------------------------------
  // Plugin setup
  // ---------------------------------------------------------------------------


  /**
   * Load translation files.
   *
   * A good reference on how to implement translation in WordPress:
   * http://ottopress.com/2012/internationalization-youre-probably-doing-it-wrong/
   *
   * @since 4.4
   */
  public function enable_translation() {

    // Load translations
    load_plugin_textdomain(
      'civicrm', // Unique name
      FALSE, // Deprecated argument
      dirname( plugin_basename( __FILE__ ) ) . '/languages/' // Relative path to translation files
    );

  }


  /**
   * Adds menu items to WordPress admin menu.
   *
   * Callback method for 'admin_menu' hook as set in register_hooks().
   *
   * @since 4.4
   */
  public function add_menu_items() {

    $civilogo = file_get_contents( plugin_dir_path( __FILE__ ) . 'assets/civilogo.svg.b64' );

    /**
     * Filter the position of the CiviCRM menu item.
     *
     * Currently set to 3.9 + some random digits to reduce risk of conflict.
     *
     * @since 4.4
     *
     * @param float The default menu position.
     * @return float The modified menu position..
     */
    $position = apply_filters( 'civicrm_menu_item_position', '3.904981' );

    // Check for settings file
    if ( CIVICRM_INSTALLED ) {

      // Add top level menu item
      $menu_page = add_menu_page(
        __( 'CiviCRM', 'civicrm' ),
        __( 'CiviCRM', 'civicrm' ),
        'access_civicrm',
        'CiviCRM',
        array( $this, 'invoke' ),
        $civilogo,
        $position
      );

      // Add core resources prior to page load
      add_action( 'load-' . $menu_page, array( $this, 'admin_page_load' ) );

    } else {

      // Add top level menu item
      $menu_page = add_menu_page(
        __( 'CiviCRM Installer', 'civicrm' ),
        __( 'CiviCRM Installer', 'civicrm' ),
        'manage_options',
        'civicrm-install',
        array( $this, 'run_installer' ),
        $civilogo,
        $position
      );

      /*
      // Add scripts and styles like this
      add_action( 'admin_print_scripts-' . $menu_page, array( $this, 'admin_installer_js' ) );
      add_action( 'admin_print_styles-' . $menu_page, array( $this, 'admin_installer_css' ) );
      add_action( 'admin_head-' . $menu_page, array( $this, 'admin_installer_head' ), 50 );
      */

    }

  }


  // ---------------------------------------------------------------------------
  // Installation
  // ---------------------------------------------------------------------------


  /**
   * Callback method for add_options_page() that runs the CiviCRM installer.
   *
   * @since 4.4
   */
  public function run_installer() {
    $this->assertPhpSupport();
    $civicrmCore = CIVICRM_PLUGIN_DIR . 'civicrm';

    $setupPaths = array(
      implode(DIRECTORY_SEPARATOR, ['vendor', 'civicrm', 'civicrm-setup']),
      implode(DIRECTORY_SEPARATOR, ['packages', 'civicrm-setup',]),
      implode(DIRECTORY_SEPARATOR, ['setup']),
    );
    foreach ($setupPaths as $setupPath) {
      $loader = implode(DIRECTORY_SEPARATOR, [$civicrmCore, $setupPath, 'civicrm-setup-autoload.php']);
      if (file_exists($civicrmCore . DIRECTORY_SEPARATOR . '.use-civicrm-setup') && file_exists($loader)) {
        require_once $loader;
        require_once implode(DIRECTORY_SEPARATOR, [$civicrmCore, 'CRM', 'Core', 'ClassLoader.php']);
        CRM_Core_ClassLoader::singleton()->register();
        \Civi\Setup::assertProtocolCompatibility(1.0);
        \Civi\Setup::init([
          'cms' => 'WordPress',
          'srcPath' => $civicrmCore,
        ]);
        $ctrl = \Civi\Setup::instance()->createController()->getCtrl();
        $ctrl->setUrls(array(
          'ctrl' => admin_url() . "options-general.php?page=civicrm-install",
          'res' => CIVICRM_PLUGIN_URL . 'civicrm/' . strtr($setupPath, DIRECTORY_SEPARATOR, '/') . '/res/',
          'jquery.js' => CIVICRM_PLUGIN_URL . 'civicrm/bower_components/jquery/dist/jquery.min.js',
          'font-awesome.css' => CIVICRM_PLUGIN_URL . 'civicrm/bower_components/font-awesome/css/font-awesome.min.css',
          'finished' => admin_url('admin.php?page=CiviCRM&q=civicrm&reset=1'),
        ));
        \Civi\Setup\BasicRunner::run($ctrl);
        return;
      }
    }

    // Uses CIVICRM_PLUGIN_DIR instead of WP_PLUGIN_DIR
    $installFile =
      CIVICRM_PLUGIN_DIR .
      'civicrm' . DIRECTORY_SEPARATOR .
      'install' . DIRECTORY_SEPARATOR .
      'index.php';

    // Notice: Undefined variable: siteDir in:
    // CIVICRM_PLUGIN_DIR/civicrm/install/index.php on line 456
    include ( $installFile );

  }


  /**
   * Callback method for missing settings file in register_hooks().
   *
   * @since 4.4
   */
  public function show_setup_warning() {

    $installLink = admin_url() . "options-general.php?page=civicrm-install";
    echo '<div id="civicrm-warning" class="updated fade">' .
       '<p><strong>' .
       __( 'CiviCRM is almost ready.', 'civicrm' ) .
       '</strong> ' .
       sprintf(
        __( 'You must <a href="%s">configure CiviCRM</a> for it to work.', 'civicrm' ),
        $installLink
       ) .
       '</p></div>';

  }


  // ---------------------------------------------------------------------------
  // HTML head
  // ---------------------------------------------------------------------------


  /**
   * Perform necessary stuff prior to CiviCRM's admin page being loaded
   * This needs to be a method because it can then be hooked into WP at the
   * right time.
   *
   * @since 4.6
   */
  public function admin_page_load() {

    // This is required for AJAX calls in WordPress admin
    $_REQUEST['noheader'] = $_GET['noheader'] = TRUE;

    // Add resources for back end
    $this->add_core_resources( FALSE );

    // Check setting for path to wp-load.php
    $this->add_wpload_setting();

  }


  /**
   * When CiviCRM is loaded in WP Admin, check for the existence of a setting
   * which holds the path to wp-load.php. This is the only reliable way to
   * bootstrap WordPress from CiviCRM.
   *
   * CMW: I'm not entirely happy with this approach, because the value will be
   * different for different installs (e.g. when a dev site is migrated to live)
   * A better approach would be to store this setting in civicrm.settings.php as
   * a constant, but doing that involves a complicated process of getting a new
   * setting registered in the installer.
   *
   * Also, it needs to be decided whether this value should be tied to a CiviCRM
   * 'domain', since a single CiviCRM install could potentially be used by a
   * number of WordPress installs. This is not relevant to its use in WordPress
   * Multisite, because the path to wp-load.php is common to all sites on the
   * network.
   *
   * My final concern is that the value will only be set *after* someone visits
   * CiviCRM in the back end. I have restricted it to this so as not to add
   * overhead to the front end, but there remains the possibility that the value
   * could be missing. To repeat: this would be better in civicrm.settings.php.
   *
   * To get the path to wp-load.php, use:
   * $path = CRM_Core_BAO_Setting::getItem('CiviCRM Preferences', 'wpLoadPhp');
   *
   * @since 4.6.3
   */
  public function add_wpload_setting() {

    if (!$this->initialize()) {
      return;
    }

    // Get path to wp-load.php
    $path = ABSPATH . 'wp-load.php';

    // Get the setting, if it exists
    $setting = CRM_Core_BAO_Setting::getItem('CiviCRM Preferences', 'wpLoadPhp');

    // If we don't have one, create it
    if ( is_null( $setting ) ) {
      CRM_Core_BAO_Setting::setItem($path, 'CiviCRM Preferences', 'wpLoadPhp');
    }

    // Is it different to the one we've stored?
    if ( $setting !== $path ) {
      // Yes - set new path (this could be because we've changed server or location)
      CRM_Core_BAO_Setting::setItem($path, 'CiviCRM Preferences', 'wpLoadPhp');
    }

  }


  /**
   * Perform necessary stuff prior to CiviCRM being loaded on the front end
   * This needs to be a method because it can then be hooked into WP at the
   * right time.
   *
   * @since 4.6
   */
  public function front_end_page_load() {

    static $frontend_loaded = FALSE;
    if ( $frontend_loaded ) {
      return;
    }

    // Add resources for front end
    $this->add_core_resources( TRUE );

    // Merge CiviCRM's HTML header with the WordPress theme's header
    add_action( 'wp_head', array( $this, 'wp_head' ) );

    // Set flag so this only happens once
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
    if ( $frontend_css_loaded ) {
      return;
    }

    if (!$this->initialize()) {
      return;
    }

    $config = CRM_Core_Config::singleton();

    // Default custom CSS to standalone
    $dependent = NULL;

    // Load core CSS
    if (!CRM_Core_BAO_Setting::getItem(CRM_Core_BAO_Setting::SYSTEM_PREFERENCES_NAME, 'disable_core_css')) {

      // Enqueue stylesheet
      wp_enqueue_style(
        'civicrm_css',
        $config->resourceBase . 'css/civicrm.css',
        NULL, // Dependencies
        CIVICRM_PLUGIN_VERSION, // Version
        'all' // Media
      );

      // Custom CSS is dependent
      $dependent = array( 'civicrm_css' );

    }

    // Load custom CSS
    if (!empty($config->customCSSURL)) {
      wp_enqueue_style(
        'civicrm_custom_css',
        $config->customCSSURL,
        $dependent, // Dependencies
        CIVICRM_PLUGIN_VERSION, // Version
        'all' // Media
      );
    }

    // Set flag so this only happens once
    $frontend_css_loaded = TRUE;

  }


  /**
   * Add CiviCRM core resources.
   *
   * @since 4.6
   *
   * @param bool $front_end True if on WP front end, false otherwise.
   */
  public function add_core_resources( $front_end = TRUE ) {

    if (!$this->initialize()) {
      return;
    }

    $config = CRM_Core_Config::singleton();
    $config->userFrameworkFrontend = $front_end;

    // Add CiviCRM core resources
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

    // CRM-11823 - If CiviCRM bootstrapped, then merge its HTML header with the CMS's header
    global $civicrm_root;
    if ( empty( $civicrm_root ) ) {
      return;
    }

    $region = CRM_Core_Region::instance('html-header', FALSE);
    if ( $region ) {
      echo '<!-- CiviCRM html header -->';
      echo $region->render( '' );
    }

  }


  // ---------------------------------------------------------------------------
  // CiviCRM Invocation (this outputs Civi's markup)
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
    if ( $alreadyInvoked ) {
      return;
    }

    // Bail if this is called via a content-preprocessing plugin
    if ( $this->is_page_request() && !in_the_loop() && !is_admin() ) {
      return;
    }

    if (!$this->initialize()) {
      return;
    }

    /*
     * CRM-12523
     * WordPress has it's own timezone calculations
     * CiviCRM relies on the php default timezone which WP
     * overrides with UTC in wp-settings.php
     */
    $wpBaseTimezone = date_default_timezone_get();
    $wpUserTimezone = get_option('timezone_string');
    if ($wpUserTimezone) {
      date_default_timezone_set($wpUserTimezone);
      CRM_Core_Config::singleton()->userSystem->setMySQLTimeZone();
    }

    /*
     * CRM-95XX
     * At this point we are calling a CiviCRM function
     * WP always quotes the request, CiviCRM needs to reverse what it just did.
     */
    $this->remove_wp_magic_quotes();

    // Required for AJAX calls
    if ($this->civicrm_in_wordpress()) {
      $_REQUEST['noheader'] = $_GET['noheader'] = TRUE;
    }

    // Code inside invoke() requires the current user to be set up
    $current_user = wp_get_current_user();

    /*
     * Bypass synchronize if running upgrade to avoid any serious non-recoverable
     * error which might hinder the upgrade process.
     */
    if ( CRM_Utils_Array::value('q', $_GET) != 'civicrm/upgrade' ) {
      $this->users->sync_user( $current_user );
    }

    // Set flag
    $alreadyInvoked = TRUE;

    // Get args
    $argdata = $this->get_request_args();

    // Set dashboard as default if args are empty
    if ( empty( $argdata['argString'] ) ) {
      $_GET['q'] = 'civicrm/dashboard';
      $_GET['reset'] = 1;
      $argdata['args'] = array('civicrm', 'dashboard');
    }

    // Do the business
    CRM_Core_Invoke::invoke($argdata['args']);

    // Restore WP's timezone
    if ($wpBaseTimezone) {
      date_default_timezone_set($wpBaseTimezone);
    }

    // Restore WP's arrays
    $this->restore_wp_magic_quotes();

    /**
     * Broadcast that CiviCRM has been invoked.
     *
     * @since 4.4
     */
    do_action( 'civicrm_invoked' );

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

    // Save original arrays
    $this->wp_get     = $_GET;
    $this->wp_post    = $_POST;
    $this->wp_cookie  = $_COOKIE;
    $this->wp_request = $_REQUEST;

    // Reassign globals
    $_GET     = stripslashes_deep( $_GET );
    $_POST    = stripslashes_deep( $_POST );
    $_COOKIE  = stripslashes_deep( $_COOKIE );
    $_REQUEST = stripslashes_deep( $_REQUEST );

    // Test for query var
    $q = get_query_var( 'q' );
    if (!empty($q)) {

      $page = get_query_var( 'civiwp' );
      $reset = get_query_var( 'reset' );
      $id = get_query_var( 'id' );
      $html = get_query_var( 'html' );
      $snippet = get_query_var( 'snippet' );

      $action = get_query_var( 'action' );
      $mode = get_query_var( 'mode' );
      $cid = get_query_var( 'cid' );
      $gid = get_query_var( 'gid' );
      $sid = get_query_var( 'sid' );
      $cs = get_query_var( 'cs' );
      $force = get_query_var( 'force' );

      $_REQUEST['q'] = $_GET['q'] = $q;
      $_REQUEST['civiwp'] = $_GET['civiwp'] = 'CiviCRM';
      if (!empty($reset)) { $_REQUEST['reset'] = $_GET['reset'] = $reset; }
      if (!empty($id)) { $_REQUEST['id'] = $_GET['id'] = $id; }
      if (!empty($html)) { $_REQUEST['html'] = $_GET['html'] = $html; }
      if (!empty($snippet)) { $_REQUEST['snippet'] = $_GET['snippet'] = $snippet; }

      if (!empty($action)) { $_REQUEST['action'] = $_GET['action'] = $action; }
      if (!empty($mode)) { $_REQUEST['mode'] = $_GET['mode'] = $mode; }
      if (!empty($cid)) { $_REQUEST['cid'] = $_GET['cid'] = $cid; }
      if (!empty($gid)) { $_REQUEST['gid'] = $_GET['gid'] = $gid; }
      if (!empty($sid)) { $_REQUEST['sid'] = $_GET['sid'] = $sid; }
      if (!empty($cs)) { $_REQUEST['cs'] = $_GET['cs'] = $cs; }
      if (!empty($force)) { $_REQUEST['force'] = $_GET['force'] = $force; }

      /**
       * Broadcast that CiviCRM query vars have been assigned.
       *
       * Use in combination with `civicrm_query_vars` filter to ensure that any
       * other query vars are included in the assignment to the super-global
       * arrays.
       *
       * @since 5.7
       */
      do_action( 'civicrm_query_vars_assigned' );

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

    // Restore original arrays
    $_GET     = $this->wp_get;
    $_POST    = $this->wp_post;
    $_COOKIE  = $this->wp_cookie;
    $_REQUEST = $this->wp_request;

  }


  /**
   * Detect Ajax, snippet, or file requests.
   *
   * @since 4.4
   *
   * @return boolean True if request is for a CiviCRM page, false otherwise.
   */
  public function is_page_request() {

    // Assume not a CiviCRM page
    $return = FALSE;

    // Kick out if not CiviCRM
    if (!$this->initialize()) {
      return $return;
    }

    // Get args
    $argdata = $this->get_request_args();

    // Grab query var
    $html = get_query_var( 'html' );
    if (empty($html)) {
      $html = isset($_GET['html']) ? $_GET['html'] : '';
    }

    /*
     * FIXME: It's not sustainable to hardcode a whitelist of all of non-HTML
     * pages. Maybe the menu-XML should include some metadata to make this
     * unnecessary?
     */
    if (CRM_Utils_Array::value('HTTP_X_REQUESTED_WITH', $_SERVER) == 'XMLHttpRequest'
        || ($argdata['args'][0] == 'civicrm' && in_array($argdata['args'][1], array('ajax', 'file')) )
        || !empty($_REQUEST['snippet'])
        || strpos($argdata['argString'], 'civicrm/event/ical') === 0 && empty($html)
        || strpos($argdata['argString'], 'civicrm/contact/imagefile') === 0
    ) {
      $return = FALSE;
    }
    else {
      $return = TRUE;
    }

    return $return;

  }


  /**
   * Get arguments and request string from query vars.
   *
   * @since 4.6
   *
   * @return array $argdata Array containing request arguments and request string.
   */
  public function get_request_args() {

    $argString = NULL;
    $args = array();

    // Get path from query vars
    $q = get_query_var( 'q' );
    if (empty($q)) {
      $q = isset($_GET['q']) ? $_GET['q'] : '';
    }

    // Fix 'civicrm/civicrm' elements derived from CRM:url()
    // @see https://lab.civicrm.org/dev/rc/issues/5#note_16205
    if (defined('CIVICRM_CLEANURL') && CIVICRM_CLEANURL) {
      if (substr($q, 0, 16) === 'civicrm/civicrm/') {
        $q = str_replace('civicrm/civicrm/', 'civicrm/', $q);
        $_REQUEST['q'] = $_GET['q'] = $q;
        set_query_var( 'q', $q );
      }
    }

    if (!empty($q)) {
      $argString = trim($q);
      $args = explode('/', $argString);
    }
    $args = array_pad($args, 2, '');

    return array(
      'args' => $args,
      'argString' => $argString
    );

  }


  /**
   * Add CiviCRM's title to the header's <title> tag.
   *
   * @since 4.6
   *
   * @param string $title The title to set.
   * @return string The computed title.
   */
  public function set_admin_title($title) {
    global $civicrm_wp_title;
    if (!$civicrm_wp_title) {
      return $title;
    }
    // Replace 1st occurance of "CiviCRM" in the title
    $pos = strpos($title, 'CiviCRM');
    if ($pos !== FALSE) {
      return substr_replace($title, $civicrm_wp_title, $pos, 7);
    }
    return $civicrm_wp_title;
  }


  /**
   * Override a WordPress page title with the CiviCRM entity title.
   *
   * Callback method for 'single_page_title' hook, always called from WP front-end.
   *
   * @since 4.6
   *
   * @param string $post_title The title of the WordPress page or post.
   * @param object $post The WordPress post object the title applies to.
   * @return string $civicrm_wp_title The title of the CiviCRM entity.
   */
  public function single_page_title( $post_title, $post ) {

    // Sanity check and override
    global $civicrm_wp_title;
    if (!empty($civicrm_wp_title)) {
      return $civicrm_wp_title;
    }

    // Fallback
    return $post_title;

  }


  /**
   * Remove edit link from page content.
   *
   * Callback from 'edit_post_link' hook.
   *
   * @since 4.6
   *
   * @return string Always empty.
   */
  public function clear_edit_post_link() {
    return '';
  }


  /**
   * Remove edit link in WP Admin Bar.
   *
   * Callback from 'wp_before_admin_bar_render' hook.
   *
   * @since 4.6
   */
  public function clear_edit_post_menu_item() {

    // Access object
    global $wp_admin_bar;

    // Bail if in admin
    if ( is_admin() ) return;

    // Remove the menu item from front end
    $wp_admin_bar->remove_menu( 'edit' );

  }


  /**
   * Get base URL.
   *
   * Clone of CRM_Utils_System_WordPress::getBaseUrl() whose access is set to
   * private. Until it is public, we cannot access the URL of the basepage since
   * CRM_Utils_System_WordPress::url().
   *
   * 27-09-2016
   * CRM-16421 CRM-17633 WIP Changes to support WP in it's own directory
   * https://wiki.civicrm.org/confluence/display/CRM/WordPress+installed+in+its+own+directory+issues
   * For now leave hard coded wp-admin references.
   * TODO: remove wp-admin references and replace with admin_url() in the future.
   * TODO: Look at best way to get path to admin_url.
   *
   * @since 4.4
   *
   * @param bool $absolute Passing TRUE prepends the scheme and domain, FALSE doesn't.
   * @param bool $frontend Passing FALSE returns the admin URL.
   * @param $forceBackend Passing TRUE overrides $frontend and returns the admin URL.
   * @return mixed|null|string
   */
  public function get_base_url($absolute, $frontend, $forceBackend) {
    $config = CRM_Core_Config::singleton();
    if ((is_admin() && !$frontend) || $forceBackend) {
      return Civi::paths()->getUrl('[wp.backend]/.', $absolute ? 'absolute' : 'relative');
    }
    else {
      return Civi::paths()->getUrl('[wp.frontend]/.', $absolute ? 'absolute' : 'relative');
    }
  }


} // Class CiviCRM_For_WordPress ends


/*
--------------------------------------------------------------------------------
Procedures start here
--------------------------------------------------------------------------------
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
 * See CiviCRM_For_WordPress::setup_instance()
 */
civi_wp();


/*
 * Tell WordPress to call plugin activation method - no longer calls legacy
 * global scope function.
 */
register_activation_hook( CIVICRM_PLUGIN_FILE, array( civi_wp(), 'activate' ) );


/*
 * Tell WordPress to call plugin deactivation method - needed in order to reset
 * the option that is set on activation.
 */
register_deactivation_hook( CIVICRM_PLUGIN_FILE, array( civi_wp(), 'deactivate' ) );


// Uninstall uses the 'uninstall.php' method
// See: http://codex.wordpress.org/Function_Reference/register_uninstall_hook



/*
--------------------------------------------------------------------------------
The global scope functions below are to maintain backwards compatibility with
previous versions of the CiviCRM WordPress plugin.
--------------------------------------------------------------------------------
*/


/**
 * Add CiviCRM access capabilities to WordPress roles.
 *
 * Called by postProcess() in civicrm/CRM/ACL/Form/WordPress/Permissions.php
 * Also a callback for the 'init' hook in civi_wp()->register_hooks()
 *
 * @since 4.3
 */
function wp_civicrm_capability() {
  civi_wp()->users->set_access_capabilities();
}

/**
 * Test if CiviCRM is currently being displayed in WordPress.
 *
 * Called by setTitle() in civicrm/CRM/Utils/System/WordPress.php
 * Also called at the top of this plugin file to determine AJAX status
 *
 * @since 4.3
 *
 * @return bool True if CiviCRM is displayed in WordPress, false otherwise.
 */
function civicrm_wp_in_civicrm() {
  return civi_wp()->civicrm_in_wordpress();
}

/**
 * This was the original name of the initialization function and is
 * retained for backward compatibility.
 *
 * @since 4.3
 *
 * @return bool True if CiviCRM is initialized, false otherwise.
 */
function civicrm_wp_initialize() {
  return civi_wp()->initialize();
}

/**
 * Initialize CiviCRM. Call this function from other modules too if
 * they use the CiviCRM API.
 *
 * @since 4.3
 *
 * @return bool True if CiviCRM is initialized, false otherwise.
 */
function civicrm_initialize() {
  return civi_wp()->initialize();
}

/**
 * Callback from 'edit_post_link' hook to remove edit link in civicrm_set_post_blank().
 *
 * @since 4.3
 *
 * @return string Always empty.
 */
function civicrm_set_blank() {
  return civi_wp()->clear_edit_post_link();
}

/**
 * Authentication function used by civicrm_wp_frontend().
 *
 * @since 4.3
 *
 * @param array $args The page arguments array.
 * @return bool True if authenticated, false otherwise.
 */
function civicrm_check_permission( $args ) {
  return civi_wp()->users->check_permission( $args );
}

/**
 * Called when authentication fails in civicrm_wp_frontend().
 *
 * @since 4.3
 *
 * @return string Warning message.
 */
function civicrm_set_frontendmessage() {
  return civi_wp()->users->get_permission_denied();
}

/**
 * Invoke CiviCRM in a WordPress context.
 *
 * Callback function from add_menu_page().
 * Callback from WordPress 'init' and 'the_content' hooks.
 * Also used by civicrm_wp_shortcode_includes() and _civicrm_update_user().
 *
 * @since 4.3
 */
function civicrm_wp_invoke() {
  civi_wp()->invoke();
}

/**
 * Method that runs only when civicrm plugin is activated.
 *
 * @since 4.3
 */
function civicrm_activate() {
  civi_wp()->activate();
}

/**
 * Set WordPress user capabilities.
 *
 * Function to create anonymous_user' role, if 'anonymous_user' role is not in
 * the wordpress installation and assign minimum capabilities for all wordpress roles.
 * This function is called on plugin activation and also from upgrade_4_3_alpha1().
 *
 * @since 4.3
 */
function civicrm_wp_set_capabilities() {
  civi_wp()->users->set_wp_user_capabilities();
}

/**
 * Callback function for add_options_page() that runs the CiviCRM installer.
 *
 * @since 4.3
 */
function civicrm_run_installer() {
  civi_wp()->run_installer();
}

/**
 * Function to get the contact type.
 *
 * @since 4.3
 *
 * @param string $default The contact type.
 * @return string $ctype The contact type.
 */
function civicrm_get_ctype( $default = NULL ) {
  return civi_wp()->users->get_civicrm_contact_type( $default );
}

/**
 * Getter function for global $wp_set_breadCrumb.
 *
 * Called by appendBreadCrumb() in civicrm/CRM/Utils/System/WordPress.php
 *
 * @since 4.3
 *
 * @return string $wp_set_breadCrumb The breadcrumb markup.
 */
function wp_get_breadcrumb() {
  global $wp_set_breadCrumb;
  return $wp_set_breadCrumb;
}

/**
 * Setter function for global $wp_set_breadCrumb.
 *
 * Called by appendBreadCrumb() in civicrm/CRM/Utils/System/WordPress.php
 * Called by resetBreadCrumb() in civicrm/CRM/Utils/System/WordPress.php
 *
 * @since 4.3
 *
 * @param string $breadCrumb The desired breadcrumb markup.
 * @return string $wp_set_breadCrumb The breadcrumb markup.
 */
function wp_set_breadcrumb( $breadCrumb ) {
  global $wp_set_breadCrumb;
  $wp_set_breadCrumb = $breadCrumb;
  return $wp_set_breadCrumb;
}


/**
 * Incorporate WP-CLI Integration.
 *
 * Based on drush civicrm functionality, work done by Andy Walker.
 * https://github.com/andy-walker/wp-cli-civicrm
 *
 * @since 4.5
 */
if ( defined('WP_CLI') && WP_CLI ) {
  // Changed from __DIR__ because of possible symlink issues
  include_once CIVICRM_PLUGIN_DIR . 'wp-cli/civicrm.php';
}
