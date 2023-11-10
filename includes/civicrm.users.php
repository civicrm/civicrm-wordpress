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
   * @var string
   * Custom role name.
   * @since 5.52
   * @access private
   */
  private $custom_role_name = 'civicrm_admin';

  /**
   * Instance constructor.
   *
   * @since 4.6
   */
  public function __construct() {

    // Store reference to CiviCRM plugin object.
    $this->civi = civi_wp();

    // Always listen for activation action.
    add_action('civicrm_activate', [$this, 'activate']);

  }

  /**
   * Plugin activation tasks.
   *
   * @since 5.6
   */
  public function activate() {

    /*
     * Assign minimum capabilities to all WordPress roles and create
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
    add_action('deleted_user', [$this, 'delete_user_ufmatch']);

  }

  /**
   * Check permissions.
   *
   * This method only denies permission when the CiviCRM path that is requested
   * begins with "civicrm/admin". Its intention seems to be to exclude admin
   * requests from display on the front-end.
   *
   * Used internally by:
   *
   * - CiviCRM_For_WordPress_Basepage::basepage_handler()
   * - CiviCRM_For_WordPress_Shortcodes::render_single()
   * - civicrm_check_permission()
   *
   * @since 4.6
   *
   * @param array $args The page arguments array.
   * @return bool True if authenticated, false otherwise.
   */
  public function check_permission($args) {

    if ($args[0] !== 'civicrm') {
      return FALSE;
    }

    $config = CRM_Core_Config::singleton();

    // Set frontend true.
    $config->userFrameworkFrontend = TRUE;

    require_once 'CRM/Utils/Array.php';

    // All profile and file URLs, as well as user dashboard and tell-a-friend are valid.
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

    // Sanity check.
    if ($user === FALSE || !($user instanceof WP_User)) {
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
   * @param int $user_id The numerical ID of the WordPress user.
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

    // Define minimum capabilities (CiviCRM permissions).
    $default_min_capabilities = [
      'access_all_custom_data' => 1,
      'access_civimail_subscribe_unsubscribe_pages' => 1,
      'access_uploaded_files' => 1,
      'make_online_contributions' => 1,
      'profile_create' => 1,
      'profile_edit' => 1,
      'profile_view' => 1,
      'register_for_events' => 1,
      'sign_civicrm_petition' => 1,
      'view_event_info' => 1,
      'view_my_invoices' => 1,
      'view_public_civimail_content' => 1,
    ];

    /**
     * Allow minimum capabilities to be filtered.
     *
     * @since 4.6
     *
     * @param array $default_min_capabilities The minimum capabilities.
     */
    $min_capabilities = apply_filters('civicrm_min_capabilities', $default_min_capabilities);

    // Assign the minimum capabilities to all WordPress roles.
    $wp_roles = wp_roles();
    foreach ($wp_roles->role_names as $role => $name) {
      $roleObj = $wp_roles->get_role($role);
      foreach ($min_capabilities as $capability_name => $capability_value) {
        if (!$roleObj->has_cap($capability_name)) {
          $roleObj->add_cap($capability_name);
        }
      }
    }

    // Add the 'anonymous_user' role with minimum capabilities.
    if (!in_array('anonymous_user', $wp_roles->roles)) {
      add_role('anonymous_user', __('Anonymous User', 'civicrm'), $min_capabilities);
    }

  }

  /**
   * Add CiviCRM access capabilities to WordPress roles.
   *
   * This is called in register_hooks().
   *
   * The legacy global scope function wp_civicrm_capability() is called by
   * postProcess() in civicrm/CRM/ACL/Form/WordPress/Permissions.php
   *
   * @since 4.6
   */
  public function set_access_capabilities() {

    $wp_roles = wp_roles();

    /**
     * Filter the default roles with access to CiviCRM.
     *
     * The 'access_civicrm' capability is the most basic CiviCRM capability and
     * is required to see the CiviCRM menu link in the WordPress Admin menu.
     *
     * @since 4.6
     *
     * @param array The default roles with access to CiviCRM.
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

    // Nonce verification not necessary here.
    // phpcs:disable WordPress.Security.NonceVerification.Recommended

    /*
     * Here we are creating a new Contact.
     * Get the Contact Type from the POST variables if any.
     */
    if (isset($_REQUEST['ctype'])) {
      $ctype = sanitize_text_field(wp_unslash($_REQUEST['ctype']));
    }
    elseif (
      isset($_REQUEST['edit']) &&
      isset($_REQUEST['edit']['ctype'])
    ) {
      $ctype = sanitize_text_field(wp_unslash($_REQUEST['edit']['ctype']));
    }
    else {
      $ctype = $default;
    }

    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    if (
      $ctype !== 'Individual' &&
      $ctype !== 'Organization' &&
      $ctype !== 'Household'
    ) {
      $ctype = $default;
    }

    return $ctype;

  }

  // ---------------------------------------------------------------------------

  /**
   * Refreshes all CiviCRM capabilities.
   *
   * @since 5.52
   */
  public function refresh_capabilities() {

    // Refresh capabilities assigned at plugin activation.
    $this->set_wp_user_capabilities();

    // Refresh access capabilities.
    $this->set_access_capabilities();

    /**
     * Fires when CiviCRM has refreshed the WordPress capabilities.
     *
     * @since 5.52
     */
    do_action('civicrm_capabilities_refreshed');

  }

  /**
   * Applies all CiviCRM capabilities to the custom WordPress role.
   *
   * @since 5.52
   */
  public function refresh_custom_role_capabilities() {

    // Get the role to apply all CiviCRM permissions to.
    $custom_role = $this->get_custom_role();
    if (empty($custom_role)) {
      return;
    }

    // Get all CiviCRM capabilities.
    $capabilities = $this->get_all_civicrm_capabilities();

    // Add the capabilities if not already added.
    foreach ($capabilities as $capability) {
      if (!$custom_role->has_cap($capability)) {
        $custom_role->add_cap($capability);
      }
    }

    // Delete capabilities that no longer exist.
    $this->delete_missing_capabilities($capabilities);

    /**
     * Fires when CiviCRM has refreshed the WordPress capabilities.
     *
     * @since 5.52
     *
     * @param array $capabilities The array of CiviCRM permissions converted to WordPress capabilities.
     * @param WP_Role $custom_role The WordPress role object.
     */
    do_action('civicrm_custom_role_capabilities_refreshed', $capabilities, $custom_role);

  }

  // ---------------------------------------------------------------------------

  /**
   * Gets all CiviCRM permissions converted to WordPress capabilities.
   *
   * @since 5.52
   *
   * @return array $capabilities The array of capabilities.
   */
  public function get_all_civicrm_capabilities() {

    // Init return.
    $capabilities = [];

    // Bail if no CiviCRM.
    if (!$this->civi->initialize()) {
      return $capabilities;
    }

    // Get all CiviCRM permissions, excluding disabled components and descriptions.
    $permissions = CRM_Core_Permission::basicPermissions(FALSE, FALSE);

    // Convert to WordPress capabilities.
    foreach ($permissions as $permission => $title) {
      $capabilities[] = CRM_Utils_String::munge(strtolower($permission));
    }

    /**
     * Filters the complete set of CiviCRM capabilities.
     *
     * @since 5.52
     *
     * @param array $capabilities The complete set of CiviCRM capabilities.
     */
    return apply_filters('civicrm_all_capabilities', $capabilities);

  }

  /**
   * Deletes CiviCRM capabilities when they no longer exist.
   *
   * This can happen when an Extension which had previously added permissions
   * is disabled or uninstalled, for example.
   *
   * Things can get a bit complicated here because capabilities can appear and
   * disappear (see above) and may have been assigned to other roles while they
   * were present. Deleting missing capabilities may therefore have unintended
   * consequences. Use the "civicrm_delete_missing_capabilities" filter if you
   * are sure that you want to delete missing capabilities.
   *
   * @since 5.52
   *
   * @param array $capabilities The complete set of CiviCRM capabilities.
   */
  public function delete_missing_capabilities($capabilities) {

    /**
     * Filters whether capabilities should be deleted.
     *
     * To enable deletion of capabilities, pass boolean true.
     *
     * @since 5.52
     *
     * @param bool $allow_delete False (disabled) by default.
     */
    $allow_delete = apply_filters('civicrm_delete_missing_capabilities', FALSE);
    if ($allow_delete === FALSE) {
      return;
    }

    // Read the stored CiviCRM permissions array.
    $stored = $this->get_saved_capabilities();

    // Save and bail if we don't have any stored.
    if (empty($stored)) {
      $this->save_capabilities($capabilities);
      return;
    }

    // Find the capabilities that are missing in the current CiviCRM data.
    $not_in_current = array_diff($stored, $capabilities);

    // Get the role to delete CiviCRM permissions from.
    $custom_role = $this->get_custom_role();
    if (empty($custom_role)) {
      return;
    }

    // Delete the capabilities if not already deleted.
    foreach ($capabilities as $capability) {
      if ($custom_role->has_cap($capability)) {
        $custom_role->remove_cap($capability);
      }
    }

    // Overwrite the current permissions array.
    $this->save_capabilities($capabilities);

  }

  /**
   * Gets the stored array of CiviCRM permissions formatted as WordPress capabilities.
   *
   * @since 5.52
   *
   * @return array $capabilities The array of stored capabilities.
   */
  public function get_saved_capabilities() {

    // Get capabilities from option.
    $capabilities = get_option('civicrm_permissions_sync_perms', 'false');

    // If no option exists, cast return as array.
    if ($capabilities === 'false') {
      $capabilities = [];
    }

    return $capabilities;

  }

  /**
   * Stores the array of CiviCRM permissions formatted as WordPress capabilities.
   *
   * @since 5.52
   *
   * @param array $capabilities The array of capabilities to store.
   */
  public function save_capabilities($capabilities) {
    update_option('civicrm_permissions_sync_perms', $capabilities);
  }

  // ---------------------------------------------------------------------------

  /**
   * Retrieves the config for the custom WordPress role.
   *
   * @since 5.52
   *
   * @return array $role_data The array of custom role data.
   */
  public function get_custom_role_data() {

    // Init default role data.
    $role_data = [
      'name' => $this->custom_role_name,
      'title' => __('CiviCRM Admin', 'civicrm'),
    ];

    /**
     * Filters the default CiviCRM custom role data.
     *
     * @since 5.52
     *
     * @param array $role_data The array of default CiviCRM custom role data.
     */
    $role_data = apply_filters('civicrm_custom_role_data', $role_data);

    return $role_data;

  }

  /**
   * Checks if the custom WordPress role exists.
   *
   * @since 5.52
   *
   * @return WP_Role|bool $custom_role The custom role if it exists, or false otherwise.
   */
  public function has_custom_role() {

    // Return the custom role if it already exists.
    $custom_role = $this->get_custom_role();
    if (!empty($custom_role)) {
      return $custom_role;
    }

    return FALSE;

  }

  /**
   * Retrieves the custom WordPress role.
   *
   * @since 5.52
   *
   * @return WP_Role|bool $custom_role The custom role, or false on failure.
   */
  public function get_custom_role() {

    // Get the default role data.
    $role_data = $this->get_custom_role_data();

    // Return the custom role if it exists.
    $wp_roles = wp_roles();
    if ($wp_roles->is_role($role_data['name'])) {
      $custom_role = $wp_roles->get_role($role_data['name']);
      return $custom_role;
    }

    return FALSE;

  }

  /**
   * Creates the custom WordPress role.
   *
   * We need a role to which we add all CiviCRM permissions. This makes all the
   * CiviCRM capabilities discoverable by other plugins.
   *
   * This method creates the role if it doesn't already exist by cloning the
   * built-in WordPress "administrator" role.
   *
   * Note: it's unlikely that you will want to grant this role to any WordPress
   * users - it is purely present to make capabilities discoverable.
   *
   * @since 5.52
   *
   * @return WP_Role|bool $custom_role The custom role, or false on failure.
   */
  public function create_custom_role() {

    // Return the custom role if it already exists.
    $custom_role = $this->has_custom_role();
    if (!empty($custom_role)) {
      return $custom_role;
    }

    // Bail if the "administrator" role doesn't exist.
    $wp_roles = wp_roles();
    if (!$wp_roles->is_role('administrator')) {
      return FALSE;
    }

    // Get the default role data.
    $role_data = $this->get_custom_role_data();

    // Add new role based on the "administrator" role.
    $admin = $wp_roles->get_role('administrator');
    $custom_role = add_role($role_data['name'], $role_data['title'], $admin->capabilities);

    // Return false if something went wrong.
    if (empty($custom_role)) {
      return FALSE;
    }

    return $custom_role;

  }

  /**
   * Deletes the custom WordPress role.
   *
   * @since 5.52
   */
  public function delete_custom_role() {

    // Bail if the custom role does not exist.
    $custom_role = $this->has_custom_role();
    if (empty($custom_role)) {
      return;
    }

    // Get the default role data.
    $role_data = $this->get_custom_role_data();

    // Okay, remove it.
    remove_role($role_data['name']);

  }

}
