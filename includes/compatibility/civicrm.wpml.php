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
   * @var array
   * Collected rewrites.
   * @since 5.72
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

    // Bail if WPML is not present.
    if (!defined('ICL_SITEPRESS_VERSION')) {
      return;
    }

    // Register WPML compatibility callbacks.
    add_action('civicrm_after_rewrite_rules', [$this, 'rewrite_rules'], 10, 2);
    add_filter('icl_ls_languages', [$this, 'rewrite_civicrm_urls']);

    // Register specific CiviCRM callbacks.
    add_filter('civicrm/basepage/match', [$this, 'basepage_match'], 10, 2);
    add_filter('civicrm/core/url/base', [$this, 'base_url_filter'], 10, 2);
    add_filter('civicrm/core/locale', [$this, 'locale_filter'], 10, 2);

  }

  /**
   * Setup the rewrite rules for WPML.
   *
   * @since 5.72
   *
   * @param bool $flush_rewrite_rules True if rules flushed, false otherwise.
   * @param WP_Post $basepage The Base Page post object.
   */
  public function rewrite_rules($flush_rewrite_rules, $basepage) {

    global $sitepress, $wpml_url_filters, $wpml_url_converter;

    /*
     * Collect all rewrite rules into an array.
     *
     * Because the array of specific Post IDs is added *after* the array of
     * paths for the Base Page ID, those specific rewrite rules will "win" over
     * the more general Base Page rules.
     */
    $collected_rewrites = [];

    // Grab information about configuration.
    $wpml_active = apply_filters('wpml_active_languages', NULL);

    // Support prefixes for a single Base Page.
		$wpml_url_filters->remove_global_hooks();
		remove_filter('page_link', [$wpml_url_filters, 'page_link_filter'], 1);
    $basepage_url = get_permalink($basepage->ID);
		add_filter('page_link', [$wpml_url_filters, 'page_link_filter'], 1, 2);
		$wpml_url_filters->add_global_hooks();
    foreach ($wpml_active as $slug => $data) {
      $language_url = $sitepress->convert_url($basepage_url, $slug);
      $parsed_url = wp_parse_url($language_url, PHP_URL_PATH);
      $regex_path = substr($parsed_url, 1);
      $collected_rewrites[$basepage->ID][] = $regex_path;
      $post_id = apply_filters('wpml_object_id', $basepage->ID, 'page', FALSE, $slug);
      if (!empty($post_id)) {
        $collected_rewrites[$post_id][] = $regex_path;
      }
    }

    // Support prefixes for Base Pages in multiple languages.
    foreach ($wpml_active as $slug => $data) {

      // Determine if there is a translation.
      $post_id = apply_filters('wpml_object_id', $basepage->ID, 'page', FALSE, $slug);
      if (empty($post_id)) {
        continue;
      }

      // Get the regex path for the unfiltered permalink.
      $wpml_url_filters->remove_global_hooks();
      remove_filter('page_link', [$wpml_url_filters, 'page_link_filter'], 1);
      $url = get_permalink($post_id);
      add_filter('page_link', [$wpml_url_filters, 'page_link_filter'], 1, 2);
      $wpml_url_filters->add_global_hooks();
      $parsed_url = wp_parse_url($url, PHP_URL_PATH);
      $regex_path = substr($parsed_url, 1);

      // Get the regex path for the converted permalink.
      $converted_url = $sitepress->convert_url($url, $slug);
      $parsed_url = wp_parse_url($converted_url, PHP_URL_PATH);
      $regex_path_converted = substr($parsed_url, 1);

      $collected_rewrites[$basepage->ID][] = $regex_path;
      $collected_rewrites[$basepage->ID][] = $regex_path_converted;
      $collected_rewrites[$post_id][] = $regex_path;
      $collected_rewrites[$post_id][] = $regex_path_converted;

    }

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
   * icl_ls_languages WPML Filter
   * Rewrite all CiviCRM URLs to contain the proper language structure based on the WPML settings
   *
   * @since 5.72
   *
   * @param array $languages passed by WPML to modify current path
   */
  public function rewrite_civicrm_urls($languages) {

    // Get the post slug.
    global $post;
    $post_slug = isset($post->post_name) ? $post->post_name : '';
    if (empty($post_slug) && isset($post->post_name)) {
      $post_slug = $post->post_name;
    }

    // Get CiviCRM basepage slug.
    $civicrm_slug = apply_filters('civicrm_basepage_slug', 'civicrm');

    // Obtain WPML language negotiation setting.
    $wpml_negotiation = apply_filters('wpml_setting', NULL, 'language_negotiation_type');

    // If this is a CiviCRM Page then let's modify the actual path.
    if ($post_slug == $civicrm_slug) {
      global $sitepress;
      $current_url = explode("?", $_SERVER['REQUEST_URI']);
      $civicrm_url = get_site_url(NULL, $current_url[0]);

      // Remove language from path.
      $wpml_negotiation = apply_filters('wpml_setting', NULL, 'language_negotiation_type');
      $civicrm_url = $this->remove_language_from_link($civicrm_url, $sitepress, $wpml_negotiation);

      // Build query string.
      $qs = [];
      parse_str($_SERVER["QUERY_STRING"], $qs);

      // Strip any WPML languages if they exist.
      unset($qs['lang']);
      $query = http_build_query($qs);

      // Rebuild CiviCRM links for each language.
      foreach ($languages as &$language) {
        $url = apply_filters('wpml_permalink', $civicrm_url, $language['language_code']);
        if (!empty($query)) {
          if ($wpml_negotiation == 3 && strpos($url, '?') !== FALSE) {
            $language['url'] = $url . '&' . $query;
          }
          else {
            $language['url'] = $url . '?' . $query;
          }
        }
      }
    }

    return $languages;

  }

  /**
   * Checks WPML for CiviCRM Base Page matches.
   *
   * @since 5.72
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

    // Check rewrites to see if we have a match with this Post ID.
    if (isset($this->rewrites[$post_id]) && !empty($this->rewrites[$post_id])) {
      $is_basepage = TRUE;
    }

    return $is_basepage;

  }

  /**
   * Filters the CiviCRM Base URL for the current language reported by WPML.
   *
   * Only filters URLs that point to the front-end/back-end, since WordPress admin URLs are
   * rewritten by WPML.
   *
   * @since 5.72
   *
   * @param str $url The URL as built by CiviCRM.
   * @param bool $admin_request True if building an admin URL, false otherwise.
   * @return str $url The URL as modified by WPML.
   */
  public function base_url_filter($url, $admin_request) {

    // Skip when not defined.
    if (empty($url)) {
      return $url;
    }

    // Grab WPML language slug.
    $slug = '';
    $languages = apply_filters('wpml_active_languages', NULL);

    foreach ($languages as $id => $language) {
      if ($language['active']) {
        $slug = $id;
        break;
      }
    }

    if (empty($slug)) {
      return $url;
    }

    // Obtain lagnuage negotiation setting
    $wpml_negotiation = apply_filters('wpml_setting', NULL, 'language_negotiation_type');

    // Build the modified URL.
    global $sitepress;
    $raw_url = $this->remove_language_from_url($url, $sitepress, $wpml_negotiation);
    $language_url = $sitepress->convert_url($raw_url, $slug);

    return $language_url;

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

  /**
   * Remove Language from URL based on WPML configuration
   *
   * @since 5.72
   *
   * @param $url passed URL to strip language from
   * @param object $sitepress WPML class
   * @param int $wpml_negotiation language negotiation setting in WPML
   *
   * @return $url base page url without language
   */
  private function remove_language_from_link($url, $sitepress, $wpml_negotiation) {

    $lang = apply_filters('wpml_current_language', NULL);
    if ($lang) {
      if ($wpml_negotiation == 1) {
        $url = str_replace('/' . $lang . '/', '/', $url);
      }
      elseif ($wpml_negotiation == 3) {
        $url = str_replace('lang=' . $lang, '', $url);
      }
    }

    return $url;

  }

}
