<?php
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

/**
 * Define CiviCRM_For_WordPress_Admin Class.
 *
 * @since 5.33
 */
class CiviCRM_For_WordPress_Admin {

  /**
   * @var object
   * Plugin object reference.
   * @since 5.33
   * @access public
   */
  public $civi;

  /**
   * @var object
   * Settings page object.
   * @since 5.34
   * @access public
   */
  public $page_options;

  /**
   * @var object
   * Integration page object.
   * @since 5.34
   * @access public
   */
  public $page_integration;

  /**
   * @var object
   * Quick Add meta box object.
   * @since 5.34
   * @access public
   */
  public $metabox_quick_add;

  /**
   * Instance constructor.
   *
   * @since 5.33
   */
  public function __construct() {

    // Store reference to CiviCRM plugin object.
    $this->civi = civi_wp();

    // Include class files and instantiate.
    $this->include_files();
    $this->setup_objects();

    // Filter Heartbeat on CiviCRM admin pages as late as is practical.
    add_filter('heartbeat_settings', [$this, 'heartbeat'], 1000, 1);

  }

  /**
   * Include files.
   *
   * @since 5.34
   */
  public function include_files() {

    // Include class files.
    include_once CIVICRM_PLUGIN_DIR . 'includes/admin-pages/civicrm.page.options.php';
    include_once CIVICRM_PLUGIN_DIR . 'includes/admin-pages/civicrm.page.integration.php';
    include_once CIVICRM_PLUGIN_DIR . 'includes/admin-metaboxes/civicrm.metabox.contact.add.php';

  }

  /**
   * Instantiate objects.
   *
   * @since 5.34
   */
  public function setup_objects() {

    // Instantiate objects.
    $this->page_options = new CiviCRM_For_WordPress_Admin_Page_Options();
    $this->page_integration = new CiviCRM_For_WordPress_Admin_Page_Integration();
    $this->metabox_quick_add = new CiviCRM_For_WordPress_Admin_Metabox_Contact_Add();

  }

  /**
   * Register hooks on "init" action.
   *
   * @since 4.4
   * @since 5.33 Moved to this class.
   */
  public function register_hooks() {

    // If settings file does not exist, show notice with link to installer.
    if (!CIVICRM_INSTALLED) {
      if (isset($_GET['page']) && $_GET['page'] == 'civicrm-install') {
        // Set install type.
        $_GET['civicrm_install_type'] = 'wordpress';
      }
      else {
        // Show notice.
        add_action('admin_notices', [$this, 'show_setup_warning']);
      }
    }

    // Listen for changes to the basepage setting.
    add_action('civicrm_postSave_civicrm_setting', [$this, 'settings_change'], 10);

    // Prevent auto-updates.
    add_filter('plugin_auto_update_setting_html', [$this, 'auto_update_prevent'], 10, 3);

    // Set page title.
    add_filter('admin_title', [$this, 'set_admin_title']);

    // Modify the admin menu.
    add_action('admin_menu', [$this, 'add_menu_items'], 9);

    // Add CiviCRM's resources in the admin header.
    add_action('admin_head', [$this->civi, 'wp_head'], 50);

    /**
     * Broadcast that this object has registered its callbacks.
     *
     * @since 5.34
     */
    do_action('civicrm/admin/hooks/registered');

  }

  // ---------------------------------------------------------------------------
  // Installation
  // ---------------------------------------------------------------------------

  /**
   * Callback method for missing settings file in register_hooks().
   *
   * @since 4.4
   * @since 5.33 Moved to this class.
   */
  public function show_setup_warning() {

    echo '<div id="civicrm-warning" class="updated fade">';
    echo '<p><strong>' . __('CiviCRM is almost ready.', 'civicrm') . '</strong> ';
    echo sprintf(
      __('You must <a href="%s">configure CiviCRM</a> for it to work.', 'civicrm'),
      admin_url('options-general.php?page=civicrm-install')
    );
    echo '</p></div>';

  }

  /**
   * Callback method for add_options_page() that runs the CiviCRM installer.
   *
   * @since 4.4
   */
  public function run_installer() {

    $this->assert_php_support();

    $civicrmCore = CIVICRM_PLUGIN_DIR . 'civicrm';

    $setupPaths = [
      implode(DIRECTORY_SEPARATOR, ['vendor', 'civicrm', 'civicrm-setup']),
      implode(DIRECTORY_SEPARATOR, ['packages', 'civicrm-setup']),
      implode(DIRECTORY_SEPARATOR, ['setup']),
    ];

    foreach ($setupPaths as $setupPath) {

      $loader = implode(DIRECTORY_SEPARATOR, [$civicrmCore, $setupPath, 'civicrm-setup-autoload.php']);

      if (file_exists($loader)) {
        require_once $loader;
        require_once implode(DIRECTORY_SEPARATOR, [$civicrmCore, 'CRM', 'Core', 'ClassLoader.php']);
        CRM_Core_ClassLoader::singleton()->register();
        \Civi\Setup::assertProtocolCompatibility(1.0);
        \Civi\Setup::init([
          'cms' => 'WordPress',
          'srcPath' => $civicrmCore,
        ]);
        $ctrl = \Civi\Setup::instance()->createController()->getCtrl();
        $ctrl->setUrls([
          'ctrl' => admin_url('options-general.php?page=civicrm-install'),
          'res' => CIVICRM_PLUGIN_URL . 'civicrm/' . strtr($setupPath, DIRECTORY_SEPARATOR, '/') . '/res/',
          'jquery.js' => CIVICRM_PLUGIN_URL . 'civicrm/bower_components/jquery/dist/jquery.min.js',
          'font-awesome.css' => CIVICRM_PLUGIN_URL . 'civicrm/bower_components/font-awesome/css/font-awesome.min.css',
          'finished' => admin_url('admin.php?page=CiviCRM&q=civicrm&reset=1'),
        ]);
        \Civi\Setup\BasicRunner::run($ctrl);
        return;
      }

    }

    wp_die(__('Installer unavailable. Failed to locate CiviCRM libraries.', 'civicrm'));

  }

  /**
   * Check that the PHP version is supported. If not, raise a fatal error with
   * a pointed message.
   *
   * One should check this before bootstrapping CiviCRM - after we start the
   * class-loader, the PHP-compatibility errors will become more ugly.
   *
   * @since 5.18
   * @since 5.33 Moved to this class.
   */
  protected function assert_php_support() {

    if (version_compare(PHP_VERSION, CIVICRM_WP_PHP_MINIMUM) < 0) {
      echo '<p>';
      echo sprintf(
        __('CiviCRM requires PHP version %1$s or greater. You are running PHP version %2$s', 'civicrm'),
        CIVICRM_WP_PHP_MINIMUM,
        PHP_VERSION
      );
      echo '<p>';
      exit();
    }

  }

  // ---------------------------------------------------------------------------
  // Initialisation
  // ---------------------------------------------------------------------------

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

    if ($failure) {
      return FALSE;
    }

    if (!$initialized) {

      $this->assert_php_support();

      // Check for settings.
      if (!CIVICRM_INSTALLED) {
        $error = FALSE;
      }
      elseif (file_exists(CIVICRM_SETTINGS_PATH)) {
        $error = include_once CIVICRM_SETTINGS_PATH;
      }

      // Autoload.
      require_once CIVICRM_PLUGIN_DIR . 'civicrm/CRM/Core/ClassLoader.php';
      CRM_Core_ClassLoader::singleton()->register();

      // Get ready for problems.
      $installLink    = admin_url('options-general.php?page=civicrm-install');
      $docLinkInstall = "https://docs.civicrm.org/installation/en/latest/wordpress/";
      $docLinkTrouble = "https://docs.civicrm.org/sysadmin/en/latest/troubleshooting/";
      $forumLink      = "https://civicrm.stackexchange.com/";

      // Construct message.
      $errorMsgAdd = sprintf(
        __('Please review the <a href="%s">WordPress Installation Guide</a> and the <a href="%s">Trouble-shooting page</a> for assistance. If you still need help installing, you can often find solutions to your issue by searching for the error message in the <a href="%s">installation support section of the community forum</a>.', 'civicrm'),
        $docLinkInstall,
        $docLinkTrouble,
        $forumLink
      );

      // Does install message get used?
      $installMessage = sprintf(
        __('Click <a href="%s">here</a> for fresh install.', 'civicrm'),
        $installLink
      );

      if ($error == FALSE) {
        wp_redirect(admin_url('options-general.php?page=civicrm-install'));
        exit;
      }

      // Access global defined in civicrm.settings.php.
      global $civicrm_root;

      // This does pretty much all of the CiviCRM initialization.
      if (!file_exists($civicrm_root . 'CRM/Core/Config.php')) {
        $error = FALSE;
      }
      else {
        $error = include_once 'CRM/Core/Config.php';
      }

      // Have we got it?
      if ($error == FALSE) {

        // Set static flag.
        $failure = TRUE;

        // FIX ME - why?
        wp_die(
          "<strong><p class='error'>" .
          sprintf(
            __('Oops! - The path for including CiviCRM code files is not set properly. Most likely there is an error in the <em>civicrm_root</em> setting in your CiviCRM settings file (%s).', 'civicrm'),
            CIVICRM_SETTINGS_PATH
          ) .
          "</p><p class='error'> &raquo; " .
          sprintf(
            __('civicrm_root is currently set to: <em>%s</em>.', 'civicrm'),
            $civicrm_root
          ) .
          "</p><p class='error'>" . $errorMsgAdd . "</p></strong>"
        );

        // Won't reach here!
        return FALSE;

      }

      // Set static flag.
      $initialized = TRUE;

      // Initialize the system by creating a config object.
      $config = CRM_Core_Config::singleton();

      // Sync the logged-in WordPress user with CiviCRM.
      global $current_user;
      if ($current_user) {

        // Sync procedure sets session values for logged in users.
        require_once 'CRM/Core/BAO/UFMatch.php';
        CRM_Core_BAO_UFMatch::synchronize(
          // User object.
          $current_user,
          // Do not update.
          FALSE,
          // CMS.
          'WordPress',
          $this->civi->users->get_civicrm_contact_type('Individual')
        );

      }

      /**
       * Broadcast that CiviCRM is now initialized.
       *
       * @since 4.4
       */
      do_action('civicrm_initialized');

    }

    // Success!
    return TRUE;

  }

  /**
   * Slow down the frequency of WordPress heartbeat calls.
   *
   * Heartbeat is important to WordPress for a number of tasks - e.g. checking
   * continued authentication whilst on a page - but it does consume server
   * resources. Reducing the frequency of calls minimises the impact on servers
   * and can make CiviCRM more responsive.
   *
   * @since 5.29
   * @since 5.33 Moved to this class.
   *
   * @param array $settings The existing heartbeat settings.
   * @return array $settings The modified heartbeat settings.
   */
  public function heartbeat($settings) {

    // Access script identifier.
    global $pagenow;

    // Bail if not admin.
    if (!is_admin()) {
      return $settings;
    }

    // Process the requested URL.
    $requested_url = filter_input(INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_URL);
    if ($requested_url) {
      $current_url = wp_unslash($requested_url);
    }
    else {
      $current_url = admin_url();
    }
    $current_screen = wp_parse_url($current_url);

    // Bail if entry is missing for some reason.
    if (!isset($current_screen['query'])) {
      return $settings;
    }

    // Bail if this is not CiviCRM admin.
    if ($pagenow != 'admin.php' || FALSE === strpos($current_screen['query'], 'page=CiviCRM')) {
      return $settings;
    }

    // Defer to any previously set value, but only if it's greater than ours.
    if (!empty($settings['interval']) && intval($settings['interval']) > 120) {
      return $settings;
    }

    // Slow down heartbeat.
    $settings['interval'] = 120;

    return $settings;

  }

  /**
   * Force rewrite rules to be recreated.
   *
   * When CiviCRM settings are saved, the method is called post-save. It checks
   * if it's the WordPress Base Page setting that has been saved and causes all
   * rewrite rules to be flushed on the next page load.
   *
   * @since 5.14
   * @since 5.33 Moved to this class.
   *
   * @param obj $dao The CiviCRM database access object.
   */
  public function settings_change($dao) {

    // Delete the option if conditions are met.
    if ($dao instanceof CRM_Core_DAO_Setting) {
      if (isset($dao->name) && $dao->name == 'wpBasePage') {
        delete_option('civicrm_rules_flushed');
      }
    }

  }

  /**
   * Prevent auto-updates of this plugin in WordPress 5.5.
   *
   * @link https://make.wordpress.org/core/2020/07/15/controlling-plugin-and-theme-auto-updates-ui-in-wordpress-5-5/
   *
   * @since 5.28
   * @since 5.33 Moved to this class.
   */
  public function auto_update_prevent($html, $plugin_file, $plugin_data) {

    // Test for this plugin.
    $this_plugin = plugin_basename(dirname(CIVICRM_PLUGIN_FILE) . '/civicrm.php');
    if ($this_plugin === $plugin_file) {
      $html = __('Auto-updates are not available for this plugin.', 'civicrm');
    }

    // --<
    return $html;

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
    // Replace 1st occurance of "CiviCRM" in the title.
    $pos = strpos($title, 'CiviCRM');
    if ($pos !== FALSE) {
      return substr_replace($title, $civicrm_wp_title, $pos, 7);
    }
    return $civicrm_wp_title;
  }

  /**
   * Adds menu items to WordPress admin menu.
   *
   * Callback method for 'admin_menu' hook as set in register_hooks().
   *
   * @since 4.4
   */
  public function add_menu_items() {

    $civilogo = file_get_contents(CIVICRM_PLUGIN_DIR . 'assets/images/civilogo.svg.b64');

    /**
     * Filter the position of the CiviCRM menu item.
     *
     * Currently set to 3.9 + some random digits to reduce risk of conflict.
     *
     * @since 4.4
     *
     * @param str The default menu position expressed as a float.
     * @return str The modified menu position expressed as a float.
     */
    $position = apply_filters('civicrm_menu_item_position', '3.904981');

    // Check for settings file.
    if (CIVICRM_INSTALLED) {

      // Add top level menu item.
      $this->menu_page = add_menu_page(
        __('CiviCRM', 'civicrm'),
        __('CiviCRM', 'civicrm'),
        'access_civicrm',
        'CiviCRM',
        [$this->civi, 'invoke'],
        $civilogo,
        $position
      );

      // Add core resources prior to page load.
      add_action('load-' . $this->menu_page, [$this, 'admin_page_load']);

    }
    else {

      // Add top level menu item.
      $this->menu_page = add_menu_page(
        __('CiviCRM Installer', 'civicrm'),
        __('CiviCRM Installer', 'civicrm'),
        'manage_options',
        'civicrm-install',
        [$this, 'run_installer'],
        $civilogo,
        $position
      );

      /*
      // Add scripts and styles like this.
      add_action('admin_print_scripts-' . $this->menu_page, [$this, 'admin_installer_js']);
      add_action('admin_print_styles-' . $this->menu_page, [$this, 'admin_installer_css']);
      add_action('admin_head-' . $this->menu_page, [$this, 'admin_installer_head'], 50);
       */

    }

  }

  /**
   * Perform necessary stuff prior to CiviCRM's admin page being loaded.
   *
   * @since 4.6
   * @since 5.33 Moved to this class.
   */
  public function admin_page_load() {

    // This is required for AJAX calls in WordPress admin.
    $_REQUEST['noheader'] = $_GET['noheader'] = TRUE;

    // Add resources for back end.
    $this->civi->add_core_resources(FALSE);

    // Check setting for path to wp-load.php.
    $this->add_wpload_setting();

  }

  /**
   * When CiviCRM is loaded in WordPress Admin, check for the existence of a
   * setting which holds the path to wp-load.php. This is the only reliable way
   * to bootstrap WordPress from CiviCRM.
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
   * @since 5.33 Moved to this class.
   */
  public function add_wpload_setting() {

    if (!$this->civi->initialize()) {
      return;
    }

    // Get path to wp-load.php.
    $path = ABSPATH . 'wp-load.php';

    // Get the setting, if it exists.
    $setting = CRM_Core_BAO_Setting::getItem('CiviCRM Preferences', 'wpLoadPhp');

    // If we don't have one, create it.
    if (is_null($setting)) {
      CRM_Core_BAO_Setting::setItem($path, 'CiviCRM Preferences', 'wpLoadPhp');
    }

    // Is it different to the one we've stored?
    if ($setting !== $path) {
      // Yes - set new path (this could be because we've changed server or location)
      CRM_Core_BAO_Setting::setItem($path, 'CiviCRM Preferences', 'wpLoadPhp');
    }

  }

  /**
   * Get a CiviCRM admin link.
   *
   * @since 5.34
   *
   * @param str $path The CiviCRM path.
   * @param str $params The CiviCRM parameters.
   * @return string $link The URL of the CiviCRM page.
   */
  public function get_admin_link($path = '', $params = NULL) {

    // Init link.
    $link = '';

    if (!$this->civi->initialize()) {
      return $link;
    }

    // Use CiviCRM to construct link.
    $link = CRM_Utils_System::url(
      $path,
      $params,
      TRUE,
      NULL,
      TRUE,
      FALSE,
      TRUE
    );

    // --<
    return $link;

  }

  /**
   * Clear CiviCRM caches.
   *
   * Another way to do this might be:
   * CRM_Core_Invoke::rebuildMenuAndCaches(TRUE);
   *
   * @since 5.34
   */
  public function clear_caches() {

    if (!$this->civi->initialize()) {
      return;
    }

    // Access config object.
    $config = CRM_Core_Config::singleton();

    // Clear database cache.
    $config->clearDBCache();

    // Cleanup the "templates_c" directory.
    $config->cleanup(1, TRUE);

    // Cleanup the session object.
    $session = CRM_Core_Session::singleton();
    $session->reset(1);

    // Call system flush.
    CRM_Utils_System::flushCache();

  }

}
