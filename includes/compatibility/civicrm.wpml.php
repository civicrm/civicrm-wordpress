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

/**
 * WPML plugin compatatibility class.
 *
 * @since 5.66
 */
class CiviCRM_For_WordPress_Compat_WPML {

  /**
   * @var object
   * Plugin object reference.
   * @since 5.66
   * @access public
   */
  public $civi;

  /**
   * Instance constructor.
   *
   * @since 5.66
   */
  public function __construct() {

    // Store reference to CiviCRM plugin object.
    $this->civi = civi_wp();

    // Register plugin compatibility hooks.
    $this->register_hooks();

  }

  /**
   * Register hooks.
   *
   * This is called via the constructor during the "plugins_loaded" action which
   * is much earlier that CiviCRM's own internal hooks. The reason for this is
   * that compability may need callbacks for events that fire well before "init"
   * which is when CiviCRM begins to load.
   *
   * @since 5.66
   */
  public function register_hooks() {

    // Bail if CiviCRM not installed yet.
    if (!CIVICRM_INSTALLED) {
      return;
    }

    // Bail if WPML is not present.
    if (!defined('ICL_SITEPRESS_VERSION')) {
      return;
    }

    // Register WPML compatibility callbacks.
    add_filter('civicrm/core/locale', [$this, 'locale_filter'], 10, 2);

  }

  /**
   * Filters the CiviCRM locale for the current language as set by WPML.
   *
   * @since 5.66
   *
   * @param str $locale The locale as reported by WordPress.
   * @return str $locale The locale as modified by Polylang.
   */
  public function locale_filter($locale) {

    $languages = apply_filters('wpml_active_languages', NULL);
    foreach ($languages as $language) {
      if ($language['active']) {
        $locale = $language['default_locale'];
        break;
      }
    }

    return $locale;

  }

}
