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
 * Define CiviCRM_For_WordPress_Admin_Page_Integration Class.
 *
 * @since 5.34
 */
class CiviCRM_For_WordPress_Admin_Page_Integration {

  /**
   * @var object
   * Plugin object reference.
   * @since 5.34
   * @access public
   */
  public $civi;

  /**
   * @var object
   * Admin object reference.
   * @since 5.34
   * @access public
   */
  public $admin;

  /**
   * @var string
   * CiviCRM Messages API Plugins route.
   * @since 5.34
   * @access public
   */
  public $plugins_route = 'https://alert.civicrm.org/plugins';

  /**
   * Instance constructor.
   *
   * @since 5.34
   */
  public function __construct() {

    // Disable until Messages API is active.
    return;

    // Bail if CiviCRM is not installed.
    if (!CIVICRM_INSTALLED) {
      return;
    }

    // Store reference to CiviCRM plugin object.
    $this->civi = civi_wp();

    // Store reference to admin object.
    $this->admin = civi_wp()->admin;

    // Wait for admin class to register hooks.
    add_action('civicrm/admin/hooks/registered', [$this, 'register_hooks']);

  }

  /**
   * Register hooks.
   *
   * @since 5.34
   */
  public function register_hooks() {

    // Add items to the CiviCRM admin menu.
    add_action('admin_menu', [$this, 'add_menu_items'], 9);

    // Add our meta boxes.
    add_action('civicrm/page/integration/add_meta_boxes', [$this, 'meta_boxes_integration_add']);

  }

  /**
   * Get the capability required to access the Settings Page.
   *
   * @since 5.37
   */
  public function access_capability() {

    /**
     * Return default capability but allow overrides.
     *
     * @since 5.37
     *
     * @param str The default access capability.
     */
    return apply_filters('civicrm/admin/integration/cap', 'manage_options');

  }

  /**
   * Adds CiviCRM sub-menu items to WordPress admin menu.
   *
   * @since 5.34
   */
  public function add_menu_items() {

    // Bail if not fully installed.
    if (!$this->civi->initialize()) {
      return;
    }

    // Get access capability.
    $capability = $this->access_capability();

    // Add Integration submenu item.
    $integration_page = add_submenu_page(
      'CiviCRM',
      __('Integrating CiviCRM with WordPress', 'civicrm'),
      __('Integration', 'civicrm'),
      $capability,
      'civi_integration',
      [$this, 'page_integration']
    );

    // Add scripts for this page.
    add_action('admin_head-' . $integration_page, [$this, 'admin_head']);
    add_action('admin_print_styles-' . $integration_page, [$this, 'admin_css']);

  }

  /**
   * Enqueue WordPress scripts on the pages that need them.
   *
   * @since 5.34
   */
  public function admin_head() {

    // Enqueue WordPress scripts.
    wp_enqueue_script('common');
    wp_enqueue_script('jquery-ui-sortable');
    wp_enqueue_script('dashboard');

  }

  /**
   * Enqueue stylesheet on this page.
   *
   * @since 5.34
   */
  public function admin_css() {

    // Enqueue common CSS.
    wp_enqueue_style(
      'civicrm-admin-styles',
      CIVICRM_PLUGIN_URL . 'assets/css/civicrm.admin.css',
      NULL,
      CIVICRM_PLUGIN_VERSION,
      'all'
    );

  }

  // ---------------------------------------------------------------------------
  // Page Loader
  // ---------------------------------------------------------------------------

  /**
   * Render the CiviCRM Integration page.
   *
   * @since 5.34
   */
  public function page_integration() {

    // Get the current screen object.
    $screen = get_current_screen();

    /**
     * Allow meta boxes to be added to this screen.
     *
     * The Screen ID to use is: "civicrm_page_cwps_settings".
     *
     * Used internally by:
     *
     * - self::meta_boxes_integration_add()
     *
     * @since 5.34
     *
     * @param str $screen_id The ID of the current screen.
     */
    do_action('civicrm/page/integration/add_meta_boxes', $screen->id);

    // Get the column CSS class.
    $columns = absint($screen->get_columns());
    $columns_css = '';
    if ($columns) {
      $columns_css = " columns-$columns";
    }

    // Include template file.
    include CIVICRM_PLUGIN_DIR . 'assets/templates/pages/page.integration.php';

  }

  // ---------------------------------------------------------------------------
  // Meta Box Loaders
  // ---------------------------------------------------------------------------

  /**
   * Register Integration Page meta boxes.
   *
   * @since 5.34
   *
   * @param str $screen_id The Admin Page Screen ID.
   */
  public function meta_boxes_integration_add($screen_id) {

    // Define valid Screen IDs.
    $screen_ids = [
      'civicrm_page_civi_integration',
    ];

    // Bail if not the Screen ID we want.
    if (!in_array($screen_id, $screen_ids)) {
      return;
    }

    // Bail if user cannot access the Integration Page.
    $capability = $this->access_capability();
    if (!current_user_can($capability)) {
      return;
    }

    // Init data.
    $data = [];

    // Create "WordPress Plugin Directory" metabox.
    add_meta_box(
      'civicrm_repo_wordpress',
      __('WordPress Plugin Directory', 'civicrm'),
      // Callback.
      [$this, 'meta_box_integration_wordpress_render'],
      // Screen ID.
      $screen_id,
      // Column: options are 'normal' and 'side'.
      'normal',
      // Vertical placement: options are 'core', 'high', 'low'.
      'core',
      $data
    );

    // Create "Other Plugin Repositories" metabox.
    add_meta_box(
      'civicrm_repo_other',
      __('Other Plugin Repositories', 'civicrm'),
      [$this, 'meta_box_integration_git_render'],
      $screen_id,
      'side',
      'core',
      $data
    );

    // Create "CiviCRM Extensions" metabox.
    add_meta_box(
      'civicrm_repo_extensions',
      __('CiviCRM Extensions', 'civicrm'),
      [$this, 'meta_box_integration_ext_render'],
      $screen_id,
      'side',
      'core',
      $data
    );

  }

  // ---------------------------------------------------------------------------
  // Meta Box Renderers
  // ---------------------------------------------------------------------------

  /**
   * Render "WordPress Plugin Directory" meta box.
   *
   * @since 5.34
   *
   * @param mixed $unused Unused param.
   * @param array $metabox Array containing id, title, callback, and args elements.
   */
  public function meta_box_integration_wordpress_render($unused, $metabox) {

    // First check our transient for the data.
    $plugins = get_site_transient('civicrm_plugins_by_tag');

    // Query again if it's not found.
    if ($plugins === FALSE) {

      // Build query.
      $query = [
        'tag' => 'civicrm',
        'fields' => [
          'tested' => TRUE,
          'last_updated' => TRUE,
          'active_installs' => TRUE,
          'short_description' => TRUE,
        ],
      ];

      // Query the WordPress Plugin Directory.
      require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
      $response = plugins_api('query_plugins', $query);

      // Parse the response if we get one.
      $plugins = [];
      if (is_object($response) && !is_wp_error($response)) {

        // We're good - overwrite.
        $plugins = $response;

        // Store for a week given how infrequently plugins are added.
        set_site_transient('civicrm_plugins_by_tag', $plugins, 1 * WEEK_IN_SECONDS);

      }

    }

    // Include template file.
    include CIVICRM_PLUGIN_DIR . 'assets/templates/metaboxes/metabox.repo.wordpress.php';

  }

  /**
   * Render "Other Plugin Repositories" meta box.
   *
   * @since 5.34
   *
   * @param mixed $unused Unused param.
   * @param array $metabox Array containing id, title, callback, and args elements.
   */
  public function meta_box_integration_git_render($unused, $metabox) {

    // First check our transient for the data.
    $plugins = get_site_transient('civicrm_plugins_by_repo');

    // TODO: Delete this when the route is set up.
    $plugins = [];

    // Query again if it's not found.
    if ($plugins === FALSE) {
      if ($this->civi->initialize()) {

        // Get parsed URL.
        $this->plugins_route .= '?prot=1&ver={ver}&uf={uf}&sid={sid}&lang={lang}&co={co}';
        $url = CRM_Utils_System::evalUrl($this->plugins_route);

        // Hit the API.
        $response = wp_remote_get(esc_url_raw($url));

        // Parse the response if we get one.
        $plugins = [];
        if (is_array($response) && !is_wp_error($response)) {

          // We're good - grab the actual data.
          $plugins = json_decode(wp_remote_retrieve_body($response), TRUE);

          // Store for a week given how infrequently plugins are added.
          set_site_transient('civicrm_plugins_by_repo', $plugins, 1 * WEEK_IN_SECONDS);

        }

      }

    }

    // Include template file.
    include CIVICRM_PLUGIN_DIR . 'assets/templates/metaboxes/metabox.repo.git.php';

  }

  /**
   * Render "CiviCRM Extensions" meta box.
   *
   * @since 5.34
   *
   * @param mixed $unused Unused param.
   * @param array $metabox Array containing id, title, callback, and args elements.
   */
  public function meta_box_integration_ext_render($unused, $metabox) {

    if (!$this->civi->initialize()) {
      return;
    }

    // Get Extensions page URL.
    $extensions_url = CRM_Utils_System::url('civicrm/admin/extensions', 'reset=1');

    // Include template file.
    include CIVICRM_PLUGIN_DIR . 'assets/templates/metaboxes/metabox.repo.ext.php';

  }

}
