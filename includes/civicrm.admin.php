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
   * Error Information page object.
   * @since 5.40
   * @access public
   */
  public $page_error;

  /**
   * @var string
   * Error handling flag to determine whether to show a troubleshooting page.
   * @since 5.40
   * @access public
   */
  public $error_flag = '';

  /**
   * @var object
   * Quick Add meta box object.
   * @since 5.34
   * @access public
   */
  public $metabox_quick_add;

  /**
   * @var string
   * Reference to the CiviCRM menu item's hook_suffix, in the WordPress admin menu.
   * @access public
   */
  public $menu_page;

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

    // Always check setting for path to "wp-load.php".
    add_action('civicrm_initialized', [$this, 'add_wpload_setting']);

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

    // Prevent auto-updates.
    add_filter('plugin_auto_update_setting_html', [$this, 'auto_update_prevent'], 10, 3);

    // Modify the admin menu.
    add_action('admin_menu', [$this, 'add_menu_items'], 9);

    // Add CiviCRM's resources in the admin header.
    add_action('admin_head', [$this->civi, 'wp_head'], 50);

    // If settings file does not exist.
    if (!CIVICRM_INSTALLED) {

      // Maybe show notice with link to installer.
      add_action('admin_notices', [$this, 'show_setup_warning']);

    }
    else {

      // Listen for changes to the Base Page setting.
      add_action('civicrm_postSave_civicrm_setting', [$this, 'settings_change'], 10);

      // Set page title.
      add_filter('admin_title', [$this, 'set_admin_title']);

    }

    /**
     * Broadcast that this object has registered its callbacks.
     *
     * Used internally by:
     *
     * - CiviCRM_For_WordPress_Admin_Metabox_Contact_Add::register_hooks()
     * - CiviCRM_For_WordPress_Admin_Page_Integration::register_hooks()
     * - CiviCRM_For_WordPress_Admin_Page_Options::register_hooks()
     *
     * @since 5.34
     */
    do_action('civicrm/admin/hooks/registered');

  }

  // ---------------------------------------------------------------------------
  // Installation
  // ---------------------------------------------------------------------------

  /**
   * Show an admin notice on pages other than the CiviCRM Installer.
   *
   * @since 4.4
   * @since 5.33 Moved to this class.
   */
  public function show_setup_warning() {

    // Check user permissions.
    if (!current_user_can('manage_options')) {
      return;
    }

    // Get current screen.
    $screen = get_current_screen();

    // Bail if it's not what we expect.
    if (!($screen instanceof WP_Screen)) {
      return;
    }

    // Bail if we are on our installer page.
    if ($screen->id === 'toplevel_page_civicrm-install') {
      return;
    }

    $message = sprintf(
      /* translators: 1: Opening strong tag, 2: Closing strong tag, 3: Opening anchor tag, 4: Closing anchor tag. */
      __('%1$sCiviCRM is almost ready.%2$s You must %3$sconfigure CiviCRM%4$s for it to work.', 'civicrm'),
      '<strong>',
      '</strong>',
      '<a href="' . menu_page_url('civicrm-install', FALSE) . '">',
      '</a>'
    );

    echo '<div id="message" class="notice notice-warning">';
    echo '<p>' . $message . '</p>';
    echo '</div>';

  }

  /**
   * Callback method for add_options_page() that runs the CiviCRM installer.
   *
   * @since 4.4
   */
  public function run_installer() {

    // Set install type.
    // phpcs:ignore WordPress.WP.CapitalPDangit.Misspelled
    $_GET['civicrm_install_type'] = 'wordpress';

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
          'ctrl' => menu_page_url('civicrm-install', FALSE),
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

  // ---------------------------------------------------------------------------
  // Pre-flight check
  // ---------------------------------------------------------------------------

  /**
   * Show an admin notice when the PHP version isn't sufficient.
   *
   * @since 5.40
   */
  public function show_php_warning() {

    // Check user permissions.
    if (!current_user_can('manage_options')) {
      return;
    }

    // Get current screen.
    $screen = get_current_screen();

    // Bail if it's not what we expect.
    if (!($screen instanceof WP_Screen)) {
      return;
    }

    // Bail if we are on our error page.
    if ($screen->id === 'toplevel_page_CiviCRM') {
      return;
    }

    $message = sprintf(
      /* translators: 1: Opening strong tag, 2: Closing strong tag, 3: Opening anchor tag, 4: Closing anchor tag. */
      __('%1$sCiviCRM needs your attention.%2$s Please visit the %3$sInformation Page%4$s for details.', 'civicrm'),
      '<strong>',
      '</strong>',
      '<a href="' . menu_page_url('CiviCRM', FALSE) . '">',
      '</a>'
    );

    echo '<div id="message" class="notice notice-warning">';
    echo '<p>' . $message . '</p>';
    echo '</div>';

  }

  /**
   * Check that the PHP version is supported.
   *
   * If not, show an admin notice and enable the Error Page instead of CiviCRM's
   * admin UI. This way WordPress is still usable while the issue is sorted out.
   *
   * This check is not necessary for fresh installs because we now have the
   * "Requires PHP:" plugin header. It is, however, necessary for upgrades - but
   * shouldn't render WordPress unusable.
   *
   * @since 5.18
   * @since 5.33 Moved to this class.
   *
   * @return bool True if the PHP version is supported, false otherwise.
   */
  protected function assert_php_support() {

    if (version_compare(PHP_VERSION, CIVICRM_WP_PHP_MINIMUM) < 0) {
      add_action('admin_notices', [$this, 'show_php_warning']);
      $this->error_flag = 'php-version';
      return FALSE;
    }

    return TRUE;

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

    static $initialized = NULL;

    if (!is_null($initialized)) {
      return $initialized;
    }

    /*
     * CiviCRM must not be initialized if it's not installed. It's okay to
     * return early because the admin notice will be displayed if, for some
     * reason, the initial redirect on install hasn't occured.
     */
    if (!CIVICRM_INSTALLED) {
      $this->error_flag = 'settings-missing';
      $initialized = FALSE;
      return FALSE;
    }

    // Check PHP version in case of upgrade.
    if (!$this->assert_php_support()) {
      $initialized = FALSE;
      return FALSE;
    }

    /*
     * Checks from this point on are for cases where the install has become
     * corrupted in some way. We are trying to fail as gracefully as we can.
     * Since CIVICRM_INSTALLED is set based on the presence of the settings
     * file, we now know it is there.
     */

    // Include settings file - returns int(1) on success.
    $error = include_once CIVICRM_SETTINGS_PATH;

    /*
     * Bail if the settings file returns something other than int(1).
     * When this happens, we should show an admin page with troubleshooting
     * instructions rather than dying and leaving WordPress unusable.
     *
     * Requires a page similar to "civicrm.page.options.php", which we can
     * show instead of "invoking" CiviCRM itself. In order to do that, this
     * method *must* have been called before "add_menu_items()" so that an
     * alternative "add_menu_page()" call can be made. Usefully, this already
     * happens because "register_hooks_clean_urls()" is called first.
     *
     * However, it looks like there may be little that can be done to mitigate
     * path errors - e.g. when $civicrm_root is not set correctly - because
     * including "civicrm.settings.php" will throw a fatal error if $civicrm_root
     * is wrong.
     */
    if ($error === FALSE) {
      $this->error_flag = 'settings-include';
      $initialized = FALSE;
      return FALSE;
    }

    // Initialize the Class Loader.
    require_once CIVICRM_PLUGIN_DIR . 'civicrm/CRM/Core/ClassLoader.php';
    CRM_Core_ClassLoader::singleton()->register();

    // Access global defined in "civicrm.settings.php".
    global $civicrm_root;

    // Bail if the config file isn't found.
    if (!file_exists($civicrm_root . 'CRM/Core/Config.php')) {
      $this->error_flag = 'config-missing';
      $initialized = FALSE;
      return FALSE;
    }

    // Include config file - returns int(1) on success.
    $error = include_once 'CRM/Core/Config.php';

    // Bail if the config file returns something other than int(1).
    if ($error === FALSE) {
      $this->error_flag = 'config-include';
      $initialized = FALSE;
      return FALSE;
    }

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

    // Success! Set static flag.
    $initialized = TRUE;

    /**
     * Broadcast that CiviCRM is now initialized.
     *
     * @since 4.4
     */
    do_action('civicrm_initialized');

    return $initialized;

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
    if ($pagenow !== 'admin.php' || FALSE === strpos($current_screen['query'], 'page=CiviCRM')) {
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
      if (isset($dao->name) && $dao->name === 'wpBasePage') {
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
   *
   * @param string $html The auto-update markup.
   * @param string $plugin_file The path to the plugin.
   * @param array $plugin_data An array of plugin data.
   * @return string $html The modified auto-update markup.
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

    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
    $civilogo = file_get_contents(CIVICRM_PLUGIN_DIR . 'assets/images/civilogo.svg.b64');

    global $wp_version;
    if (version_compare($wp_version, '5.9.9999', '>')) {
      $menu_position = 3;
    }
    else {
      $menu_position = '3.904981';
    }

    /**
     * Filter the position of the CiviCRM menu item.
     *
     * As per the code above, the position was previously set to '3.904981' to
     * reduce risk of conflicts. The position is now conditionally set depending
     * on the version of WordPress.
     *
     * @since 4.4
     * @since 5.47 Conditionally set because WordPress 6.0 enforces integers.
     *
     * @param str|int $menu_position The default menu position.
     */
    $position = apply_filters('civicrm_menu_item_position', $menu_position);

    // Try and initialize CiviCRM.
    $success = $this->initialize();

    // If all went well.
    if ($success) {

      // Add the CiviCRM top level menu item.
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

      /*
       * Are we here because this is a fresh install or because something is broken?
       *
       * Let's inspect the "error_flag" property for help with the decision. Where
       * the settings file is missing, there's not a lot we can do, so assume it's
       * a fresh install.
       *
       * However, we may be able to detect signs of installs where CiviCRM has been
       * installed but "civicrm.settings.php" can't be found.
       *
       * @see self::detect_existing_install()
       */
      if ($this->error_flag === 'settings-missing') {

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
         * Add scripts and styles like this if needed:
         *
         * add_action('admin_print_scripts-' . $this->menu_page, [$this, 'admin_installer_js']);
         * add_action('admin_print_styles-' . $this->menu_page, [$this, 'admin_installer_css']);
         * add_action('admin_head-' . $this->menu_page, [$this, 'admin_installer_head'], 50);
         */

      }
      else {

        // Hand over to our Error Page to provide feedback.
        $this->page_error_init($civilogo, $position);

      }

    }

  }

  /**
   * Try and detect signs of the existence of CiviCRM.
   *
   * It's possible that CiviCRM has been installed but that something is broken
   * with the current install. This method looks for tell-tale signs.
   *
   * This is not used as yet, but is included as-is to be completed later.
   *
   * @since 5.40
   *
   * @return bool $existing_install True if there are signs of an existing install.
   */
  public function detect_existing_install() {

    // Assume there's no evidence.
    $existing_install = FALSE;

    // This option is created on activation.
    $existing_option = FALSE;
    if ('fjwlws' !== get_option('civicrm_activation_in_progress', 'fjwlws')) {
      $existing_option = TRUE;
    }

    // Look for a directory in the standard location.
    $existing_uploads_dir = FALSE;
    $upload_dir = wp_upload_dir();
    if (is_dir($upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'civicrm')) {
      $existing_uploads_dir = TRUE;
    }

    // Look for a file in the legacy location.
    $existing_legacy_file = FALSE;
    if (file_exists(CIVICRM_PLUGIN_DIR . 'civicrm.settings.php')) {
      $existing_legacy_file = TRUE;
    }

    return $existing_install;

  }

  /**
   * Show Error Information page.
   *
   * In situations where something has gone wrong with the CiviCRM installation,
   * show a page which will help people troubleshoot the problem.
   *
   * @since 5.40
   *
   * @param str $logo The CiviCRM logo.
   * @param str $position The default menu position expressed as a float.
   */
  public function page_error_init($logo, $position) {

    // Include and init Error Page.
    include_once CIVICRM_PLUGIN_DIR . 'includes/admin-pages/civicrm.page.error.php';
    $this->page_error = new CiviCRM_For_WordPress_Admin_Page_Error($logo, $position);

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
   * To get the path to wp-load.php, use:
   * $path = Civi::settings()->get('wpLoadPhp');
   *
   * @since 4.6.3
   * @since 5.33 Moved to this class.
   */
  public function add_wpload_setting() {

    if (!CIVICRM_INSTALLED) {
      return;
    }

    if (!$this->initialize()) {
      return;
    }

    if (version_compare(CRM_Core_BAO_Domain::getDomain()->version, '4.7.0', '<')) {
      return;
    }

    // Get path to wp-load.php.
    $path = ABSPATH . 'wp-load.php';

    // Get the setting, if it exists.
    $setting = Civi::settings()->get('wpLoadPhp');

    /*
     * If we don't have a setting, create it. Also set it if it's different to
     * what's stored. This could be because we've changed server or location.
     */
    if (empty($setting) || $setting !== $path) {
      Civi::settings()->set('wpLoadPhp', $path);
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

    if (!$this->initialize()) {
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

    if (!$this->initialize()) {
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

  /**
   * Gets a suggested CiviCRM Contact ID via the "Unsupervised" Dedupe Rule.
   *
   * @since 5.43
   *
   * @param array $contact The array of CiviCRM Contact data.
   * @param string $contact_type The Contact Type.
   * @return integer|bool $contact_id The suggested Contact ID, or false on failure.
   */
  public function get_by_dedupe_unsupervised($contact, $contact_type = 'Individual') {

    if (empty($contact)) {
      return FALSE;
    }

    if (!$this->initialize()) {
      return FALSE;
    }

    // Get the Dedupe params.
    $dedupe_params = CRM_Dedupe_Finder::formatParams($contact, $contact_type);
    $dedupe_params['check_permission'] = FALSE;

    // Use Dedupe Rules to find possible Contact IDs.
    $contact_ids = CRM_Dedupe_Finder::dupesByParams($dedupe_params, $contact_type, 'Unsupervised');

    // Return the suggested Contact ID if present.
    $contact_id = 0;
    if (!empty($contact_ids)) {
      $contact_ids = array_reverse($contact_ids);
      $contact_id = (int) array_pop($contact_ids);
    }

    // --<
    return $contact_id;

  }

  /**
   * Gets the CiviCRM Shortcode Mode.
   *
   * Defaults to "legacy" to preserve existing behaviour.
   *
   * @since 5.44
   *
   * @return string $shortcode_mode The Shortcode Mode: either 'legacy' or 'modern'.
   */
  public function get_shortcode_mode() {
    return get_option('civicrm_shortcode_mode', 'legacy');
  }

  /**
   * Sets the CiviCRM Shortcode Mode.
   *
   * @since 5.44
   *
   * @param string $shortcode_mode The Shortcode Mode: either 'legacy' or 'modern'.
   */
  public function set_shortcode_mode($shortcode_mode) {
    update_option('civicrm_shortcode_mode', $shortcode_mode);
  }

  /**
   * Gets the array of CiviCRM Shortcode Modes.
   *
   * @since 5.44
   *
   * @return array $shortcode_modes The array of Shortcode Modes.
   */
  public function get_shortcode_modes() {
    return ['legacy', 'modern'];
  }

}
