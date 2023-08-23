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
 * Miscellaneous plugin compatibility class.
 *
 * @since 5.24
 */
class CiviCRM_For_WordPress_Compat_Misc {

  /**
   * @var object
   * Plugin object reference.
   * @since 5.24
   * @access public
   */
  public $civi;

  /**
   * Instance constructor.
   *
   * @since 5.24
   */
  public function __construct() {

    // Store reference to CiviCRM plugin object.
    $this->civi = civi_wp();

    // Register plugin compatibility hooks.
    $this->register_hooks();

  }

  /**
   * Register plugin compatibility hooks.
   *
   * This is called via the constructor during the "plugins_loaded" action which
   * is much earlier that CiviCRM's own internal hooks. The reason for this is
   * that compability may need callbacks for events that fire well before "init"
   * which is when CiviCRM begins to load.
   *
   * @since 5.24
   */
  public function register_hooks() {

    // Bail if CiviCRM not installed yet.
    if (!CIVICRM_INSTALLED) {
      return;
    }

    // Register Base Page callbacks.
    add_action('civicrm_basepage_parsed', [$this, 'register_basepage_hooks']);

    // Prevent AIOSEO from stomping on CiviCRM Shortcodes.
    add_filter('aioseo_conflicting_shortcodes', [$this, 'aioseo_resolve_conflict']);

  }

  /**
   * Register Base Page compatibility hooks.
   *
   * @since 5.66
   */
  public function register_basepage_hooks() {

    // Add compatibility with Yoast SEO plugin's Open Graph title.
    add_filter('wpseo_opengraph_title', [$this, 'wpseo_page_title'], 100, 1);

    // Don't let the Yoast SEO plugin parse the Base Page title.
    if (class_exists('WPSEO_Frontend')) {
      $frontend = WPSEO_Frontend::get_instance();
      remove_filter('pre_get_document_title', [$frontend, 'title'], 15);
    }

  }

  /**
   * Get CiviCRM Base Page title for Open Graph elements.
   *
   * Callback method for 'wpseo_opengraph_title' hook, to provide compatibility
   * with the WordPress SEO plugin.
   *
   * @since 4.6.4
   *
   * @param string $post_title The title of the WordPress page or post.
   * @return string $basepage_title The title of the CiviCRM entity.
   */
  public function wpseo_page_title($post_title) {

    // Hand back our Base Page title.
    return $this->civi->basepage->title_get();

  }

  /**
   * Fixes AIOSEO's attempt to modify Shortcodes.
   *
   * @see https://civicrm.stackexchange.com/questions/40765/wp-all-in-one-seo-plugin-conflict
   *
   * @since 5.45
   *
   * @param array $conflicting_shortcodes The existing AIOSEO Conflicting Shortcodes array.
   * @return array $conflicting_shortcodes The modified AIOSEO Conflicting Shortcodes array.
   */
  public function aioseo_resolve_conflict($conflicting_shortcodes) {
    $conflicting_shortcodes['CiviCRM'] = 'civicrm';
    return $conflicting_shortcodes;
  }

}
