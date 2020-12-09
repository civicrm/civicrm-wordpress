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
   * Instance constructor.
   *
   * @since 5.34
   */
  public function __construct() {

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
    add_action('add_meta_boxes', [$this, 'meta_boxes_integration_add']);

  }

  /**
   * Adds CiviCRM sub-menu items to WordPress admin menu.
   *
   * @since 5.34
   */
  public function add_menu_items() {

    // Bail if not fully installed.
    if (!CIVICRM_INSTALLED) {
      return;
    }

    // Add Integration submenu item.
    $integration_page = add_submenu_page(
      'CiviCRM',
      __('Integrating CiviCRM with WordPress', 'civicrm'),
      __('Integration', 'civicrm'),
      'access_civicrm',
      'civi_integration',
      [$this, 'page_integration']
    );

    // Add scripts for this page.
    add_action('admin_head-' . $integration_page, [$this, 'admin_head']);

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
     * @since 5.34
     *
     * @param str $screen_id The ID of the current screen.
     */
    do_action('add_meta_boxes', $screen->id, NULL);

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

    // Bail if user cannot access CiviCRM.
    if (!current_user_can('access_civicrm')) {
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

    // Create "CiviCRM Extensions" metabox.
    add_meta_box(
      'civicrm_repo_extensions',
      __('CiviCRM Extensions', 'civicrm'),
      [$this, 'meta_box_integration_ext_render'],
      $screen_id,
      'normal',
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
  public function meta_box_integration_wordpress_render($unused = NULL, $metabox) {

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
  public function meta_box_integration_git_render($unused = NULL, $metabox) {

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
  public function meta_box_integration_ext_render($unused = NULL, $metabox) {

    if (!$this->civi->initialize()) {
      return;
    }

    // Get Extensions page URL.
    $extensions_url = CRM_Utils_System::url('civicrm/admin/extensions', 'reset=1');

    // Include template file.
    include CIVICRM_PLUGIN_DIR . 'assets/templates/metaboxes/metabox.repo.ext.php';

  }

}
