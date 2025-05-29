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
 * @since 5.80
 */
do_action('civicrm/metabox/theme/pre');

?>
<div class="theme_notice notice notice-error inline" style="background-color: #f7f7f7; display: none;">
  <p></p>
</div>

<p><?php /* translators: 1: The opening code tag, 2: The closing code tag. */ printf(esc_html__('Some themes do not use "The Loop" to render page and post content. If a theme does not use The Loop, then it will not display CiviCRM Shortcodes. Try using the "Content Filter" check instead. If this does not work, you can use the %1$scivicrm_theme_compatibility_mode%2$s filter to implement your own check.', 'civicrm'), '<code>', '</code>'); ?></p>

<label for="theme_compatibility_mode" class="screen-reader-text"><?php esc_html_e('Theme Compatibility', 'civicrm'); ?></label>
<select name="theme_compatibility_mode" id="theme_compatibility_mode">
  <option value="loop"<?php echo $selected_loop; ?>><?php esc_html_e('Check for The Loop', 'civicrm'); ?></option>
  <option value="filter"<?php echo $selected_filter; ?>><?php esc_html_e('Check the Content Filter', 'civicrm'); ?></option>
</select>

<p class="submit">
  <?php submit_button(esc_html__('Saved', 'civicrm'), 'primary hide-if-no-js', 'civicrm_theme_submit', FALSE, $options_ajax); ?>
  <?php submit_button(esc_html__('Update', 'civicrm'), 'primary hide-if-js', 'civicrm_theme_post_submit', FALSE, $options_post); ?>
  <span class="spinner"></span>
</p>
<br class="clear">
<?php

/**
 * After Shortcode section.
 *
 * @since 5.80
 */
do_action('civicrm/metabox/theme/post');
