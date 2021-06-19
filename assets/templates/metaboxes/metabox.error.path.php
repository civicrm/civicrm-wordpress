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

?><!-- assets/templates/metaboxes/metabox.error.path.php -->
<p><?php printf(
  __('The path for including CiviCRM code files does appear to be set properly. Most likely there is an error in the %s setting in your CiviCRM settings file.', 'civicrm'),
  '<code>civicrm_root</code>'
); ?></p>

<p><?php _e('Your CiviCRM settings file location is set to:', 'civicrm'); ?><br><pre><?php echo CIVICRM_SETTINGS_PATH; ?></pre></p>

<p><?php printf(__('%s is currently set to:', 'civicrm'), '<code>civicrm_root</code>'); ?><br><pre><?php echo $civicrm_root; ?></pre></p>

<p><?php printf(__('Please check that your CiviCRM settings file is where it should be and that %s is set correctly in it. Also check that the CiviCRM code directory is where it should be. If these are both fine, then you will have to look in your logs for more information.', 'civicrm'), '<code>civicrm_root</code>'); ?></p>
