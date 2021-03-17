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
 * Define CiviCRM_For_WordPress_Users Class.
 *
 * @since 4.6
 */
class CiviCRM_For_WordPress_Users {

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

    // Always listen for activation action.
    add_action('civicrm_activation', [$this, 'activate']);

  }

  /**
   * Plugin activation tasks.
   *
   * @since 5.6
   */
  public function activate() {

    /*
     * Assign minimum capabilities for all WordPress roles and create
     * 'anonymous_user' role.
     */
    $this->set_wp_user_capabilities();

  }

  /**
   * Register hooks.
   *
   * @since 4.6
   */
  public function register_hooks() {

    // Add CiviCRM access capabilities to WordPress roles.
    $this->set_access_capabilities();

    // Do not hook into user updates if CiviCRM not installed yet.
    if (!CIVICRM_INSTALLED) {
      return;
    }

    // Synchronise users on insert and update.
    add_action('user_register', [$this, 'update_user']);
    add_action('profile_update', [$this, 'update_user']);

    // Delete ufMatch record when a WordPress user is deleted.
    add_action('deleted_user', [$this, 'delete_user_ufmatch'], 10, 1);

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
  public function check_permission($args) {

    if ($args[0] != 'civicrm') {
      return FALSE;
    }

    $config = CRM_Core_Config::singleton();

    // Set frontend true.
    $config->userFrameworkFrontend = TRUE;

    require_once 'CRM/Utils/Array.php';

    // All profile and file urls, as well as user dashboard and tell-a-friend are valid.
    $arg1 = CRM_Utils_Array::value(1, $args);
    $invalidPaths = ['admin'];
    if (in_array($arg1, $invalidPaths)) {
      return FALSE;
    }

    return TRUE;

  }

  /**
   * Check a CiviCRM permission.
   *
   * @since 5.35
   *
   * @param str $permission The permission string.
   * @return bool $permitted True if allowed, false otherwise.
   */
  public function check_civicrm_permission($permission) {

    // Always deny if CiviCRM is not initialised.
    if (!$this->civi->initialize()) {
      return FALSE;
    }

    // Deny by default.
    $permitted = FALSE;

    // Check CiviCRM permissions.
    if (CRM_Core_Permission::check($permission)) {
      $permitted = TRUE;
    }

    return $permitted;

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
    return __('You do not have permission to access this content.', 'civicrm');
  }

  /**
   * Handle WordPress user events.
   *
   * Callback function for 'user_register' hook.
   * Callback function for 'profile_update' hook.
   *
   * CMW: seems to (wrongly) create new CiviCRM Contact every time a user changes
   * their first_name or last_name attributes in WordPress.
   *
   * @since 4.6
   *
   * @param int $user_id The numeric ID of the WordPress user.
   */
  public function update_user($user_id) {

    $user = get_userdata($user_id);
    if ($user) {
      $this->sync_user($user);
    }

  }

  /**
   * Keep WordPress user synced with CiviCRM Contact.
   *
   * @since 4.6
   *
   * @param object $user The WordPress user object.
   */
  public function sync_user($user = FALSE) {

    // Sanity check
    if ($user === FALSE || !is_a($user, 'WP_User')) {
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
      // User object.
      $user,
      // Update = true.
      TRUE,
      // CMS.
      'WordPress',
      // Contact Type.
      'Individual'
    );

  }

  /**
   * When a WordPress user is deleted, delete the UFMatch record.
   *
   * Callback function for 'delete_user' hook.
   *
   * @since 4.6
   *
   * @param $user_id The numerical ID of the WordPress user.
   */
  public function delete_user_ufmatch($user_id) {

    if (!$this->civi->initialize()) {
      return;
    }

    // Delete the UFMatch record.
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
    if (!isset($wp_roles)) {
      $wp_roles = new WP_Roles();
    }

    // Define minimum capabilities (CiviCRM permissions).
    $default_min_capabilities = [
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
    ];

    /**
     * Allow minimum capabilities to be filtered.
     *
     * @since 4.6
     *
     * @param array $default_min_capabilities The minimum capabilities.
     * @return array $default_min_capabilities The modified capabilities.
     */
    $min_capabilities = apply_filters('civicrm_min_capabilities', $default_min_capabilities);

    // Assign the minimum capabilities to all WordPress roles.
    foreach ($wp_roles->role_names as $role => $name) {
      $roleObj = $wp_roles->get_role($role);
      foreach ($min_capabilities as $capability_name => $capability_value) {
        $roleObj->add_cap($capability_name);
      }
    }

    // Add the 'anonymous_user' role with minimum capabilities.
    if (!in_array('anonymous_user', $wp_roles->roles)) {
      add_role(
        'anonymous_user',
        __('Anonymous User', 'civicrm'),
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
    if (!isset($wp_roles)) {
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
    $roles = apply_filters('civicrm_access_roles', ['super admin', 'administrator']);

    // Give access to CiviCRM to particular roles.
    foreach ($roles as $role) {
      $roleObj = $wp_roles->get_role($role);
      if (
        is_object($roleObj) &&
        is_array($roleObj->capabilities) &&
        !array_key_exists('access_civicrm', $wp_roles->get_role($role)->capabilities)
      ) {
        $wp_roles->add_cap($role, 'access_civicrm');
      }
    }

  }

  /**
   * Get CiviCRM Contact Type.
   *
   * @since 4.6
   *
   * @param string $default The requested Contact Type.
   * @return string $ctype The computed Contact Type.
   */
  public function get_civicrm_contact_type($default = NULL) {

    /*
     * Here we are creating a new Contact.
     * Get the Contact Type from the POST variables if any.
     */
    if (isset($_REQUEST['ctype'])) {
      $ctype = $_REQUEST['ctype'];
    }
    elseif (
      isset($_REQUEST['edit']) &&
      isset($_REQUEST['edit']['ctype'])
    ) {
      $ctype = $_REQUEST['edit']['ctype'];
    }
    else {
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

}
