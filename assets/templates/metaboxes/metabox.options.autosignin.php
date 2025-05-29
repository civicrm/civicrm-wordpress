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

?><!-- assets/templates/metaboxes/metabox.options.shortcode.php -->
<?php

/**
 * Before Automatically Sign In User section.
 *
 * @since 6.4
 */
do_action('civicrm/metabox/autosigninuser/pre');

?>
<div class="auto_sign_in_user_notice notice notice-error inline" style="background-color: #f7f7f7; display: none;">
  <p></p>
</div>

<p><?php esc_html_e('When a WordPress User is created, CiviCRM will automatically log in the user after the creation of the user. This setting lets you choose whether CiviCRM will automatically log the user in after creation. Do you want to allow CiviCRM to automatically log in the user after it is created?', 'civicrm'); ?></p>

<label for="auto_sign_in_user" class="screen-reader-text"><?php esc_html_e('Automatically Sign In User', 'civicrm'); ?></label>
<select name="auto_sign_in_user" id="auto_sign_in_user">
  <option value="yes"<?php echo $selected_yes; ?>><?php esc_html_e('Yes', 'civicrm'); ?></option>
  <option value="no"<?php echo $selected_no; ?>><?php esc_html_e('No', 'civicrm'); ?></option>
</select>

<p class="submit">
  <?php submit_button(esc_html__('Saved', 'civicrm'), 'primary hide-if-no-js', 'civicrm_auto_sign_in_user_submit', FALSE, $options_ajax); ?>
  <?php submit_button(esc_html__('Update', 'civicrm'), 'primary hide-if-js', 'civicrm_auto_sign_in_user_post_submit', FALSE, $options_post); ?>
  <span class="spinner"></span>
</p>
<br class="clear">
<?php

/**
 * After Automatically Sign In User section.
 *
 * @since 6.4
 */
do_action('civicrm/metabox/autosigninuser/post');
