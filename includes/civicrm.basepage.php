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
 * Define CiviCRM_For_WordPress_Basepage Class.
 *
 * @since 4.6
 */
class CiviCRM_For_WordPress_Basepage {

  /**
   * @var object
   * Plugin object reference.
   * @since 4.6
   * @access public
   */
  public $civi;

  /**
   * Instance constructor.
   *
   * @since 4.6
   */
  public function __construct() {

    // Store reference to CiviCRM plugin object.
    $this->civi = civi_wp();

    // Always listen for activation action.
    add_action('civicrm_activation', [$this, 'activate']);

    // Always listen for deactivation action.
    add_action('civicrm_deactivation', [$this, 'deactivate']);

    // Always check if the basepage needs to be created.
    add_action('civicrm_instance_loaded', [$this, 'maybe_create_basepage']);

  }

  /**
   * Register hooks to handle CiviCRM in a WordPress wpBasePage context.
   *
   * @since 4.6
   */
  public function register_hooks() {

    // Kick out if not CiviCRM.
    if (!$this->civi->initialize()) {
      return;
    }

    // In WordPress 4.6.0+, tell it URL params are part of canonical URL.
    add_filter('get_canonical_url', [$this, 'basepage_canonical_url'], 999);

    // Yoast SEO has separate way of establishing canonical URL.
    add_filter('wpseo_canonical', [$this, 'basepage_canonical_url'], 999);

    // And also for All in One SEO to handle canonical URL.
    add_filter('aioseop_canonical_url', [$this, 'basepage_canonical_url'], 999);

    // Regardless of URL, load page template.
    add_filter('template_include', [$this, 'basepage_template'], 999);

    // Check permission.
    $argdata = $this->civi->get_request_args();
    if (!$this->civi->users->check_permission($argdata['args'])) {
      add_filter('the_content', [$this->civi->users, 'get_permission_denied']);
      return;
    }

    // Cache CiviCRM base page markup.
    add_action('wp', [$this, 'basepage_handler'], 10, 1);

  }

  /**
   * Trigger the process whereby the WordPress basepage is created.
   *
   * Sets a one-time-only option to flag that we need to create a basepage -
   * it will not update the option once it has been set to another value nor
   * create a new option with the same name.
   *
   * As a result of doing this, we know that a basepage needs to be created, but
   * the moment to do so is once CiviCRM has been successfully installed.
   *
   * @see do_basepage_creation()
   *
   * @since 5.6
   */
  public function activate() {

    // Save option.
    add_option('civicrm_activation_create_basepage', 'true');

  }

  /**
   * Plugin deactivation.
   *
   * @since 5.6
   */
  public function deactivate() {

    // Delete option.
    delete_option('civicrm_activation_create_basepage');

  }

  /**
   * Register the hook to create the WordPress basepage, if necessary.
   *
   * Changes the one-time-only option so that the basepage can only be created
   * once. Thereafter, we're on our own until there's a 'delete_post' callback
   * to prevent the basepage from being deleted.
   *
   * @since 5.6
   */
  public function maybe_create_basepage() {

    // Bail if CiviCRM not installed.
    if (!CIVICRM_INSTALLED) {
      return;
    }

    // Bail if not installing.
    if (get_option('civicrm_activation_create_basepage') !== 'true') {
      return;
    }

    // Bail if not WordPress admin.
    if (!is_admin()) {
      return;
    }

    // Create basepage.
    add_action('wp_loaded', [$this, 'create_wp_basepage']);

    // Change option so the callback above never runs again.
    update_option('civicrm_activation_create_basepage', 'done');

  }

  /**
   * Create WordPress basepage and save setting.
   *
   * @since 4.6
   * @since 5.6 Relocated from CiviCRM_For_WordPress to here.
   */
  public function create_wp_basepage() {

    if (!$this->civi->initialize()) {
      return;
    }

    $config = CRM_Core_Config::singleton();

    // Bail if we already have a basepage setting.
    if (!empty($config->wpBasePage)) {
      return;
    }

    /**
     * Filter the default page slug.
     *
     * @since 4.6
     *
     * @param str The default basepage slug.
     * @return str The modified basepage slug.
     */
    $slug = apply_filters('civicrm_basepage_slug', 'civicrm');

    // Get existing page with that slug.
    $page = get_page_by_path($slug);

    // Does it exist?
    if ($page) {

      // We already have a basepage.
      $result = $page->ID;

    }
    else {

      // Create the basepage.
      $result = $this->create_basepage($slug);

    }

    // Were we successful?
    if ($result !== 0 && !is_wp_error($result)) {

      // Get the post object.
      $post = get_post($result);

      $params = [
        'version' => 3,
        'wpBasePage' => $post->post_name,
      ];

      // Save the setting.
      civicrm_api3('setting', 'create', $params);

    }

  }

  /**
   * Create a WordPress page to act as the CiviCRM base page.
   *
   * @since 4.6
   * @since 5.6 Relocated from CiviCRM_For_WordPress to here.
   *
   * @param string $slug The unique slug for the page - same as wpBasePage setting.
   * @return int|WP_Error The page ID on success. The value 0 or WP_Error on failure.
   */
  private function create_basepage($slug) {

    // If multisite, switch to main site.
    if (is_multisite() && !is_main_site()) {

      // Store this site.
      $original_site = get_current_blog_id();

      // Switch.
      global $current_site;
      switch_to_blog($current_site->blog_id);

    }

    // Define basepage.
    $page = [
      'post_status' => 'publish',
      'post_type' => 'page',
      'post_parent' => 0,
      'comment_status' => 'closed',
      'ping_status' => 'closed',
      // Quick fix for Windows.
      'to_ping' => '',
      // Quick fix for Windows.
      'pinged' => '',
      // Quick fix for Windows.
      'post_content_filtered' => '',
      // Quick fix for Windows.
      'post_excerpt' => '',
      'menu_order' => 0,
      'post_name' => $slug,
    ];

    /**
     * Filter the default page title.
     *
     * @since 4.6
     *
     * @param str The default base page title.
     * @return str The modified base page title.
     */
    $page['post_title'] = apply_filters('civicrm_basepage_title', __('CiviCRM', 'civicrm'));

    // Default content.
    $content = __('Do not delete this page. Page content is generated by CiviCRM.', 'civicrm');

    /**
     * Filter the default page content.
     *
     * @since 4.6
     *
     * @param str $content The default base page content.
     * @return str $content The modified base page content.
     */
    $page['post_content'] = apply_filters('civicrm_basepage_content', $content);

    // Insert the post into the database.
    $page_id = wp_insert_post($page);

    // Switch back if we've switched.
    if (isset($original_site)) {
      restore_current_blog();
    }

    // Make sure Rewrite Rules are flushed.
    delete_option('civicrm_rules_flushed');

    return $page_id;

  }

  /**
   * Build CiviCRM base page content.
   *
   * Callback method for 'wp' hook, always called from WordPress front-end.
   *
   * @since 4.6
   *
   * @param object $wp The WordPress object, present but not used.
   */
  public function basepage_handler($wp) {

    /*
     * At this point, all conditional tags are available.
     * @see https://codex.wordpress.org/Conditional_Tags
     */

    // Bail if this is a 404.
    if (is_404()) {
      return;
    }

    // Kick out if not CiviCRM.
    if (!$this->civi->initialize()) {
      return;
    }

    // Add core resources for front end.
    add_action('wp', [$this->civi, 'front_end_page_load'], 100);

    // CMW: why do we need this? Nothing that follows uses it.
    require_once ABSPATH . WPINC . '/pluggable.php';

    /*
     * Let's do the_loop.
     * This has the effect of bypassing the logic in:
     * @see https://github.com/civicrm/civicrm-wordpress/pull/36
     */
    if (have_posts()) {
      while (have_posts()) {

        the_post();

        global $post;

        // Start buffering.
        ob_start();
        // Now, instead of echoing, base page output ends up in buffer.
        $this->civi->invoke();
        // Save the output and flush the buffer.
        $this->basepage_markup = ob_get_clean();

        /*
         * The following logic is in response to some of the complexities of how
         * titles are handled in WordPress, particularly when there are SEO
         * plugins present that modify the title for Open Graph purposes. There
         * have also been issues with the default WordPress themes, which modify
         * the title using the 'wp_title' filter.
         *
         * First, we try and set the title of the page object, which will work
         * if the loop is not run subsequently and if there are no additional
         * filters on the title.
         *
         * Second, we store the CiviCRM title so that we can construct the base
         * page title if other plugins modify it.
         */

        // Override post title.
        global $civicrm_wp_title;
        $post->post_title = $civicrm_wp_title;

        // Because the above seems unreliable, store title for later use.
        $this->basepage_title = $civicrm_wp_title;

        // Disallow commenting.
        $post->comment_status = 'closed';

      }
    }

    // Reset loop.
    rewind_posts();

    // Override page title with high priority.
    add_filter('wp_title', [$this, 'wp_page_title'], 100, 3);
    add_filter('document_title_parts', [$this, 'wp_page_title_parts'], 100, 1);

    // Add compatibility with Yoast SEO plugin's Open Graph title.
    add_filter('wpseo_opengraph_title', [$this, 'wpseo_page_title'], 100, 1);

    // Don't let the Yoast SEO plugin parse the basepage title.
    if (class_exists('WPSEO_Frontend')) {
      $frontend = WPSEO_Frontend::get_instance();
      remove_filter('pre_get_document_title', [$frontend, 'title'], 15);
    }

    // Include this content when base page is rendered.
    add_filter('the_content', [$this, 'basepage_render']);

    // Hide the edit link.
    add_action('edit_post_link', [$this, 'clear_edit_post_link']);

    // Tweak admin bar.
    add_action('wp_before_admin_bar_render', [$this, 'clear_edit_post_menu_item']);

    // Add body classes for easier styling.
    add_filter('body_class', [$this, 'add_body_classes']);

    // Flag that we have parsed the base page.
    $this->basepage_parsed = TRUE;

    /**
     * Broadcast that the base page is parsed.
     *
     * @since 4.4
     */
    do_action('civicrm_basepage_parsed');

  }

  /**
   * Get CiviCRM basepage title for <title> element.
   *
   * Callback method for 'wp_title' hook, called at the end of function wp_title.
   *
   * @since 4.6
   *
   * @param string $title Title that might have already been set.
   * @param string $separator Separator determined in theme (but defaults to WordPress default).
   * @param string $separator_location Whether the separator should be left or right.
   */
  public function wp_page_title($title, $separator = '&raquo;', $separator_location = '') {

    // If feed, return just the title.
    if (is_feed()) {
      return $this->basepage_title;
    }

    // Set default separator location, if it isn't defined.
    if ('' === trim($separator_location)) {
      $separator_location = (is_rtl()) ? 'left' : 'right';
    }

    // If we have WP SEO present, use its separator.
    if (class_exists('WPSEO_Options')) {
      $separator_code = WPSEO_Options::get_default('wpseo_titles', 'separator');
      $separator_array = WPSEO_Option_Titles::get_instance()->get_separator_options();
      if (array_key_exists($separator_code, $separator_array)) {
        $separator = $separator_array[$separator_code];
      }
    }

    // Construct title depending on separator location.
    if ($separator_location == 'right') {
      $title = $this->basepage_title . " $separator " . get_bloginfo('name', 'display');
    }
    else {
      $title = get_bloginfo('name', 'display') . " $separator " . $this->basepage_title;
    }

    // Return modified title.
    return $title;

  }

  /**
   * Get CiviCRM basepage title for <title> element.
   *
   * Callback method for 'document_title_parts' hook. This filter was introduced
   * in WordPress 3.8 but it depends on whether the theme has implemented that
   * method for generating the title or not.
   *
   * @since 5.14
   *
   * @param array $parts The existing title parts.
   * @return array $parts The modified title parts.
   */
  public function wp_page_title_parts($parts) {

    // Override with CiviCRM's title.
    if (isset($parts['title'])) {
      $parts['title'] = $this->basepage_title;
    }

    // Return modified title parts.
    return $parts;

  }

  /**
   * Get CiviCRM base page title for Open Graph elements.
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

    // Hand back our base page title.
    return $this->basepage_title;

  }

  /**
   * Get CiviCRM base page content.
   *
   * Callback method for 'the_content' hook, always called from WordPress
   * front-end.
   *
   * @since 4.6
   *
   * @return str $basepage_markup The base page markup.
   */
  public function basepage_render() {

    // Hand back our base page markup.
    return $this->basepage_markup;

  }

  /**
   * Provide the canonical URL for a page accessed through a basepage.
   *
   * WordPress will default to saying the canonical URL is the URL of the base
   * page itself, but we need to indicate that in this case, the whole thing
   * matters.
   *
   * Note: this function is used for three different but similar hooks:
   *  - `get_canonical_url` (WordPress 4.6.0+)
   *  - `aioseop_canonical_url` (All in One SEO)
   *  - `wpseo_canonical` (Yoast WordPress SEO)
   *
   * The native WordPress one passes the page object, while the other two do
   * not.  We don't actually need the page object, so the argument is omitted
   * here.
   *
   * @since 4.6
   *
   * @param string $canonical The canonical URL.
   * @return string The complete URL to the page as it should be accessed.
   */
  public function basepage_canonical_url($canonical) {

    // Access CiviCRM config object.
    $config = CRM_Core_Config::singleton();

    // Retain old logic when not using clean URLs.
    if (!$config->cleanURL) {

      /*
       * It would be better to specify which params are okay to accept as the
       * canonical URLs, but this will work for the time being.
       */
      if (empty($_GET['civiwp'])
        || empty($_GET['q'])
        || 'CiviCRM' !== $_GET['civiwp']) {
        return $canonical;
      }
      $path = $_GET['q'];
      unset($_GET['q']);
      unset($_GET['civiwp']);
      $query = http_build_query($_GET);

    }
    else {

      $argdata = $this->civi->get_request_args();
      $path = $argdata['argString'];
      $query = http_build_query($_GET);

    }

    /*
     * We should, however, build the URL the way that CiviCRM expects it to be
     * (rather than through some other funny base page).
     */
    return CRM_Utils_System::url($path, $query);

  }

  /**
   * Get CiviCRM base page template.
   *
   * Callback method for 'template_include' hook, always called from WordPress
   * front-end.
   *
   * @since 4.6
   *
   * @param string $template The path to the existing template.
   * @return string $template The modified path to the desired template.
   */
  public function basepage_template($template) {

    // Get template path relative to the theme's root directory.
    $template_name = str_replace(trailingslashit(get_stylesheet_directory()), '', $template);

    // If the above fails, try parent theme.
    if ($template_name == $template) {
      $template_name = str_replace(trailingslashit(get_template_directory()), '', $template);
    }

    // Bail in the unlikely event that the template name has not been found.
    if ($template_name == $template) {
      return $template;
    }

    /**
     * Allow base page template to be overridden.
     *
     * In most cases, the logic will not progress beyond here. Shortcodes in
     * posts and pages will have a template set, so we leave them alone unless
     * specifically overridden by the filter.
     *
     * @since 4.6
     *
     * @param string $template_name The provided template name.
     * @return string The overridden template name.
     */
    $basepage_template = apply_filters('civicrm_basepage_template', $template_name);

    // Find the base page template.
    $page_template = locate_template([$basepage_template]);

    // If not homepage and template is found.
    if ('' != $page_template && !is_front_page()) {
      return $page_template;
    }

    /**
     * Override the template, but allow plugins to amend.
     *
     * This filter handles the scenario where no basepage has been set, in
     * which case CiviCRM will try to load its content in the site's homepage.
     * Many themes, however, do not have a call to "the_content()" on the
     * homepage - it is often used as a gateway page to display widgets,
     * archives and so forth.
     *
     * Be aware that if the homepage is set to show latest posts, then this
     * template override will not have the desired effect. A basepage *must*
     * be set if this is the case.
     *
     * @since 4.6
     *
     * @param string The template name (set to the default page template).
     * @return string The overridden template name.
     */
    $home_template_name = apply_filters('civicrm_basepage_home_template', 'page.php');

    // Find the homepage template.
    $home_template = locate_template([$home_template_name]);

    // Use it if found.
    if ('' != $home_template) {
      return $home_template;
    }

    // Fall back to provided template.
    return $template;

  }

  /**
   * Add classes to body element when on basepage.
   *
   * This allows selectors to be written for particular CiviCRM "pages" despite
   * them all being rendered on the one WordPress basepage.
   *
   * @since 4.7.18
   *
   * @param array $classes The existing body classes.
   * @return array $classes The modified body classes.
   */
  public function add_body_classes($classes) {

    $args = $this->civi->get_request_args();

    // Bail if we don't have any.
    if (is_null($args['argString'])) {
      return $classes;
    }

    // Check for top level - it can be assumed this always 'civicrm'.
    if (isset($args['args'][0]) && !empty($args['args'][0])) {
      $classes[] = $args['args'][0];
    }

    // Check for second level - the component.
    if (isset($args['args'][1]) && !empty($args['args'][1])) {
      $classes[] = $args['args'][0] . '-' . $args['args'][1];
    }

    // Check for third level - the component's configuration.
    if (isset($args['args'][2]) && !empty($args['args'][2])) {
      $classes[] = $args['args'][0] . '-' . $args['args'][1] . '-' . $args['args'][2];
    }

    // Check for fourth level - because well, why not?
    if (isset($args['args'][3]) && !empty($args['args'][3])) {
      $classes[] = $args['args'][0] . '-' . $args['args'][1] . '-' . $args['args'][2] . '-' . $args['args'][3];
    }

    return $classes;

  }

  /**
   * Remove edit link from page content.
   *
   * Callback from 'edit_post_link' hook.
   *
   * @since 4.6
   * @since 5.33 Moved to this class.
   *
   * @return string Always empty.
   */
  public function clear_edit_post_link() {
    return '';
  }

  /**
   * Remove edit link in WordPress Admin Bar.
   *
   * Callback from 'wp_before_admin_bar_render' hook.
   *
   * @since 4.6
   */
  public function clear_edit_post_menu_item() {

    // Access object.
    global $wp_admin_bar;

    // Bail if in admin.
    if (is_admin()) {
      return;
    }

    // Remove the menu item from front end.
    $wp_admin_bar->remove_menu('edit');

  }

}
