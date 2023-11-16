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

?><!-- assets/templates/metaboxes/metabox.repo.ext.php -->
<p><?php esc_html_e('Extensions are a bit like plugins because they enhance what CiviCRM can do. You can find, install and manage many of them in CiviCRM.', 'civicrm'); ?></p>

<ul>
  <li><em><a href="<?php echo $extensions_url; ?>"><?php esc_html_e('Go to your CiviCRM Extensions Page', 'civicrm'); ?></a></em></li>
</ul>

<hr/>

<p><?php esc_html_e('Here are some other places you can find them.', 'civicrm'); ?></p>

<ul>
  <li><em><a href="https://civicrm.org/extensions/wordpress"><?php esc_html_e('Search CiviCRM Website for "Extensions" that are compatible with WordPress', 'civicrm'); ?></a></em></li>
  <li><em><a href="https://lab.civicrm.org/explore/projects?tag=wordpress"><?php esc_html_e('Search CiviCRM GitLab for "Extensions" that are compatible with WordPress', 'civicrm'); ?></a></em></li>
  <li><em><a href="https://github.com/search?p=2&q=civicrm+extension&type=Repositories"><?php esc_html_e('Search GitHub for "CiviCRM Extensions"', 'civicrm'); ?></a></em></li>
</ul>
