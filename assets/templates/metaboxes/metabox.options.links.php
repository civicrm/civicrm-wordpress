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

?><!-- assets/templates/metaboxes/metabox.options.links.php -->
<?php

/**
 * Before Links section.
 *
 * @since 5.34
 */
do_action('civicrm/metabox/links/pre');

?>
<p><?php esc_html_e('Below is a list of shortcuts to some CiviCRM admin pages that are important when you are setting up CiviCRM. When these settings are correctly configured, your CiviCRM installation should be ready for you to customise to your requirements.', 'civicrm'); ?></p>

<ul>
  <?php foreach ($admin_links as $admin_link) : ?>
    <li>
      <a href="<?php echo $admin_link['url']; ?>"><?php echo $admin_link['text']; ?></a>
    </li>
  <?php endforeach; ?>
</ul>

<hr>

<p><?php esc_html_e('Shortcuts to some CiviCRM maintenance tasks.', 'civicrm'); ?></p>

<ul>
  <?php foreach ($maintenance_links as $maintenance_link) : ?>
    <li>
      <a href="<?php echo $maintenance_link['url']; ?>"><?php echo $maintenance_link['text']; ?></a>
      <?php if (!empty($maintenance_link['description'])) : ?>
        <p class="description"><?php echo $maintenance_link['description']; ?></p>
      <?php endif; ?>
    </li>
  <?php endforeach; ?>
</ul>

<?php

/**
 * After Links section.
 *
 * @since 5.34
 */
do_action('civicrm/metabox/links/post');
