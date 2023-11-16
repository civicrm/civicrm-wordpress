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

?><!-- assets/templates/metaboxes/metabox.options.permissions.php -->
<?php

/**
 * Before Permissions section.
 *
 * @since 5.52
 */
do_action('civicrm/metabox/permissions/pre');

?>
<div class="permissions_error notice notice-error inline" style="background-color: #f7f7f7; display: none;">
  <p></p>
</div>
<div class="permissions_success notice notice-success inline" style="background-color: #f7f7f7; display: none;">
  <p></p>
</div>

<p><?php esc_html_e('You may need all CiviCRM permissions to be exposed as capabilities in WordPress, e.g. when you want them to be discoverable by other plugins. CiviCRM can do this by creating a role called "CiviCRM Admin" that has the complete set of CiviCRM capabilities. If you choose not to create the "CiviCRM Admin" role, then refreshing will just rebuild the existing set of capabilities.', 'civicrm'); ?></p>

<label for="permissions_role" class="screen-reader-text"><?php esc_html_e('CiviCRM Admin Role', 'civicrm'); ?></label>
<select name="permissions_role" id="permissions_role">
  <option value="enable"<?php echo $selected_enable; ?>><?php esc_html_e('Enable the CiviCRM Admin role', 'civicrm'); ?></option>
  <option value="disable"<?php echo $selected_disable; ?>><?php esc_html_e('Do not enable the CiviCRM Admin role', 'civicrm'); ?></option>
</select>

<p class="submit">
  <?php submit_button(esc_html__('Refresh Permissions', 'civicrm'), 'primary', 'civicrm_permissions_submit', FALSE, $options); ?>
  <span class="spinner"></span>
</p>
<br class="clear">
<?php

/**
 * After Permissions section.
 *
 * @since 5.52
 */
do_action('civicrm/metabox/permissions/post');
