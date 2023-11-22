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
 * Gets a URL that points to the CiviCRM Base Page.
 *
 * @see CiviCRM_For_WordPress_Basepage::url
 *
 * @since 5.69
 *
 * @param string $path The path being linked to, such as "civicrm/add".
 * @param array|string $query A query string to append to the link, or an array of key-value pairs.
 * @param bool $absolute Whether to force the output to be an absolute link.
 * @param string $fragment A fragment identifier (named anchor) to append to the link.
 * @param bool $htmlize Whether to encode special html characters such as &.
 * @return string $link An HTML string containing a link to the given path.
 */
function civicrm_basepage_url(
  $path = '',
  $query = '',
  $absolute = TRUE,
  $fragment = NULL,
  $htmlize = TRUE
) {
  return civi_wp()->basepage->url(
    $path,
    $query,
    $absolute,
    $fragment,
    $htmlize
  );
}

/**
 * Add CiviCRM access capabilities to WordPress roles.
 *
 * Called by postProcess() in civicrm/CRM/ACL/Form/WordPress/Permissions.php
 * Also a callback for the 'init' hook in civi_wp()->register_hooks()
 *
 * @since 4.3
 */
function wp_civicrm_capability() {
  civi_wp()->users->set_access_capabilities();
}

/**
 * Test if CiviCRM is currently being displayed in WordPress.
 *
 * Called by setTitle() in civicrm/CRM/Utils/System/WordPress.php
 * Also called at the top of this plugin file to determine AJAX status
 *
 * @since 4.3
 *
 * @return bool True if CiviCRM is displayed in WordPress, false otherwise.
 */
function civicrm_wp_in_civicrm() {
  return civi_wp()->civicrm_in_wordpress();
}

/**
 * This was the original name of the initialization function and is
 * retained for backward compatibility.
 *
 * @since 4.3
 *
 * @return bool True if CiviCRM is initialized, false otherwise.
 */
function civicrm_wp_initialize() {
  return civi_wp()->initialize();
}

/**
 * Initialize CiviCRM. Call this function from other modules too if
 * they use the CiviCRM API.
 *
 * @since 4.3
 *
 * @return bool True if CiviCRM is initialized, false otherwise.
 */
function civicrm_initialize() {
  return civi_wp()->initialize();
}

/**
 * Callback from 'edit_post_link' hook to remove edit link in civicrm_set_post_blank().
 *
 * @since 4.3
 *
 * @return string Always empty.
 */
function civicrm_set_blank() {
  return civi_wp()->clear_edit_post_link();
}

/**
 * Authentication function used by civicrm_wp_frontend().
 *
 * @since 4.3
 *
 * @param array $args The page arguments array.
 * @return bool True if authenticated, false otherwise.
 */
function civicrm_check_permission($args) {
  return civi_wp()->users->check_permission($args);
}

/**
 * Called when authentication fails in civicrm_wp_frontend().
 *
 * @since 4.3
 *
 * @return string Warning message.
 */
function civicrm_set_frontendmessage() {
  return civi_wp()->users->get_permission_denied();
}

/**
 * Invoke CiviCRM in a WordPress context.
 *
 * Callback function from add_menu_page().
 * Callback from WordPress 'init' and 'the_content' hooks.
 * Also used by civicrm_wp_shortcode_includes() and _civicrm_update_user().
 *
 * @since 4.3
 */
function civicrm_wp_invoke() {
  civi_wp()->invoke();
}

/**
 * Method that runs only when CiviCRM plugin is activated.
 *
 * @since 4.3
 */
function civicrm_activate() {
  civi_wp()->activate();
}

/**
 * Set WordPress user capabilities.
 *
 * Function to create 'anonymous_user' role, if 'anonymous_user' role is not in
 * the WordPress installation and assign minimum capabilities for all WordPress roles.
 * This function is called on plugin activation and also from upgrade_4_3_alpha1().
 *
 * @since 4.3
 */
function civicrm_wp_set_capabilities() {
  civi_wp()->users->set_wp_user_capabilities();
}

/**
 * Callback function for add_options_page() that runs the CiviCRM installer.
 *
 * @since 4.3
 */
function civicrm_run_installer() {
  civi_wp()->run_installer();
}

/**
 * Function to get the Contact Type.
 *
 * @since 4.3
 *
 * @param string $default The Contact Type.
 * @return string $ctype The Contact Type.
 */
function civicrm_get_ctype($default = NULL) {
  return civi_wp()->users->get_civicrm_contact_type($default);
}

/**
 * Getter function for global $wp_set_breadCrumb.
 *
 * Called by appendBreadCrumb() in civicrm/CRM/Utils/System/WordPress.php
 *
 * @since 4.3
 *
 * @return array $wp_set_breadCrumb The breadcrumb markup.
 */
function wp_get_breadcrumb() {
  global $wp_set_breadCrumb;
  return $wp_set_breadCrumb;
}

/**
 * Setter function for global $wp_set_breadCrumb.
 *
 * Called by appendBreadCrumb() in civicrm/CRM/Utils/System/WordPress.php
 * Called by resetBreadCrumb() in civicrm/CRM/Utils/System/WordPress.php
 *
 * @since 4.3
 *
 * @param array $breadCrumb The desired breadcrumb markup.
 * @return array $wp_set_breadCrumb The breadcrumb markup.
 */
function wp_set_breadcrumb($breadCrumb) {
  global $wp_set_breadCrumb;
  $wp_set_breadCrumb = $breadCrumb;
  return $wp_set_breadCrumb;
}
