<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 5                                                  |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2019                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2019
 *
 */


// This file must not accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * Define CiviCRM_For_WordPress_Shortcodes_Modal Class.
 *
 * @since 4.6
 */
class CiviCRM_For_WordPress_Shortcodes_Modal {

  /**
   * Plugin object reference.
   *
   * @since 4.6
   * @access public
   * @var object $civi The plugin object reference.
   */
  public $civi;


  /**
   * Instance constructor.
   *
   * @since 4.6
   */
  function __construct() {

    // Store reference to CiviCRM plugin object
    $this->civi = civi_wp();

  }


  /**
   * Register hooks to handle the shortcode modal.
   *
   * @since 4.6
   */
  public function register_hooks() {

    // Bail if CiviCRM not installed yet
    if ( ! CIVICRM_INSTALLED ) return;

    // Adds the CiviCRM button to post and page edit screens
    // Use priority 100 to position button to the farright
    add_action( 'media_buttons', array( $this, 'add_form_button' ), 100 );


    // Add the javascript and styles to make it all happen
    add_action('load-post.php', array($this, 'add_core_resources'));
    add_action('load-post-new.php', array($this, 'add_core_resources'));
    add_action('load-page.php', array($this, 'add_core_resources'));
    add_action('load-page-new.php', array($this, 'add_core_resources'));

  }


  /**
   * Add button to editor for WP selected post types.
   *
   * Callback method for 'media_buttons' hook as set in register_hooks().
   *
   * @since 4.7
   */
  public function add_form_button() {

    // Add button to WP selected post types, if allowed
    if ( $this->post_type_has_button() ) {

      $civilogo = file_get_contents( plugin_dir_path( __FILE__ ) . '../assets/civilogo.svg.b64' );

      $url = admin_url( 'admin.php?page=CiviCRM&q=civicrm/shortcode&reset=1' );
      echo '<a href= "' . $url . '" class="button crm-popup medium-popup crm-shortcode-button" data-popup-type="page" style="padding-left: 4px;" title="' . __( 'Add CiviCRM Public Pages', 'civicrm' ) . '"><img src="' . $civilogo . '" height="15" width="15" alt="' . __( 'Add CiviCRM Public Pages', 'civicrm' ) . '" />'. __( 'CiviCRM', 'civicrm' ) .'</a>';

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
      CRM_Core_Resources::singleton()->addCoreResources();
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

    // Get screen object
    $screen = get_current_screen();

    // Bail if no post type (e.g. Ninja Forms)
    if ( ! isset( $screen->post_type ) ) return;

    // Get post types that support the editor
    $capable_post_types = $this->get_post_types_with_editor();

    // Default allowed to true on all capable post types
    $allowed = ( in_array( $screen->post_type, $capable_post_types ) ) ? true : false;

    /**
     * Filter the appearance of the CiviCRM button.
     *
     * @since 4.6
     *
     * @param bool $allowed True if the button is allowed, false otherwise.
     * @param object $screen The current WordPress screen object.
     * @return bool $allowed True if the button is allowed, false otherwise.
     */
    $allowed = apply_filters( 'civicrm_restrict_button_appearance', $allowed, $screen );

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

    static $supported_post_types = array();
    if ( !empty( $supported_post_types) ) {
      return $supported_post_types;
    }

    // Get only post types with an admin UI
    $args = array(
      'public' => true,
      'show_ui' => true,
    );

    // Get post types
    $post_types = get_post_types($args);

    foreach ($post_types AS $post_type) {
      // Filter only those which have an editor
      if (post_type_supports($post_type, 'editor')) {
        $supported_post_types[] = $post_type;
      }
    }

    return $supported_post_types;
  }

} // Class CiviCRM_For_WordPress_Shortcodes_Modal ends
