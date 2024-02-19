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
    add_action('civicrm_after_rewrite_rules', [$this, 'rewrite_rules_wpml'], 10, 2);
    add_filter('icl_ls_languages', [$this, 'rewrite_civicrm_urls_wpml']);

    // Register specific CiviCRM callbacks.
    add_filter('civicrm/core/locale', [$this, 'locale_filter'], 10, 2);
    add_filter('civicrm/basepage/match', [$this, 'basepage_match'], 10, 2);
    add_filter('civicrm/core/url/base', [$this, 'base_url_filter'], 10, 2);

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
  * Checks WPML for CiviCRM Base Page matches.
  *
  * @since 5.70
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

    // Check rewrites to see if we have a match with this $post_id
    if (isset($this->rewrites[$post_id]) && !empty($this->rewrites[$post_id])) {
      $is_basepage = TRUE;
    }

    return $is_basepage;

  }

 /**
  * Filters the CiviCRM Base URL for the current language reported by WPML
  *
  * Only filters URLs that point to the front-end/back-end, since WordPress admin URLs are
  * rewritten by WPML.
  *
  * @since 5.70
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
    $raw_url = $this->wpml_remove_language_from_url($url, $sitepress, $wpml_negotiation);
    $language_url = $sitepress->convert_url($raw_url, $slug);

    return $language_url;

  }

 /**
  * Setup the rewrite rules for WPML.
  *
  * @since 5.70
  *
  * @param bool $flush_rewrite_rules True if rules flushed, false otherwise.
  * @param WP_Post $basepage The Base Page post object.
  */
  public function rewrite_rules_wpml($flush_rewrite_rules, $basepage) {

    // Bail if WPML is not present.
    if (!defined('ICL_SITEPRESS_VERSION')) {
      return;
    }

    /*
     * Collect all rewrite rules into an array.
     *
     * Because the array of specific Post IDs is added *after* the array of
     * paths for the Base Page ID, those specific rewrite rules will "win" over
     * the more general Base Page rules.
     */
    global $sitepress;
    $collected_rewrites = [];

    // Grab information about configuration
    $wpml_active = apply_filters('wpml_active_languages', NULL);

    // Obtain lagnuage negotiation setting
    $wpml_negotiation = apply_filters('wpml_setting', NULL, 'language_negotiation_type');

    // Support prefixes for a single Base Page.
    $basepage_url = get_permalink($basepage->ID);
    $basepage_raw_url = $this->wpml_remove_language_from_url($basepage_url, $sitepress, $wpml_negotiation);
    foreach ($wpml_active as $slug => $data) {
      $language_url = $sitepress->convert_url($basepage_raw_url, $slug);
      $parsed_url = wp_parse_url($language_url, PHP_URL_PATH);
      $regex_path = substr($parsed_url, 1);

      // Determine if there is a translation path
      $post_id = apply_filters('wpml_object_id', $basepage->ID, 'page', FALSE, $slug);
      if ($post_id == $basepage->ID) {
        $collected_rewrites[$post_id][] = $regex_path;
      }
      else {
        $url = get_permalink($post_id);
        $url = $sitepress->convert_url($url, $slug);
        $parsed_url = wp_parse_url($url, PHP_URL_PATH);
        $regex_path = substr($parsed_url, 1);
        $collected_rewrites[$post_id][] = $regex_path;
      }

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
    $this->rewrites = $rewrites;

    // Maybe force flush.
    if ($flush_rewrite_rules) {
      flush_rewrite_rules();
    }
  }

 /**
  * icl_ls_languages WPML Filter
  * Rewrite all CiviCRM URLs to contain the proper language structure based on the WPML settings
  *
  * @since 5.70
  *
  * @param array $languages passed by WPML to modify current path
  */
  public function rewrite_civicrm_urls_wpml($languages) {

    // Get the post slug
    global $post;
    $post_slug = isset($post->post_name) ? $post->post_name : '';
    if (empty($post_slug) && isset($post->post_name)) {
      $post_slug = $post->post_name;
    }

    // Get CiviCRM basepage slug
    $civicrm_slug = apply_filters('civicrm_basepage_slug', 'civicrm');

    // Obtain WPML lagnuage negotiation setting
    $wpml_negotiation = apply_filters('wpml_setting', NULL, 'language_negotiation_type');

    // If this is a CiviCRM Page then let's modify the actual path
    if ($post_slug == $civicrm_slug) {
      global $sitepress;
      $current_url = explode("?", $_SERVER['REQUEST_URI']);
      $civicrm_url = get_site_url(NULL, $current_url[0]);

      // Remove language from path
      $wpml_negotiation = apply_filters('wpml_setting', NULL, 'language_negotiation_type');
      $civicrm_url = $this->wpml_remove_language_from_url($civicrm_url, $sitepress, $wpml_negotiation);

      // Build query string
      $qs = [];
      parse_str($_SERVER["QUERY_STRING"], $qs);

      // Strip any WPML languages if they exist
      unset($qs['lang']);
      $query = http_build_query($qs);

      // Rebuild CiviCRM links for each language
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
  * Remove Language from URL based on WPML configuration
  *
  * @since 5.70
  *
  * @param $url passed URL to strip language from
  * @param object $sitepress WPML class
  * @param int $wpml_negotiation language negotiation setting in WPML
  *
  * @return $url base page url without language
  */
  private function wpml_remove_language_from_url($url, $sitepress, $wpml_negotiation) {
    $lang = $this->wpml_get_language_param_for_convert_url($sitepress);
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

 /**
  * Get Language for WPML.
  * This code was taken from the WPML source, because it couldn't be referenced directly
  *
  * @since 5.70
  *
  * @param object $sitepress reference to WPML class
  */
  private function wpml_get_language_param_for_convert_url($sitepress) {
    if (!empty($sitepress)) {
      if (isset($_GET['lang'])) {
        return filter_var($_GET['lang'], FILTER_SANITIZE_STRING);
      }

      if (is_multisite() && isset($_POST['lang'])) {
        return filter_var($_POST['lang'], FILTER_SANITIZE_STRING);
      }

      if (is_multisite() && defined('SUBDOMAIN_INSTALL') && SUBDOMAIN_INSTALL) {
        return $sitepress->get_current_language();
      }
    }
    return NULL;
  }

}
