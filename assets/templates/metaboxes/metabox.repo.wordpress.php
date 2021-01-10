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

?><!-- assets/templates/metaboxes/metabox.repo.wordpress.php -->
<p><?php _e('The easiest way to extend CiviCRM and integrate it with WordPress is through installing plugins that are hosted in the WordPress Plugin Directory. These can be installed and updated through the normal WordPress admin screens.', 'civicrm'); ?></p>

<hr/>

<ul>
  <li><em><a href="https://wordpress.org/plugins/tags/civicrm/"><?php _e('Search the WordPress Plugin Directory for plugins tagged CiviCRM', 'civicrm'); ?></a></em></li>
  <li><em><a href="https://wordpress.org/plugins/search/civicrm/"><?php _e('Search the WordPress Plugin Directory for references to CiviCRM', 'civicrm'); ?></a></em></li>
</ul>

<hr/>

<div class="plugin-directory-list-wrapper">
  <?php if (!empty($plugins->plugins)) : ?>
    <ul class="plugin-directory-list">
      <?php foreach ($plugins->plugins as $plugin) : ?>
        <li>
          <strong><a href="<?php echo __('https://wordpress.org/plugins/') . $plugin['slug']; ?>/"><?php echo $plugin['name']; ?></a></strong><br>
          <?php printf(__('Version %s', 'civicrm'), $plugin['version']); ?><br>
          <?php printf(__('%d+ active installations', 'civicrm'), $plugin['active_installs']); ?></br>
          <?php printf(__('Tested up to WordPress %s', 'civicrm'), $plugin['tested']); ?><br>
          <?php printf(__('Last updated %s ago', 'civicrm'), human_time_diff(strtotime($plugin['last_updated']))); ?><br>
          <?php if (!empty($plugin['short_description'])) : ?>
            <span class="description"><?php echo $plugin['short_description']; ?></span><br>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php else : ?>
    <p><?php _e('Could not fetch list of plugins.', 'civicrm'); ?></p>
  <?php endif; ?>
</div>
