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

?><!-- assets/templates/metaboxes/metabox.error.php.php -->
<?php /* translators: 1: The minimum PHP version, 2: The current PHP version. */ ?>
<p><?php printf(__('CiviCRM requires PHP version %1$s or greater. You are running PHP version %2$s', 'civicrm'), CIVICRM_WP_PHP_MINIMUM, PHP_VERSION); ?></p>

<p><?php esc_html_e('You will have to upgrade PHP before you can run this version CiviCRM.', 'civicrm'); ?></p>

<p><?php esc_html_e('To continue using CiviCRM without upgrading PHP, you will have to revert both the plugin and the database to a backup of your previous version.', 'civicrm'); ?></p>
