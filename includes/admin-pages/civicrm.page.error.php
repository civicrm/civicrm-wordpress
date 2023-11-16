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
 * Define CiviCRM_For_WordPress_Admin_Page_Error Class.
 *
 * @since 5.40
 */
class CiviCRM_For_WordPress_Admin_Page_Error {

  /**
   * @var object
   * Plugin object reference.
   * @since 5.40
   * @access public
   */
  public $civi;

  /**
   * @var object
   * Admin object reference.
   * @since 5.40
   * @access public
   */
  public $admin;

  /**
   * Instance constructor.
   *
   * This class is constructed during the "admin_menu" action at priority 9.
   *
   * @since 5.40
   *
   * @param str $logo The CiviCRM logo.
   * @param str $position The default menu position expressed as a float.
   */
  public function __construct($logo, $position) {

    // Store reference to CiviCRM plugin object.
    $this->civi = civi_wp();

    // Store reference to admin object.
    $this->admin = civi_wp()->admin;

    // Add items to the CiviCRM admin menu.
    $this->add_menu_items($logo, $position);

    // Add our meta boxes.
    add_action('civicrm/page/error/add_meta_boxes', [$this, 'meta_boxes_error_add']);

  }

  /**
   * Get the capability required to access the Settings Page.
   *
   * @since 5.40
   */
  public function access_capability() {

    /**
     * Return default capability but allow overrides.
     *
     * @since 5.40
     *
     * @param str The default access capability.
     */
    return apply_filters('civicrm/admin/error/cap', 'manage_options');

  }

  /**
   * Adds CiviCRM sub-menu items to WordPress admin menu.
   *
   * @since 5.40
   *
   * @param str $logo The CiviCRM logo.
   * @param str $position The default menu position expressed as a float.
   */
  public function add_menu_items($logo, $position) {

    // Get access capability.
    $capability = $this->access_capability();

    // Add our top level menu item.
    $error_page = add_menu_page(
      __('Troubleshooting', 'civicrm'),
      __('CiviCRM', 'civicrm'),
      $capability,
      'CiviCRM',
      [$this, 'page_error'],
      $logo,
      $position
    );

    // Add scripts for this page.
    add_action('admin_head-' . $error_page, [$this, 'admin_head']);
    add_action('admin_print_styles-' . $error_page, [$this, 'admin_css']);

  }

  /**
   * Enqueue WordPress scripts on the pages that need them.
   *
   * @since 5.40
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
   * @since 5.40
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
   * Render the CiviCRM Error page.
   *
   * @since 5.40
   */
  public function page_error() {

    // Get the current screen object.
    $screen = get_current_screen();

    /**
     * Allow meta boxes to be added to this screen.
     *
     * The Screen ID to use is: "civicrm_page_civi_error".
     *
     * Used internally by:
     *
     * - self::meta_boxes_error_add()
     *
     * @since 5.40
     *
     * @param str $screen_id The ID of the current screen.
     */
    do_action('civicrm/page/error/add_meta_boxes', $screen->id);

    // Grab columns.
    $columns = (1 === $screen->get_columns() ? '1' : '2');

    // Include template file.
    include CIVICRM_PLUGIN_DIR . 'assets/templates/pages/page.error.php';

  }

  // ---------------------------------------------------------------------------
  // Meta Box Loaders
  // ---------------------------------------------------------------------------

  /**
   * Register Error Page meta boxes.
   *
   * @since 5.40
   *
   * @param str $screen_id The Admin Page Screen ID.
   */
  public function meta_boxes_error_add($screen_id) {

    // Define valid Screen IDs.
    $screen_ids = [
      'toplevel_page_CiviCRM',
    ];

    // Bail if not the Screen ID we want.
    if (!in_array($screen_id, $screen_ids)) {
      return;
    }

    // Bail if user cannot access the Error Page.
    $capability = $this->access_capability();
    if (!current_user_can($capability)) {
      return;
    }

    // Init data.
    $data = [];

    // Check for PHP version flag.
    if (civi_wp()->admin->error_flag === 'php-version') {

      // Create "PHP Error Information" metabox.
      add_meta_box(
        'civicrm_error_php',
        __('PHP Error Information', 'civicrm'),
        // Callback.
        [$this, 'meta_box_error_php_render'],
        // Screen ID.
        $screen_id,
        // Column: options are 'normal' and 'side'.
        'normal',
        // Vertical placement: options are 'core', 'high', 'low'.
        'core',
        $data
      );

    }
    else {

      // Create "Path Error Information" metabox.
      add_meta_box(
        'civicrm_error_path',
        __('Path Error Information', 'civicrm'),
        // Callback.
        [$this, 'meta_box_error_path_render'],
        // Screen ID.
        $screen_id,
        // Column: options are 'normal' and 'side'.
        'normal',
        // Vertical placement: options are 'core', 'high', 'low'.
        'core',
        $data
      );

    }

    // Create "General Information" metabox.
    add_meta_box(
      'civicrm_error_help',
      __('General Information', 'civicrm'),
      // Callback.
      [$this, 'meta_box_error_help_render'],
      // Screen ID.
      $screen_id,
      // Column: options are 'normal' and 'side'.
      'normal',
      // Vertical placement: options are 'core', 'high', 'low'.
      'core',
      $data
    );

  }

  // ---------------------------------------------------------------------------
  // Meta Box Renderers
  // ---------------------------------------------------------------------------

  /**
   * Render "General Information" meta box.
   *
   * @since 5.40
   *
   * @param mixed $unused Unused param.
   * @param array $metabox Array containing id, title, callback, and args elements.
   */
  public function meta_box_error_help_render($unused, $metabox) {

    // Include template file.
    include CIVICRM_PLUGIN_DIR . 'assets/templates/metaboxes/metabox.error.help.php';

  }

  /**
   * Render "PHP Error Information" meta box.
   *
   * @since 5.40
   *
   * @param mixed $unused Unused param.
   * @param array $metabox Array containing id, title, callback, and args elements.
   */
  public function meta_box_error_php_render($unused, $metabox) {

    global $civicrm_root;

    // Include template file.
    include CIVICRM_PLUGIN_DIR . 'assets/templates/metaboxes/metabox.error.php.php';

  }

  /**
   * Render "Path Error Information" meta box.
   *
   * @since 5.40
   *
   * @param mixed $unused Unused param.
   * @param array $metabox Array containing id, title, callback, and args elements.
   */
  public function meta_box_error_path_render($unused, $metabox) {

    global $civicrm_root;

    // Include template file.
    include CIVICRM_PLUGIN_DIR . 'assets/templates/metaboxes/metabox.error.path.php';

  }

}
