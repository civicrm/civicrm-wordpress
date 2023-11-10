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

?><!-- assets/templates/metaboxes/metabox.error.help.php -->
<p><?php printf(
  /* translators: 1: Opening anchor tag, 2: Closing anchor tag, 3: Opening anchor tag, 4: Closing anchor tag, 5: Opening anchor tag, 6: Closing anchor tag. */
  __('Please review the %1$sWordPress Installation Guide%2$s and the %3$sTroubleshooting page%4$s for assistance. If you still need help, you can often find solutions to your issue by searching for the error message in the %5$sinstallation support section of the community forum%6$s.', 'civicrm'),
  '<a href="https://docs.civicrm.org/installation/en/latest/wordpress/">', '</a>',
  '<a href="https://docs.civicrm.org/sysadmin/en/latest/troubleshooting/">', '</a>',
  '<a href="https://civicrm.stackexchange.com/">', '</a>'
); ?></p>
