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

    // Support Clean URLs when WPML is active.
    add_action('civicrm_after_rewrite_rules', [$this, 'rewrite_rules_wpml'], 10, 2);
    add_filter('icl_ls_languages', [$this, 'rewrite_civicrm_urls_wpml']);

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

    // Build rewrite rules
    $this->wp_add_rewrite_rules($collected_rewrite, $flush_rewrite_rules);
  }

  /**
   * Support WPML.
   *
   * @since 5.55.0
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
    $wpml_options = get_option('icl_sitepress_settings');
    $language_slugs = (isset($wpml_options['active_languages'])) ? $wpml_options['active_languages'] : [];

    // Support prefixes for a single Base Page.
    $basepage_url = get_permalink($basepage->ID);

    $basepage_raw_url = $this->wpml_remove_language_from_url($basepage_url, $sitepress, $wpml_options['language_negotiation_type']);
    foreach ($language_slugs as $id => $slug) {
      $language_url = $sitepress->convert_url($basepage_raw_url, $slug);
      $parsed_url = wp_parse_url($language_url, PHP_URL_PATH);
      $regex_path = substr($parsed_url, 1);
      $collected_rewrites[$basepage->ID][] = $regex_path;
      $post_id = apply_filters('wpml_object_id', $basepage->ID, $slug);
      if (!empty($post_id)) {
        continue;
      }

      $url = get_permalink($post_id);
      $parsed_url = wp_parse_url($url, PHP_URL_PATH);
      $regex_path = substr($parsed_url, 1);
      $collected_rewrites[$basepage->ID][] = $regex_path;
      $collected_rewrites[$post_id][] = $regex_path;
    };

    // Build rewrite rules
    $this->wp_add_rewrite_rules($collected_rewrites, $flush_rewrite_rules);
  }

  /**
   * icl_ls_languages WPML Filter
   *
   * Modify all CiviCRM URLs to contain the proper language structure based on the WPML settings
   * @param array $languages passed by WPML to modify current path
   */
  public function rewrite_civicrm_urls_wpml($languages) {

    // Get the post slug
    global $post;
    $post_slug = isset($post->post_name) ? $post->post_name : '';
    if (empty($post_slug) && isset($post->post_name)) {
      $post_slug = $post->post_name;
    }

    $civicrm_slug = apply_filters('civicrm_basepage_slug', 'civicrm');

    // If this is a CiviCRM Page then let's modify the actual path
    if ($post_slug == $civicrm_slug) {
      global $sitepress;
      $current_url = explode("?", $_SERVER['REQUEST_URI']);
      $civicrm_url = get_site_url(null, $current_url[0]);

      // Remove lang from path
      $wpml_options = get_option('icl_sitepress_settings');
      $wpml_lang_conf = $wpml_options['language_negotiation_type'];
      $civicrm_url = $this->wpml_remove_language_from_url($civicrm_url, $sitepress, $wpml_lang_conf);

      // Build query string
      $qs = [];
      parse_str($_SERVER["QUERY_STRING"], $qs);

      // Strip any WPML languages if they exist
      unset($qs['lang']);
      $query = http_build_query($qs);

      // Rebuild CiviCRM links for each language
      foreach($languages as &$language) {
        $url = apply_filters('wpml_permalink', $civicrm_url, $language['language_code']);

        if (!empty($query)) {
          if ($wpml_lang_conf == 3 && strpos($url, '?') !== FALSE) {
            $language['url'] = $url . '&' . $query;
          } else {
             $language['url'] = $url . '?' . $query;
          }
        }
      }
    }

    return $languages;
  }

  /**
   * Generate CiviCRM Rewrite Rules for Wordpress
   * @param array $collected_rewrites rules to be added to Wordpress
   * @param bool $flush_rewrite_rules whether to flush the rewrite rules
   */
  private function wp_add_rewrite_rules($collected_rewrites, $flush_rewrite_rules) {
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
   * Remove Language from URL based on WPML configuration
   *
   * @param $url passed URL to strip language from
   * @param object $sitepress WPML class
   * @param int $wpml_lang_config language setting in WPML
   *
   * @return $url base page url without language
   */
  private function wpml_remove_language_from_url($url, $sitepress, $wpml_lang_config) {
    $lang = $this->wpml_get_language_param_for_convert_url($sitepress);
    if ($lang) {
      if ($wpml_lang_config == 1) {
        $url = str_replace('/' . $lang . '/', '/', $url);
      } elseif ($wpml_lang_config == 3) {
        $url = str_replace('lang=' . $lang, '', $url);
      }
    }

    return $url;
  }

  /**
   * Get Language for WPML.
   * This code was taken from the WPML source, because it couldn't be referenced directly
   * @param object $sitepress reference to WPML class
   */
  private function wpml_get_language_param_for_convert_url($sitepress) {
    if (isset($_GET['lang'])) {
      return filter_var($_GET['lang'], FILTER_SANITIZE_STRING);
   }

   if (is_multisite() && isset($_POST['lang'])) {
     return filter_var($_POST['lang'], FILTER_SANITIZE_STRING);
   }

   if (is_multisite() && defined('SUBDOMAIN_INSTALL') && SUBDOMAIN_INSTALL) {
     return $sitepress->get_current_language();
   }

   return null;
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
