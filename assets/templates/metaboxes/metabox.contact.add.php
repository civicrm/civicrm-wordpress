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

?><!-- assets/templates/metaboxes/metabox.contact.add.php -->
<?php

/**
 * Before Contact Add section.
 *
 * @since 5.34
 */
do_action('civicrm/metabox/contact/add/pre');

?>
<form name="contact-quick-add" action="<?php echo esc_url(admin_url('admin.php')); ?>" method="post" id="contact-quick-add" class="initial-form">

  <div class="civicrm_quick_add_error notice notice-error inline" style="background-color: #f7f7f7;<?php echo $error_css; ?>">
    <p><?php echo $error; ?></p>
  </div>

  <?php wp_nonce_field('civicrm_quick_add_action', 'civicrm_quick_add_nonce'); ?>

  <div class="input-text-wrap" id="contact-first-name-wrap">
    <label for="civicrm_quick_add_first_name"><?php esc_html_e('First Name', 'civicrm'); ?></label>
    <input type="text" name="civicrm_quick_add_first_name" id="civicrm_quick_add_first_name" autocomplete="off" />
    <br class="clear" />
  </div>

  <div class="input-text-wrap" id="contact-last-name-wrap">
    <label for="civicrm_quick_add_last_name"><?php esc_html_e('Last Name', 'civicrm'); ?></label>
    <input type="text" name="civicrm_quick_add_last_name" id="civicrm_quick_add_last_name" autocomplete="off" />
    <br class="clear" />
  </div>

  <div class="input-text-wrap" id="contact-email-wrap">
    <label for="civicrm_quick_add_email"><?php esc_html_e('Email', 'civicrm'); ?></label>
    <input type="text" name="civicrm_quick_add_email" id="civicrm_quick_add_email" autocomplete="off" />
    <br class="clear" />
  </div>

  <p class="submit">
    <?php submit_button(esc_html__('Add Contact', 'civicrm'), 'primary', 'civicrm_quick_add_submit', FALSE, $options); ?>
    <span class="spinner"></span>
    <br class="clear" />
  </p>

</form>

<div class="contacts-added-wrap<?php echo $visiblity_class; ?>">
  <h3><?php esc_html_e('Recently Added Contacts', 'civicrm'); ?></h3>

  <div class="civicrm_quick_add_success notice notice-success inline" style="background-color: #f7f7f7; display: none;">
    <p></p>
  </div>

  <ul class="contacts-added-list">
    <?php if (!empty($recents)) : ?>
      <?php foreach ($recents as $recent) : ?>
        <?php echo $recent; ?>
      <?php endforeach; ?>
    <?php endif; ?>
  </ul>
</div>

<?php

/**
 * After Contact Add section.
 *
 * @since 5.34
 */
do_action('civicrm/metabox/contact/add/post');
