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

?><!-- assets/templates/metaboxes/metabox.repo.ext.php -->
<p><?php _e('Extensions are a bit like plugins because they enhance what CiviCRM can do. You can find, install and manage many of them in CiviCRM.', 'civicrm'); ?></p>

<ul>
  <li><em><a href="<?php echo $extensions_url; ?>"><?php _e('Go to your CiviCRM Extensions Page', 'civicrm'); ?></a></em></li>
</ul>

<hr/>

<p><?php _e('Here are some other places you can find them.', 'civicrm'); ?></p>

<ul>
  <li><em><a href="https://civicrm.org/extensions/wordpress"><?php _e('Search CiviCRM Website for "Extensions" that are compatible with WordPress', 'civicrm'); ?></a></em></li>
  <li><em><a href="https://lab.civicrm.org/explore/projects?tag=wordpress"><?php _e('Search CiviCRM GitLab for "Extensions" that are compatible with WordPress', 'civicrm'); ?></a></em></li>
  <li><em><a href="https://github.com/search?p=2&q=civicrm+extension&type=Repositories"><?php _e('Search GitHub for "CiviCRM Extensions"', 'civicrm'); ?></a></em></li>
</ul>
