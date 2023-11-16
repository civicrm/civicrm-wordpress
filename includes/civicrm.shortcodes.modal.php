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
 * Define CiviCRM_For_WordPress_Shortcodes_Modal Class.
 *
 * @since 4.6
 */
class CiviCRM_For_WordPress_Shortcodes_Modal {

  /**
   * @var object
   * Plugin object reference.
   * @since 4.6
   * @access public
   */
  public $civi;

  /**
   * Instance constructor.
   *
   * @since 4.6
   */
  public function __construct() {

    // Store reference to CiviCRM plugin object.
    $this->civi = civi_wp();

  }

  /**
   * Register hooks to handle the shortcode modal.
   *
   * @since 4.6
   */
  public function register_hooks() {

    // Bail if CiviCRM not installed yet.
    if (!CIVICRM_INSTALLED) {
      return;
    }

    /*
     * Adds the CiviCRM button to post and page edit screens.
     * Use priority 100 to position button to the far right.
     */
    add_action('media_buttons', [$this, 'add_form_button'], 100);

    // Add the Javascript and styles to make it all happen.
    add_action('load-post.php', [$this, 'add_core_resources']);
    add_action('load-post-new.php', [$this, 'add_core_resources']);
    add_action('load-page.php', [$this, 'add_core_resources']);
    add_action('load-page-new.php', [$this, 'add_core_resources']);

  }

  /**
   * Add button to editor for selected WordPress Post Types.
   *
   * Callback method for 'media_buttons' hook as set in register_hooks().
   *
   * @since 4.7
   */
  public function add_form_button() {

    // Add button to selected WordPress Post Types, if allowed.
    if ($this->post_type_has_button()) {

      // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
      $civilogo = file_get_contents(CIVICRM_PLUGIN_DIR . 'assets/images/civilogo.svg.b64');

      $url = admin_url('admin.php?page=CiviCRM&q=civicrm/shortcode&reset=1');
      echo '<a href= "' . $url . '" class="button crm-shortcode-button" style="padding-left: 4px;" title="' . __('Add CiviCRM Public Pages', 'civicrm') . '"><img src="' . $civilogo . '" height="15" width="15" alt="' . __('Add CiviCRM Public Pages', 'civicrm') . '" style="margin: -3px 1px 0 -2px;" />' . __('CiviCRM', 'civicrm') . '</a>';

    }

  }

  /**
   * Add core resources.
   *
   * Callback method as set in register_hooks().
   *
   * @since 4.7
   */
  public function add_core_resources() {
    if ($this->civi->initialize()) {
      Civi::resources()
        ->addCoreResources()
        ->addScriptFile('civicrm', 'js/crm.insert-shortcode.js', 0, 'html-header');
    }
  }

  /**
   * Does a WordPress post type have the CiviCRM button on it?
   *
   * @since 4.6
   *
   * @return bool $has_button True if the post type has the button, false otherwise.
   */
  public function post_type_has_button() {

    // Get screen object.
    $screen = get_current_screen();

    // Bail if no post type - e.g. Ninja Forms.
    if (!isset($screen->post_type)) {
      return;
    }

    // Get post types that support the editor.
    $capable_post_types = $this->get_post_types_with_editor();

    // Default allowed to true on all capable post types.
    $allowed = (in_array($screen->post_type, $capable_post_types)) ? TRUE : FALSE;

    /**
     * Filter the appearance of the CiviCRM button.
     *
     * @since 4.6
     *
     * @param bool $allowed True if the button is allowed, false otherwise.
     * @param object $screen The current WordPress screen object.
     */
    $allowed = apply_filters('civicrm_restrict_button_appearance', $allowed, $screen);

    return $allowed;

  }

  /**
   * Get WordPress post types that support the editor.
   *
   * @since 4.6
   *
   * @return array $supported_post_types Array of post types that have an editor.
   */
  public function get_post_types_with_editor() {

    static $supported_post_types = [];
    if (!empty($supported_post_types)) {
      return $supported_post_types;
    }

    // Get only post types with an admin UI.
    $args = [
      'public' => TRUE,
      'show_ui' => TRUE,
    ];

    // Get post types.
    $post_types = get_post_types($args);

    foreach ($post_types as $post_type) {
      // Filter only those which have an editor.
      if (post_type_supports($post_type, 'editor')) {
        $supported_post_types[] = $post_type;
      }
    }

    return $supported_post_types;
  }

}
