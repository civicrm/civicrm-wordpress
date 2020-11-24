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

?>

<div class="crm-container crm-public<?php echo $class; ?>">

  <?php if ($show_title) { ?>
    <h2><?php echo $title; ?></h2>
  <?php } ?>

  <?php if ($description) { ?>
    <div class="civi-description"><?php echo $description; ?></div>
  <?php } ?>

  <p><?php echo $more_link; ?></p>

  <?php if ($empowered_enabled) { ?>
  <div class="crm-public-footer">
    <?php echo $footer; ?>
  </div>
  <?php } ?>

</div>
