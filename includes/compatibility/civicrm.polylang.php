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
 * Polylang plugin compatatibility class.
 *
 * @since 5.66
 */
class CiviCRM_For_WordPress_Compat_Polylang {

  /**
   * @var object
   * Plugin object reference.
   * @since 5.66
   * @access public
   */
  public $civi;

  /**
   * @var array
   * Base Page data.
   * @since 5.66
   * @access private
   */
  private $basepages = [];

  /**
   * @var array
   * Collected rewrites.
   * @since 5.66
   * @access private
   */
  private $rewrites = [];

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

    // Bail if Polylang is not present.
    if (!function_exists('pll_languages_list')) {
      return;
    }

    // Register Polylang compatibility callbacks.
    add_action('civicrm_after_rewrite_rules', [$this, 'rewrite_rules'], 10, 2);
    add_filter('pll_check_canonical_url', [$this, 'canonical_url'], 10, 2);
    add_filter('civicrm/basepage/match', [$this, 'basepage_match'], 10, 2);
    add_filter('civicrm/core/url/base', [$this, 'base_url_filter'], 10, 2);
    add_filter('civicrm/core/locale', [$this, 'locale_filter'], 10, 2);

  }

  /**
   * Support Polylang.
   *
   * @since 5.24
   * @since 5.66 Moved to this class.
   *
   * @param bool $flush_rewrite_rules True if rules flushed, false otherwise.
   * @param WP_Post $basepage The Base Page post object.
   */
  public function rewrite_rules($flush_rewrite_rules, $basepage) {

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
    $this->rewrites = array_map('array_unique', $collected_rewrites);
    if (!empty($this->rewrites)) {
      foreach ($this->rewrites as $post_id => $rewrite) {
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
   * Prevents Polylang from redirecting CiviCRM URLs.
   *
   * @since 5.66
   *
   * @param string|false $redirect_url False or the URL to redirect to.
   * @param PLL_Language $language The language detected.
   * @return string|false $redirect_url False or the URL to redirect to.
   */
  public function canonical_url($redirect_url, $language) {

    // Bail if this is not a Page.
    if (!is_page()) {
      return $redirect_url;
    }

    // Bail if this is not a Base Page.
    $post = get_post();
    if (!empty($this->rewrites)) {
      foreach ($this->rewrites as $post_id => $rewrite) {
        if ($post_id === $post->ID) {
          return FALSE;
        }
      }
    }

    return $redirect_url;

  }

  /**
   * Checks Polylang for CiviCRM Base Page matches.
   *
   * @since 5.66
   *
   * @param bool $is_basepage TRUE if the Post ID matches the Base Page ID, FALSE otherwise.
   * @param int $post_id The WordPress Post ID to check.
   * @return bool $is_basepage TRUE if the Post ID matches the Base Page ID, FALSE otherwise.
   */
  public function basepage_match($is_basepage, $post_id) {

    // Bail if this is already the Base Page.
    if ($is_basepage) {
      return $is_basepage;
    }

    // Bail if there are no rewrites.
    if (empty($this->rewrites)) {
      return $is_basepage;
    }

    foreach ($this->rewrites as $page_id => $rewrite) {
      if ($post_id === $page_id) {
        $is_basepage = TRUE;
      }
    }

    return $is_basepage;

  }

  /**
   * Filters the CiviCRM Base URL for the current language reported by Polylang.
   *
   * Only filters URLs that point to the front-end, since WordPress admin URLs are not
   * rewritten by Polylang.
   *
   * @since 5.66
   *
   * @param str $url The URL as built by CiviCRM.
   * @param bool $admin_request True if building an admin URL, false otherwise.
   * @return str $url The URL as modified by Polylang.
   */
  public function base_url_filter($url, $admin_request) {

    // Skip when not defined.
    if (empty($url) || $admin_request) {
      return $url;
    }

    // Find the language slug.
    $slug = pll_current_language();
    if (empty($slug)) {
      return $url;
    }

    // Build the modified URL.
    $raw_url = PLL()->links_model->remove_language_from_link($url);
    $language = PLL()->model->get_language($slug);
    $language_url = PLL()->links_model->add_language_to_link($raw_url, $language);

    return $language_url;

  }

  /**
   * Filters the CiviCRM locale for the current language as set by Polylang.
   *
   * @since 5.66
   *
   * @param str $locale The locale as reported by WordPress.
   * @return str $locale The locale as modified by Polylang.
   */
  public function locale_filter($locale) {

    $pll_locale = pll_current_language('locale');
    if (!empty($pll_locale)) {
      $locale = $pll_locale;
    }

    return $locale;

  }

}
