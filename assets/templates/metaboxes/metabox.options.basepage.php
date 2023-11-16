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

?><!-- assets/templates/metaboxes/metabox.options.basepage.php -->
<?php

/**
 * Before Basepage section.
 *
 * @since 5.34
 */
do_action('civicrm/metabox/basepage/pre');

?>
<div class="basepage_notice notice notice-error inline" style="background-color: #f7f7f7;<?php echo $hidden; ?>">
  <p><?php echo $message; ?></p>
</div>

<p>
  <?php esc_html_e('CiviCRM needs a WordPress Page to show its content on the public-facing pages of your website.', 'civicrm'); ?>
  <?php if (!($basepage instanceof WP_Post)) : ?>
    <em class="basepage_feedback"><?php esc_html_e('Please select a Page from the drop-down for CiviCRM to use as its Base Page. If CiviCRM was able to create one automatically, there should be one with the title "CiviCRM". If not, please select another suitable WordPress Page.', 'civicrm'); ?></em>
  <?php else : ?>
    <em class="basepage_feedback"><?php esc_html_e('It appears that your Base Page has been set. Looking good.', 'civicrm'); ?></em>
  <?php endif; ?>
</p>

<label for="page_id" class="screen-reader-text"><?php esc_html_e('Choose Base Page', 'civicrm'); ?></label>
<?php wp_dropdown_pages($params); ?>

<p class="submit">
  <?php submit_button(__('Saved', 'civicrm'), 'primary hide-if-no-js', 'civicrm_basepage_submit', FALSE, $options_ajax); ?>
  <?php submit_button(__('Update', 'civicrm'), 'primary hide-if-js', 'civicrm_basepage_post_submit', FALSE, $options_post); ?>
  <span class="spinner"></span>
</p>
<br class="clear">
<?php

/**
 * After Basepage section.
 *
 * @since 5.34
 */
do_action('civicrm/metabox/basepage/post');
