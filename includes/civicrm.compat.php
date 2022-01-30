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
 * Define CiviCRM_For_WordPress_Compat Class.
 *
 * @since 5.24
 */
class CiviCRM_For_WordPress_Compat {

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

    // Support Clean URLs when Polylang is active.
    add_action('civicrm_after_rewrite_rules', [$this, 'rewrite_rules_polylang'], 10, 2);

    // Prevent AIOSEO from stomping on CiviCRM Shortcodes.
    add_filter('aioseo_conflicting_shortcodes', [$this, 'aioseo_resolve_conflict']);

  }

  /**
   * Support Polylang.
   *
   * @since 5.24
   *
   * @param bool $flush_rewrite_rules True if rules flushed, false otherwise.
   * @param WP_Post $basepage The Base Page post object.
   */
  public function rewrite_rules_polylang($flush_rewrite_rules, $basepage) {

    // Bail if Polylang is not present.
    if (!function_exists('pll_languages_list')) {
      return;
    }

    /*
     * Collect all rewrite rules into an array.
     *
     * Because the array of specific Post IDs is added *after* the array of
     * paths for the Base Page ID, those specific rewrite rules will "win" over
     * the more general Base Page rules.
     */
    $collected_rewrites = [];

    // Support prefixes for a single Base Page.
    $basepage_url = get_permalink($basepage->ID);
    $basepage_raw_url = PLL()->links_model->remove_language_from_link($basepage_url);
    $language_slugs = pll_languages_list();
    foreach ($language_slugs as $slug) {
      $language = PLL()->model->get_language($slug);
      $language_url = PLL()->links_model->add_language_to_link($basepage_raw_url, $language);
      $parsed_url = wp_parse_url($language_url, PHP_URL_PATH);
      $regex_path = substr($parsed_url, 1);
      $collected_rewrites[$basepage->ID][] = $regex_path;
      $post_id = pll_get_post($basepage->ID, $slug);
      if (!empty($post_id)) {
        $collected_rewrites[$post_id][] = $regex_path;
      }
    };

    // Support prefixes for Base Pages in multiple languages.
    foreach ($language_slugs as $slug) {
      $post_id = pll_get_post($basepage->ID, $slug);
      if (empty($post_id)) {
        continue;
      }
      $url = get_permalink($post_id);
      $parsed_url = wp_parse_url($url, PHP_URL_PATH);
      $regex_path = substr($parsed_url, 1);
      $collected_rewrites[$basepage->ID][] = $regex_path;
      $collected_rewrites[$post_id][] = $regex_path;
    };

    // Make collection unique and add remaining rewrite rules.
    $rewrites = array_map('array_unique', $collected_rewrites);
    if (!empty($rewrites)) {
      foreach ($rewrites as $post_id => $rewrite) {
        foreach ($rewrite as $path) {
          add_rewrite_rule(
            '^' . $path . '([^?]*)?',
            'index.php?page_id=' . $post_id . '&civiwp=CiviCRM&q=civicrm%2F$matches[1]',
            'top'
          );
        }
      }
    }

    // Maybe force flush.
    if ($flush_rewrite_rules) {
      flush_rewrite_rules();
    }

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
