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

?><!-- assets/templates/page.error.php -->
<div class="wrap civicrm-wrap civicrm-error-wrap">

  <img src="<?php echo CIVICRM_PLUGIN_URL . 'assets/images/civicrm-logo.png'; ?>" width="160" height="42" alt="<?php esc_attr_e('CiviCRM Logo', 'civicrm'); ?>" id="civicrm-logo">

  <h1><?php esc_html_e('CiviCRM Troubleshooting', 'civicrm'); ?></h1>

  <p><?php esc_html_e('Something seems to be wrong with your CiviCRM installation. This page will help you try and troubleshoot the problem.', 'civicrm'); ?></p>

  <form method="post" id="civicrm_error_form" action="">

    <?php wp_nonce_field('meta-box-order', 'meta-box-order-nonce', FALSE); ?>
    <?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', FALSE); ?>
    <?php wp_nonce_field('civicrm_error_form_action', 'civicrm_error_form_nonce'); ?>

    <div id="poststuff">

      <div id="post-body" class="metabox-holder columns-<?php echo $columns; ?>">

        <div id="postbox-container-1" class="postbox-container">
          <?php do_meta_boxes($screen->id, 'side', NULL); ?>
        </div>

        <div id="postbox-container-2" class="postbox-container">
          <?php do_meta_boxes($screen->id, 'normal', NULL); ?>
          <?php do_meta_boxes($screen->id, 'advanced', NULL); ?>
        </div>

      </div><!-- #post-body -->
      <br class="clear">

    </div><!-- #poststuff -->

  </form>

</div><!-- /.wrap -->
