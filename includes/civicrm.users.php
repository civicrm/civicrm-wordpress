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
 * Define CiviCRM_For_WordPress_Users Class.
 *
 * @since 4.6
 */
class CiviCRM_For_WordPress_Users {

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

    // Always listen for activation action
    add_action( 'civicrm_activation', array( $this, 'activate' ) );

  }


  /**
   * Plugin activation tasks.
   *
   * @since 5.6
   */
  public function activate() {

    // Assign minimum capabilities for all WP roles and create 'anonymous_user' role
    $this->set_wp_user_capabilities();

  }


  /**
   * Register hooks.
   *
   * @since 4.6
   */
  public function register_hooks() {

    // Add CiviCRM access capabilities to WordPress roles
    $this->set_access_capabilities();

    // Do not hook into user updates if CiviCRM not installed yet
    if ( ! CIVICRM_INSTALLED ) return;

    // Synchronise users on insert and update
    add_action( 'user_register', array( $this, 'update_user' ) );
    add_action( 'profile_update', array( $this, 'update_user' ) );

    // Delete ufMatch record when a WordPress user is deleted
    add_action( 'deleted_user', array( $this, 'delete_user_ufmatch' ), 10, 1 );

  }


  /**
   * Check permissions.
   *
   * Authentication function used by basepage_register_hooks()
   *
   * @since 4.6
   *
   * @param array $args The page arguments array.
   * @return bool True if authenticated, false otherwise.
   */
  public function check_permission( $args ) {

    if ( $args[0] != 'civicrm' ) {
      return FALSE;
    }

    $config = CRM_Core_Config::singleton();

    // Set frontend true
    $config->userFrameworkFrontend = TRUE;

    require_once 'CRM/Utils/Array.php';

    // All profile and file urls, as well as user dashboard and tell-a-friend are valid
    $arg1 = CRM_Utils_Array::value(1, $args);
    $invalidPaths = array('admin');
    if ( in_array( $arg1, $invalidPaths ) ) {
      return FALSE;
    }

    return TRUE;

  }


  /**
   * Get "permission denied" text.
   *
   * Called when authentication fails in basepage_register_hooks()
   *
   * @since 4.6
   *
   * @return string Warning message.
   */
  public function get_permission_denied() {
    return __( 'You do not have permission to access this content.', 'civicrm' );
  }


  /**
   * Handle WordPress user events.
   *
   * Callback function for 'user_register' hook
   * Callback function for 'profile_update' hook
   *
   * CMW: seems to (wrongly) create new CiviCRM Contact every time a user changes their
   * first_name or last_name attributes in WordPress.
   *
   * @since 4.6
   *
   * @param int $user_id The numeric ID of the WordPress user
   */
  public function update_user( $user_id ) {

    $user = get_userdata( $user_id );
    if ( $user ) {
      $this->sync_user( $user );
    }

  }


  /**
   * Keep WordPress user synced with CiviCRM Contact.
   *
   * @since 4.6
   *
   * @param object $user The WordPress user object.
   */
  public function sync_user( $user = FALSE ) {

    // Sanity check
    if ( $user === FALSE OR !is_a($user, 'WP_User') ) {
      return;
    }

    if (!$this->civi->initialize()) {
      return;
    }

    require_once 'CRM/Core/BAO/UFMatch.php';

    /*
     * This does not return anything, so if we want to do anything further
     * to the CiviCRM Contact, we have to search for it all over again.
     */
    CRM_Core_BAO_UFMatch::synchronize(
      $user, // User object
      TRUE, // Update = true
      'WordPress', // CMS
      'Individual' // contact type
    );

    /*
    // IN PROGRESS: synchronizeUFMatch does return the contact object, however
    $civi_contact = CRM_Core_BAO_UFMatch::synchronizeUFMatch(
      $user, // User object
      $user->ID, // ID
      $user->user_mail, // Unique identifier
      null // Unused
      'WordPress' // CMS
      'Individual' // contact type
    );

    // Now we can allow other plugins to do their thing
    do_action( 'civicrm_contact_synced', $user, $civi_contact );
    */

  }


  /**
   * When a WordPress user is deleted, delete the ufMatch record.
   *
   * Callback function for 'delete_user' hook
   *
   * @since 4.6
   *
   * @param $user_id The numerical ID of the WordPress user.
   */
  public function delete_user_ufmatch( $user_id ) {

    if (!$this->civi->initialize()) {
      return;
    }

    // Delete the ufMatch record
    require_once 'CRM/Core/BAO/UFMatch.php';
    CRM_Core_BAO_UFMatch::deleteUser($user_id);

  }


  /**
   * Create anonymous role and define capabilities.
   *
   * Function to create 'anonymous_user' role, if 'anonymous_user' role is not
   * in the WordPress installation and assign minimum capabilities for all
   * WordPress roles.
   *
   * The legacy global scope function civicrm_wp_set_capabilities() is called
   * from upgrade_4_3_alpha1()
   *
   * @since 4.6
   */
  public function set_wp_user_capabilities() {

    global $wp_roles;
    if ( ! isset( $wp_roles ) ) {
      $wp_roles = new WP_Roles();
    }

    // Minimum capabilities (Civicrm permissions) arrays
    $default_min_capabilities =  array(
      'access_civimail_subscribe_unsubscribe_pages' => 1,
      'access_all_custom_data' => 1,
      'access_uploaded_files' => 1,
      'make_online_contributions' => 1,
      'profile_create' => 1,
      'profile_edit' => 1,
      'profile_view' => 1,
      'register_for_events' => 1,
      'view_event_info' => 1,
      'sign_civicrm_petition' => 1,
      'view_public_civimail_content' => 1,
    );

    /**
     * Allow minimum capabilities to be filtered.
     *
     * @since 4.6
     *
     * @param array $default_min_capabilities The minimum capabilities.
     * @return array $default_min_capabilities The modified capabilities.
     */
    $min_capabilities = apply_filters( 'civicrm_min_capabilities', $default_min_capabilities );

    // Assign the Minimum capabilities (Civicrm permissions) to all WP roles
    foreach ( $wp_roles->role_names as $role => $name ) {
      $roleObj = $wp_roles->get_role( $role );
      foreach ( $min_capabilities as $capability_name => $capability_value ) {
        $roleObj->add_cap( $capability_name );
      }
    }

    // Add the 'anonymous_user' role with minimum capabilities.
    if ( ! in_array( 'anonymous_user' , $wp_roles->roles ) ) {
      add_role(
        'anonymous_user',
        __( 'Anonymous User', 'civicrm' ),
        $min_capabilities
      );
    }

  }


  /**
   * Add CiviCRM access capabilities to WordPress roles.
   *
   * This is a callback for the 'init' hook in register_hooks().
   *
   * The legacy global scope function wp_civicrm_capability() is called by
   * postProcess() in civicrm/CRM/ACL/Form/WordPress/Permissions.php
   *
   * @since 4.6
   */
  public function set_access_capabilities() {

    // Test for existing global
    global $wp_roles;
    if ( ! isset( $wp_roles ) ) {
      $wp_roles = new WP_Roles();
    }

    /**
     * Filter the default roles with access to CiviCRM.
     *
     * The 'access_civicrm' capability is the most basic CiviCRM capability and
     * is required to see the CiviCRM menu link in the WordPress Admin menu.
     *
     * @since 4.6
     *
     * @param array The default roles with access to CiviCRM.
     * @return array The modified roles with access to CiviCRM.
     */
    $roles = apply_filters( 'civicrm_access_roles', array( 'super admin', 'administrator' ) );

     // Give access to CiviCRM to particular roles.
    foreach ( $roles as $role ) {
      $roleObj = $wp_roles->get_role( $role );
      if (
        is_object( $roleObj ) &&
        is_array( $roleObj->capabilities ) &&
        ! array_key_exists( 'access_civicrm', $wp_roles->get_role( $role )->capabilities )
      ) {
        $wp_roles->add_cap( $role, 'access_civicrm' );
      }
    }

  }


  /**
   * Get CiviCRM contact type.
   *
   * @since 4.6
   *
   * @param string $default The requested contact type.
   * @return string $ctype The computed contact type.
   */
  public function get_civicrm_contact_type( $default = NULL ) {

    // Here we are creating a new contact
    // Get the contact type from the POST variables if any
    if ( isset( $_REQUEST['ctype'] ) ) {
      $ctype = $_REQUEST['ctype'];
    } elseif (
      isset( $_REQUEST['edit'] ) &&
      isset( $_REQUEST['edit']['ctype'] )
    ) {
      $ctype = $_REQUEST['edit']['ctype'];
    } else {
      $ctype = $default;
    }

    if (
      $ctype != 'Individual' &&
      $ctype != 'Organization' &&
      $ctype != 'Household'
    ) {
      $ctype = $default;
    }

    return $ctype;

  }


} // Class CiviCRM_For_WordPress_Users ends


