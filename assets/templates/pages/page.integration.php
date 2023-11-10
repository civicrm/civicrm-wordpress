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

?><!-- assets/templates/page.integration.php -->
<div class="wrap civicrm-wrap civicrm-integration-wrap">

  <img src="<?php echo CIVICRM_PLUGIN_URL . 'assets/images/civicrm-logo.png'; ?>" width="160" height="42" alt="<?php esc_attr_e('CiviCRM Logo', 'civicrm'); ?>" id="civicrm-logo">

  <h1><?php esc_html_e('Integrating CiviCRM with WordPress', 'civicrm'); ?></h1>

  <p><?php esc_html_e('We have collected some resources to help you make the most of CiviCRM in WordPress.', 'civicrm'); ?></p>

  <form method="post" id="civicrm_integration_form" action="<?php /* echo $this->page_submit_url_get(); */ ?>">

    <?php wp_nonce_field('meta-box-order', 'meta-box-order-nonce', FALSE); ?>
    <?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', FALSE); ?>

    <div id="welcome-panel" class="welcome-panel hidden">
    </div>

    <div id="dashboard-widgets-wrap">

      <div id="dashboard-widgets" class="metabox-holder<?php echo $columns_css; ?>">

        <div id="postbox-container-1" class="postbox-container">
          <?php do_meta_boxes($screen->id, 'normal', ''); ?>
        </div>

        <div id="postbox-container-2" class="postbox-container">
          <?php do_meta_boxes($screen->id, 'side', ''); ?>
        </div>

      </div><!-- #post-body -->
      <br class="clear">

    </div><!-- #poststuff -->

  </form>

</div><!-- /.wrap -->
