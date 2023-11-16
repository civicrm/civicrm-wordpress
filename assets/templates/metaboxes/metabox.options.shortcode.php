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
 * Before Shortcode section.
 *
 * @since 5.44
 */
do_action('civicrm/metabox/shortcode/pre');

?>
<div class="shortcode_notice notice notice-error inline" style="background-color: #f7f7f7; display: none;">
  <p></p>
</div>

<p><?php esc_html_e('When a CiviCRM Shortcode is embedded in a Post/Page without "hijack" being set, it is shown embedded in the content in "Shortcode Mode". If any action is taken via the Shortcode, a query string is appended to the URL and the Post/Page is shown in "Base Page Mode" and the title and content are overwritten. Choose to keep this legacy behaviour or move to the new "Remain in Shortcode Mode" behaviour.', 'civicrm'); ?></p>

<label for="shortcode_mode" class="screen-reader-text"><?php esc_html_e('Display Mode', 'civicrm'); ?></label>
<select name="shortcode_mode" id="shortcode_mode">
  <option value="modern"<?php echo $selected_modern; ?>><?php esc_html_e('Remain in Shortcode Mode', 'civicrm'); ?></option>
  <option value="legacy"<?php echo $selected_legacy; ?>><?php esc_html_e('Legacy Base Page Mode', 'civicrm'); ?></option>
</select>

<p class="submit">
  <?php submit_button(esc_html__('Saved', 'civicrm'), 'primary hide-if-no-js', 'civicrm_shortcode_submit', FALSE, $options_ajax); ?>
  <?php submit_button(esc_html__('Update', 'civicrm'), 'primary hide-if-js', 'civicrm_shortcode_post_submit', FALSE, $options_post); ?>
  <span class="spinner"></span>
</p>
<br class="clear">
<?php

/**
 * After Shortcode section.
 *
 * @since 5.44
 */
do_action('civicrm/metabox/shortcode/post');
