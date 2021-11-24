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

// Kick out if uninstall not called from WordPress.
if (!defined('WP_UNINSTALL_PLUGIN')) {
  exit();
}

// Delete options that this plugin has set.
delete_option('civicrm_activation_in_progress');
delete_option('civicrm_rules_flushed');

// TODO: Remove the CiviCRM Base Page(s).
// TODO: Remove the directory/directories that "civicrm.settings.php" lives in.
// TODO: Remove the CiviCRM database(s).
