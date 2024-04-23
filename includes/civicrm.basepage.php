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
   * @var bool
   * Base Page parsed flag.
   * @since 4.6
   * @access public
   */
  public $basepage_parsed = FALSE;

  /**
   * @var string
   * Base Page title.
   * @since 4.6
   * @access public
   */
  public $basepage_title = '';

  /**
   * @var string
   * Base Page markup.
   * @since 4.6
   * @access public
   */
  public $basepage_markup = '';

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

    // Always check if the Base Page needs to be created.
    add_action('civicrm_instance_loaded', [$this, 'maybe_create_basepage']);

  }

  /**
   * Register hooks to handle CiviCRM in a WordPress Base Page context.
   *
   * @since 4.6
   */
  public function register_hooks() {

    // Kick out if not CiviCRM.
    if (!$this->civi->initialize()) {
      return;
    }

    // Cache CiviCRM Base Page markup.
    add_action('wp', [$this, 'basepage_handler'], 10, 1);

  }

  /**
   * Triggers the process whereby the WordPress Base Page is created.
   *
   * Sets a one-time-only option to flag that we need to create a Base Page -
   * it will not update the option once it has been set to another value nor
   * create a new option with the same name.
   *
   * As a result of doing this, we know that a Base Page needs to be created,
   * but the moment to do so is once CiviCRM has been successfully installed.
   *
   * @see self::maybe_create_basepage()
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
   * Auto-creates the WordPress Base Page if necessary.
   *
   * Changes the one-time-only option so that the Base Page can only be created
   * once. Thereafter, we're on our own until there's a 'delete_post' callback
   * to prevent the Base Page from being deleted.
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

    // Create the Base Page.
    add_action('wp_loaded', [$this, 'create_wp_basepage']);

    // Change option so the callback above never runs again.
    update_option('civicrm_activation_create_basepage', 'done');

  }

  /**
   * Creates the WordPress Base Page and saves the CiviCRM "wpBasePage" setting.
   *
   * @since 4.6
   * @since 5.6 Relocated from CiviCRM_For_WordPress to here.
   * @since 5.44 Returns success or failure.
   *
   * @return bool TRUE if successful, FALSE otherwise.
   */
  public function create_wp_basepage() {

    if (!$this->civi->initialize()) {
      return FALSE;
    }

    if (version_compare(CRM_Core_BAO_Domain::getDomain()->version, '4.7.0', '<')) {
      return FALSE;
    }

    // Bail if we already have a Base Page setting.
    $config = CRM_Core_Config::singleton();
    if (!empty($config->wpBasePage)) {
      return TRUE;
    }

    /**
     * Filter the default Base Page slug.
     *
     * @since 4.6
     *
     * @param str The default Base Page slug.
     */
    $slug = apply_filters('civicrm_basepage_slug', 'civicrm');

    // Get existing Page with that slug.
    $page = get_page_by_path($slug);

    // Get the ID if the Base Page already exists.
    $result = 0;
    if ($page instanceof WP_Post) {
      $result = $page->ID;
    }

    // Create the Base Page if it's missing.
    if ($result === 0) {
      $result = $this->create_basepage($slug);
    }

    // Save the Page slug as the setting if we have one.
    if ($result !== 0 && !is_wp_error($result)) {
      $post = get_post($result);
      civicrm_api3('Setting', 'create', [
        'wpBasePage' => $post->post_name,
      ]);
      return TRUE;
    }

    return FALSE;

  }

  /**
   * Create a WordPress page to act as the CiviCRM Base Page.
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

      /**
       * Allow plugins to override the switch to the main site.
       *
       * This filter changes the default behaviour on WordPress Multisite so
       * that the Base Page *is* created on every site on which CiviCRM is
       * activated. This is a more sensible and inclusive default, since the
       * absence of the Base Page on a sub-site often leads to confusion.
       *
       * To restore the previous functionality, return boolean TRUE.
       *
       * The previous functionality may be the desired behaviour when the
       * WordPress Multisite instance in question is one where sub-sites aren't
       * truly "separate" e.g. sites built on frameworks such as "Commons in
       * a Box" or "MultilingualPress".
       *
       * @since 5.44
       *
       * @param bool False by default prevents the switch to the main site.
       */
      $switch = apply_filters('civicrm/basepage/main_site_only', FALSE);

      if ($switch !== FALSE) {

        // Store this site.
        $original_site = get_current_blog_id();

        // Switch to main site.
        switch_to_blog(get_main_site_id());

      }

    }

    // Define Base Page.
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
     * Filter the default Base Page title.
     *
     * @since 4.6
     *
     * @param str The default Base Page title.
     */
    $page['post_title'] = apply_filters('civicrm_basepage_title', __('CiviCRM', 'civicrm'));

    // Default content.
    $content = __('Do not delete this page. Page content is generated by CiviCRM.', 'civicrm');

    /**
     * Filter the default Base Page content.
     *
     * @since 4.6
     *
     * @param str $content The default Base Page content.
     * @return str $content The modified Base Page content.
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
   * Build CiviCRM Base Page content.
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

    // Bail if this is a Favicon request.
    if (function_exists('is_favicon') && is_favicon()) {
      return;
    }

    // Check for the Base Page query conditions.
    $is_basepage_query = FALSE;
    if ($this->civi->civicrm_in_wordpress() && $this->civi->is_page_request()) {
      $is_basepage_query = TRUE;
    }

    // Do not proceed without them.
    if (!$is_basepage_query) {
      return;
    }

    // Kick out if not CiviCRM.
    if (!$this->civi->initialize()) {
      return;
    }

    /**
     * Fires before the Base Page is processed.
     *
     * @since 5.66
     */
    do_action('civicrm/basepage/handler/pre');

    // Set a "found" flag.
    $basepage_found = FALSE;

    // Check permission.
    $denied = TRUE;
    $argdata = $this->civi->get_request_args();
    if ($this->civi->users->check_permission($argdata['args'])) {
      $denied = FALSE;
    }

    // Get the Shortcode Mode setting.
    $shortcode_mode = $this->civi->admin->get_shortcode_mode();

    /*
     * Let's do the_loop.
     * This has the effect of bypassing the logic in:
     * @see https://github.com/civicrm/civicrm-wordpress/pull/36
     */
    if (have_posts()) {
      while (have_posts()) {

        the_post();

        global $post;

        /**
         * Allow "Base Page mode" to be forced.
         *
         * Return TRUE to force CiviCRM to render a Post/Page as if on the Base Page.
         *
         * @since 5.44
         *
         * @param bool By default "Base Page mode" should not be triggered.
         * @param WP_Post $post The current WordPress Post object.
         */
        $basepage_mode = (bool) apply_filters('civicrm_force_basepage_mode', FALSE, $post);

        // Determine if the current Post is the Base Page.
        $is_basepage = $this->is_match($post->ID);

        // Skip when this is not the Base Page or when "Base Page mode" is not forced or not in "legacy mode".
        if ($is_basepage || $basepage_mode || $shortcode_mode === 'legacy') {

          // Set context.
          $this->civi->civicrm_context_set('basepage');

          // Start buffering.
          ob_start();
          // Now, instead of echoing, Base Page output ends up in buffer.
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

          // Put CiviCRM into "Base Page mode".
          $basepage_found = TRUE;

        }

      }
    }

    // Reset loop.
    rewind_posts();

    /**
     * Fires after the Base Page may have been processed.
     *
     * @since 5.66
     *
     * @param bool $basepage_found TRUE if the CiviCRM Base Page was found, FALSE otherwise.
     */
    do_action('civicrm/basepage/handler/post', $basepage_found);

    // Bail if the Base Page has not been processed.
    if (!$basepage_found) {
      return;
    }

    // Hide the edit link.
    add_action('edit_post_link', [$this, 'clear_edit_post_link']);

    // Tweak admin bar.
    add_action('wp_before_admin_bar_render', [$this, 'clear_edit_post_menu_item']);

    // Add body classes for easier styling.
    add_filter('body_class', [$this, 'add_body_classes']);

    // In WordPress 4.6.0+, tell it URL params are part of canonical URL.
    add_filter('get_canonical_url', [$this, 'basepage_canonical_url'], 999);

    // Yoast SEO has separate way of establishing canonical URL.
    add_filter('wpseo_canonical', [$this, 'basepage_canonical_url'], 999);

    // And also for All in One SEO to handle canonical URL.
    add_filter('aioseop_canonical_url', [$this, 'basepage_canonical_url'], 999);

    // Override page title with high priority.
    add_filter('wp_title', [$this, 'wp_page_title'], 100, 3);
    add_filter('document_title_parts', [$this, 'wp_page_title_parts'], 100, 1);

    // Regardless of URL, load page template.
    add_filter('template_include', [$this, 'basepage_template'], 999);

    // Show content based on permission.
    if ($denied) {

      // Do not show content.
      add_filter('the_content', [$this->civi->users, 'get_permission_denied']);

    }
    else {

      // Add core resources for front end.
      add_action('wp', [$this->civi, 'front_end_page_load'], 100);

      // Include this content when Base Page is rendered.
      add_filter('the_content', [$this, 'basepage_render'], 21);

    }

    // Flag that we have parsed the Base Page.
    $this->basepage_parsed = TRUE;

    /**
     * Broadcast that the Base Page is parsed.
     *
     * @since 4.4
     */
    do_action('civicrm_basepage_parsed');

  }

  /**
   * Get CiviCRM Base Page title for <title> element.
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
    if ($separator_location === 'right') {
      $title = $this->basepage_title . " $separator " . get_bloginfo('name', 'display');
    }
    else {
      $title = get_bloginfo('name', 'display') . " $separator " . $this->basepage_title;
    }

    // Return modified title.
    return $title;

  }

  /**
   * Get CiviCRM Base Page title for <title> element.
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
   * Get CiviCRM Base Page content.
   *
   * Callback method for 'the_content' hook, always called from WordPress
   * front-end.
   *
   * @since 4.6
   *
   * @return str $basepage_markup The Base Page markup.
   */
  public function basepage_render() {

    // Hand back our Base Page markup.
    return $this->basepage_markup;

  }

  /**
   * Provide the canonical URL for a page accessed through a Base Page.
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

    // None of the following needs a nonce check.
    // phpcs:disable WordPress.Security.NonceVerification.Recommended

    // Retain old logic when not using clean URLs.
    if (!$config->cleanURL) {

      $civiwp = empty($_GET['civiwp']) ? '' : sanitize_text_field(wp_unslash($_GET['civiwp']));
      $q = empty($_GET['q']) ? '' : sanitize_text_field(wp_unslash($_GET['q']));

      /*
       * It would be better to specify which params are okay to accept as the
       * canonical URLs, but this will work for the time being.
       */
      if (empty($civiwp)
        || 'CiviCRM' !== $civiwp
        || empty($q)) {
        return $canonical;
      }
      $path = $q;
      unset($q, $_GET['q'], $civiwp, $_GET['civiwp']);
      $query = http_build_query($_GET);

    }
    else {

      $argdata = $this->civi->get_request_args();
      $path = $argdata['argString'];
      $query = http_build_query($_GET);

    }

    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    /*
     * We should, however, build the URL the way that CiviCRM expects it to be
     * (rather than through some other funny Base Page).
     */
    return CRM_Utils_System::url($path, $query);

  }

  /**
   * Get CiviCRM Base Page template.
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
    if ($template_name === $template) {
      $template_name = str_replace(trailingslashit(get_template_directory()), '', $template);
    }

    // Bail in the unlikely event that the template name has not been found.
    if ($template_name === $template) {
      return $template;
    }

    /**
     * Allow Base Page template to be overridden.
     *
     * In most cases, the logic will not progress beyond here. Shortcodes in
     * posts and pages will have a template set, so we leave them alone unless
     * specifically overridden by the filter.
     *
     * @since 4.6
     *
     * @param string $template_name The provided template name.
     */
    $basepage_template = apply_filters('civicrm_basepage_template', $template_name);

    // Find the Base Page template.
    $page_template = locate_template([$basepage_template]);

    // If not homepage and template is found.
    if (!is_front_page() && !empty($page_template)) {
      return $page_template;
    }

    /**
     * Override the template, but allow plugins to amend.
     *
     * This filter handles the scenario where no Base Page has been set, in
     * which case CiviCRM will try to load its content in the site's homepage.
     * Many themes, however, do not have a call to "the_content()" on the
     * homepage - it is often used as a gateway page to display widgets,
     * archives and so forth.
     *
     * Be aware that if the homepage is set to show latest posts, then this
     * template override will not have the desired effect. A Base Page *must*
     * be set if this is the case.
     *
     * @since 4.6
     *
     * @param string The template name (set to the default page template).
     */
    $home_template_name = apply_filters('civicrm_basepage_home_template', 'page.php');

    // Find the homepage template.
    $home_template = locate_template([$home_template_name]);

    // Use it if found.
    if (!empty($home_template)) {
      return $home_template;
    }

    // Fall back to provided template.
    return $template;

  }

  /**
   * Add classes to body element when on Base Page.
   *
   * This allows selectors to be written for particular CiviCRM "pages" despite
   * them all being rendered on the one WordPress Base Page.
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

  /**
   * Gets the current Base Page object.
   *
   * @since 5.44
   *
   * @return WP_Post|bool The Base Page object or FALSE on failure.
   */
  public function basepage_get() {

    // Bail if CiviCRM not bootstrapped.
    if (!$this->civi->initialize()) {
      return FALSE;
    }

    // Get config.
    $config = CRM_Core_Config::singleton();

    // Get Base Page object.
    $basepage = get_page_by_path($config->wpBasePage);
    if (is_null($basepage) || !($basepage instanceof WP_Post)) {
      return FALSE;
    }

    /**
     * Filters the CiviCRM Base Page object.
     *
     * @since 5.66
     *
     * @param WP_Post $basepage The CiviCRM Base Page object.
     */
    return apply_filters('civicrm/basepage', $basepage);

  }

  /**
   * Gets a URL that points to the CiviCRM Base Page.
   *
   * There can be situations where `CRM_Utils_System::url` does not return
   * a link to the Base Page, e.g. in a page template where the content
   * contains a Shortcode. This utility method will always return a URL
   * that points to the CiviCRM Base Page.
   *
   * @see https://lab.civicrm.org/dev/wordpress/-/issues/144
   *
   * @since 5.69
   *
   * @param string $path The path being linked to, such as "civicrm/add".
   * @param array|string $query A query string to append to the link, or an array of key-value pairs.
   * @param bool $absolute Whether to force the output to be an absolute link.
   * @param string $fragment A fragment identifier (named anchor) to append to the link.
   * @param bool $htmlize Whether to encode special html characters such as &.
   * @return string $link An HTML string containing a link to the given path.
   */
  public function url(
    $path = '',
    $query = '',
    $absolute = TRUE,
    $fragment = NULL,
    $htmlize = TRUE
  ) {

    // Return early if no CiviCRM.
    $link = '';
    if (!$this->civi->initialize()) {
      return $link;
    }

    // Add modifying callbacks prior to multi-lingual compat.
    add_filter('civicrm/basepage/match', [$this, 'ensure_match'], 9);
    add_filter('civicrm/core/url/base', [$this, 'ensure_url'], 9, 2);

    // Pass to CiviCRM to construct front-end URL.
    $link = CRM_Utils_System::url(
      $path,
      $query,
      TRUE,
      $fragment,
      $htmlize,
      TRUE,
      FALSE
    );

    // Remove callbacks.
    remove_filter('civicrm/basepage/match', [$this, 'ensure_match'], 9);
    remove_filter('civicrm/core/url/base', [$this, 'ensure_url'], 9);

    return $link;

  }

  /**
   * Callback to ensure CiviCRM returns a Base Page URL.
   *
   * @since 5.69
   *
   * @return bool
   */
  public function ensure_match() {
    return TRUE;
  }

  /**
   * Callback to ensure CiviCRM builds a Base Page URL.
   *
   * @since 5.69
   *
   * @param str $url The "base" URL as built by CiviCRM.
   * @param bool $admin_request True if building an admin URL, false otherwise.
   * @return str $url The Base Page URL.
   */
  public function ensure_url($url, $admin_request) {

    // Skip when not defined.
    if (empty($url) || $admin_request) {
      return $url;
    }

    // Return the Base Page URL.
    return $this->url_get();

  }

  /**
   * Gets the current Base Page ID.
   *
   * @since 5.66
   *
   * @return int|bool The Base Page ID or FALSE on failure.
   */
  public function id_get() {

    // Get the Base Page object.
    $basepage = $this->basepage_get();
    if (!($basepage instanceof WP_Post)) {
      return FALSE;
    }

    return $basepage->ID;

  }

  /**
   * Gets the current Base Page URL.
   *
   * @since 5.66
   *
   * @return str The Base Page URL or empty on failure.
   */
  public function url_get() {

    // Get the Base Page object.
    $basepage = $this->basepage_get();
    if (!($basepage instanceof WP_Post)) {
      return '';
    }

    return get_permalink($basepage->ID);

  }

  /**
   * Gets the Base Page title.
   *
   * @since 5.66
   *
   * @return string $basepage_title The title of the CiviCRM entity.
   */
  public function title_get() {
    return $this->basepage_title;
  }

  /**
   * Checks a Post ID against the Base Page ID.
   *
   * @since 5.66
   *
   * @param int $post_id The Post ID to check.
   * @return bool TRUE if the Post ID matches the Base Page ID, or FALSE otherwise.
   */
  public function is_match($post_id) {

    // Get the Base Page ID.
    $basepage_id = $this->id_get();
    if ($basepage_id === FALSE) {
      return FALSE;
    }

    // Determine if the given Post is the Base Page.
    $is_basepage = $basepage_id === $post_id ? TRUE : FALSE;

    /**
     * Filters the CiviCRM Base Page match.
     *
     * @since 5.66
     *
     * @param bool $is_basepage TRUE if the Post ID matches the Base Page ID, FALSE otherwise.
     * @param int $post_id The WordPress Post ID to check.
     */
    return apply_filters('civicrm/basepage/match', $is_basepage, $post_id);

  }

  /**
   * Gets the current Base Page setting.
   *
   * @since 5.66
   *
   * @return string|bool $setting The Base Page setting, or FALSE on failure.
   */
  public function setting_get() {

    // Bail if CiviCRM not bootstrapped.
    if (!$this->civi->initialize()) {
      return FALSE;
    }

    // Get the setting.
    $setting = civicrm_api3('Setting', 'getvalue', [
      'name' => 'wpBasePage',
      'group' => 'CiviCRM Preferences',
    ]);

    return $setting;

  }

  /**
   * Sets the current Base Page setting.
   *
   * @since 5.66
   *
   * @param string $slug The Base Page setting.
   */
  public function setting_set($slug) {

    // Bail if CiviCRM not bootstrapped.
    if (!$this->civi->initialize()) {
      return;
    }

    // Set the setting.
    civicrm_api3('Setting', 'create', [
      'wpBasePage' => $slug,
    ]);

  }

}
