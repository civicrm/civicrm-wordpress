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

?><!-- assets/templates/metaboxes/metabox.options.email.php -->
<?php

/**
 * Before Email Sync section.
 *
 * @since 5.34
 */
do_action('civicrm/metabox/email_sync/pre');

?>
<div class="email_notice notice notice-error inline" style="background-color: #f7f7f7; display: none;">
  <p></p>
</div>

<p><?php esc_html_e('When a WordPress User updates their Email, CiviCRM will automatically update the Primary Email of their linked Contact record. This setting lets you choose whether the reverse update should happen - i.e. if the Primary Email of a Contact that has a linked WordPress User is updated, do you want CiviCRM to update the WordPress User Email?', 'civicrm'); ?></p>

<label for="sync_email" class="screen-reader-text"><?php esc_html_e('Sync Emails', 'civicrm'); ?></label>
<select name="sync_email" id="sync_email">
  <option value="yes"<?php echo $selected_yes; ?>><?php esc_html_e('Yes', 'civicrm'); ?></option>
  <option value="no"<?php echo $selected_no; ?>><?php esc_html_e('No', 'civicrm'); ?></option>
</select>

<p class="submit">
  <?php submit_button(esc_html__('Saved', 'civicrm'), 'primary hide-if-no-js', 'civicrm_email_submit', FALSE, $options_ajax); ?>
  <?php submit_button(esc_html__('Update', 'civicrm'), 'primary hide-if-js', 'civicrm_email_post_submit', FALSE, $options_post); ?>
  <span class="spinner"></span>
</p>
<br class="clear">
<?php

/**
 * After Email Sync section.
 *
 * @since 5.34
 */
do_action('civicrm/metabox/email_sync/post');
