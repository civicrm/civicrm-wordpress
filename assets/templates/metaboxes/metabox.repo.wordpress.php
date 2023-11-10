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

?><!-- assets/templates/metaboxes/metabox.repo.wordpress.php -->
<p><?php esc_html_e('The easiest way to extend CiviCRM and integrate it with WordPress is through installing plugins that are hosted in the WordPress Plugin Directory. These can be installed and updated through the normal WordPress admin screens.', 'civicrm'); ?></p>

<hr/>

<ul>
  <li><em><a href="https://wordpress.org/plugins/tags/civicrm/"><?php esc_html_e('Search the WordPress Plugin Directory for plugins tagged CiviCRM', 'civicrm'); ?></a></em></li>
  <li><em><a href="https://wordpress.org/plugins/search/civicrm/"><?php esc_html_e('Search the WordPress Plugin Directory for references to CiviCRM', 'civicrm'); ?></a></em></li>
</ul>

<hr/>

<div class="plugin-directory-list-wrapper">
  <?php if (!empty($plugins->plugins)) : ?>
    <ul class="plugin-directory-list">
      <?php foreach ($plugins->plugins as $civicrm_plugin) : ?>
        <li>
          <?php /* Deliberate use of default domain so that WordPress "translates" the URL. */ ?>
          <?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ ?>
          <strong><a href="<?php echo esc_url(__('https://wordpress.org/plugins/') . $civicrm_plugin['slug']); ?>/"><?php echo esc_html($civicrm_plugin['name']); ?></a></strong><br>
          <?php /* translators: %s: The plugin version. */ ?>
          <?php echo esc_html(sprintf(__('Version %s', 'civicrm'), $civicrm_plugin['version'])); ?><br>
          <?php /* translators: %d: The number of installs. */ ?>
          <?php echo esc_html(sprintf(__('%d+ active installations', 'civicrm'), $civicrm_plugin['active_installs'])); ?></br>
          <?php /* translators: %s: The version of WordPress the plugin is tested to. */ ?>
          <?php echo esc_html(sprintf(__('Tested up to WordPress %s', 'civicrm'), $civicrm_plugin['tested'])); ?><br>
          <?php /* translators: %s: The date of the last plugin update. */ ?>
          <?php echo esc_html(sprintf(__('Last updated %s ago', 'civicrm'), human_time_diff(strtotime($civicrm_plugin['last_updated'])))); ?><br>
          <?php if (!empty($civicrm_plugin['short_description'])) : ?>
            <span class="description"><?php echo esc_html($civicrm_plugin['short_description']); ?></span><br>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php else : ?>
    <p><?php esc_html_e('Could not fetch list of plugins.', 'civicrm'); ?></p>
  <?php endif; ?>
</div>
