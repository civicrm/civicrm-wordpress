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

?><!-- assets/templates/metaboxes/metabox.repo.git.php -->
<p><?php esc_html_e('For the more adventurous, here is a list of plugins that are hosted in git repositories around the web. You may need a bit more technical confidence to install and upgrade these plugins.', 'civicrm'); ?></p>

<hr/>

<ul>
  <li><em><a href="https://github.com/search?q=civicrm+wordpress&amp;type=Repositories"><?php esc_html_e('Search GitHub for CiviCRM &amp; WordPress', 'civicrm'); ?></a></em></li>
</ul>

<hr/>

<div class="plugin-repo-list-wrapper">
  <?php if (!empty($plugins->messages)) : ?>
    <ul class="plugin-repo-list">
      <?php foreach ($plugins->messages as $civicrm_plugin) : ?>
        <li>
          <strong><a href="<?php echo esc_url($civicrm_plugin['url']); ?>/"><?php echo esc_html($civicrm_plugin['name']); ?></a></strong><br>
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
