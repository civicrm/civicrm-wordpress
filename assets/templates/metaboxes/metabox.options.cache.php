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

?><!-- assets/templates/metaboxes/metabox.options.cache.php -->
<?php

/**
 * Before Clear Cache section.
 *
 * @since 5.34
 */
do_action('civicrm/metabox/cache/pre');

?>
<div class="caches_error notice notice-error inline" style="background-color: #f7f7f7; display: none;">
  <p></p>
</div>
<div class="caches_success notice notice-success inline" style="background-color: #f7f7f7; display: none;">
  <p></p>
</div>

<p><?php esc_html_e('You may sometimes find yourself in situations that require the CiviCRM caches to be cleared, e.g. when template files need to be refreshed.', 'civicrm'); ?></p>

<p class="submit">
  <?php submit_button(esc_html__('Clear Caches', 'civicrm'), 'primary', 'civicrm_cache_submit', FALSE, $options); ?>
  <span class="spinner"></span>
</p>
<br class="clear">
<?php

/**
 * After Clear Cache section.
 *
 * @since 5.34
 */
do_action('civicrm/metabox/cache/post');
