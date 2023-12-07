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
 * Define CiviCRM_For_WordPress_Shortcodes Class.
 *
 * @since 4.6
 */
class CiviCRM_For_WordPress_Shortcodes {

  /**
   * @var object
   * Plugin object reference.
   * @since 4.6
   * @access public
   */
  public $civi;

  /**
   * @var array
   * The stored Shortcodes.
   * @since 4.6
   * @access public
   */
  public $shortcodes = [];

  /**
   * @var array
   * The array of rendered Shortcode markup.
   * @since 4.6
   * @access public
   */
  public $shortcode_markup = [];

  /**
   * @var array
   * Count multiple passes of do_shortcode in a post.
   * @since 4.6
   * @access public
   */
  public $shortcode_in_post = [];

  /**
   * Instance constructor.
   *
   * @since 4.6
   */
  public function __construct() {

    // Store reference to CiviCRM plugin object.
    $this->civi = civi_wp();

  }

  /**
   * Register hooks to handle the presence of Shortcodes in content.
   *
   * @since 4.6
   */
  public function register_hooks() {

    // Register the CiviCRM Shortcode.
    add_shortcode('civicrm', [$this, 'render_single']);

    // Pre-render CiviCRM content when one or more Shortcodes are detected.
    add_action('wp', [$this, 'prerender'], 20, 1);

  }

  /**
   * Determine if a CiviCRM Shortcode is present in any of the posts about to be displayed.
   *
   * Callback method for 'wp' hook, always called from WordPress front-end.
   *
   * @since 4.6
   *
   * @param object $wp The WordPress object, present but not used.
   */
  public function prerender($wp) {

    /*
     * At this point, all conditional tags are available
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

    /**
     * Filter the Shortcode Components that do not invoke CiviCRM.
     *
     * Shortcodes for Components such as Afform do load CiviCRM resources but do
     * not have a CiviCRM path and are not rendered via the `invoke()` method.
     * We can allow multiple instances of these Shortcodes in a single page load.
     *
     * @since 5.56
     *
     * @param array $components The array of Components that do not invoke CiviCRM.
     */
    $components = apply_filters('civicrm_no_invoke_shortcode_components', ['afform']);

    // Track the Shortcodes for Components that do and do not invoke CiviCRM.
    $no_invoke_shortcodes = [];
    $total_no_invoke_shortcodes = 0;
    $invoke_shortcodes = [];
    $total_invoke_shortcodes = 0;

    // Track the total number of CiviCRM Shortcodes.
    $total_shortcodes = 0;

    /*
     * Let's loop through the results.
     * This also has the effect of bypassing the logic in:
     * @see https://github.com/civicrm/civicrm-wordpress/pull/36
     */
    if (have_posts()) {
      while (have_posts()) {

        the_post();

        global $post;

        // Check for existence of Shortcode in content.
        if (has_shortcode($post->post_content, 'civicrm')) {

          // Get CiviCRM Shortcodes in this post.
          $shortcodes_array = $this->get_for_post($post->post_content);

          // Sanity check.
          if (!empty($shortcodes_array)) {

            // Add it to our collection of Shortcodes.
            $this->shortcodes[$post->ID] = $shortcodes_array;

            // Bump Shortcode counter.
            $total_shortcodes += count($this->shortcodes[$post->ID]);

            // Check for Components that do not invoke CiviCRM.
            foreach ($shortcodes_array as $key => $shortcode) {
              $atts = $this->get_atts($shortcode);
              if (!empty($atts['component']) && in_array($atts['component'], $components)) {
                $no_invoke_shortcodes[$post->ID][$key] = $shortcode;
                $total_no_invoke_shortcodes++;
              }
              else {
                $invoke_shortcodes[$post->ID][$key] = $shortcode;
                $total_invoke_shortcodes++;
              }
            }

          }

        }

      }
    }

    // Reset loop.
    rewind_posts();

    // Bail if there are no Shortcodes.
    if ($total_shortcodes === 0) {
      return;
    }

    // Set context.
    $this->civi->civicrm_context_set('shortcode');

    // We need CiviCRM initialised prior to parsing Shortcodes.
    if (!$this->civi->initialize()) {
      return;
    }

    if ($total_invoke_shortcodes === 1) {

      /*
       * Since we have only one Shortcode, run the_loop again.
       * The DB query has already been done, so this has no significant impact.
       */
      if (have_posts()) {
        while (have_posts()) {

          the_post();

          global $post;

          // Is this the post?
          if (!array_key_exists($post->ID, $invoke_shortcodes)) {
            continue;
          }

          // The Shortcode must be the item in the Shortcodes array.
          $shortcode = reset($invoke_shortcodes[$post->ID]);
          $key = key($invoke_shortcodes[$post->ID]);

          // Check to see if a Shortcode component has been repeated?
          $atts = $this->get_atts($shortcode);

          // Test for hijacking.
          if (isset($atts['hijack']) && 1 === (int) $atts['hijack']) {
            add_filter('civicrm_context', [$this, 'get_context']);
          }

          // Store corresponding markup.
          $this->shortcode_markup[$post->ID][$key] = do_shortcode($shortcode);

          // Test for hijacking.
          if (isset($atts['hijack']) && 1 === (int) $atts['hijack']) {

            // Ditch the filter.
            remove_filter('civicrm_context', [$this, 'get_context']);

            // Set title.
            global $civicrm_wp_title;
            $post->post_title = $civicrm_wp_title;

            // Override page title.
            add_filter('single_post_title', [$this, 'single_page_title'], 50, 2);

            // Overwrite content.
            add_filter('the_content', [$this, 'get_content']);

          }

        }
      }

      // Reset loop.
      rewind_posts();

    }

    // How should we handle multiple non-invoking Shortcodes?
    if ($total_no_invoke_shortcodes > 0) {

      // Let's render Shortcodes that do not invoke CiviCRM.
      foreach ($no_invoke_shortcodes as $post_id => $shortcode_array) {

        // Set flag if there are multiple Shortcodes in this post.
        $multiple = (count($shortcode_array) > 1) ? 1 : 0;

        foreach ($shortcode_array as $key => $shortcode) {
          // Mimic invoke in multiple Shortcode context.
          $this->shortcode_markup[$post_id][$key] = $this->render_multiple($post_id, $shortcode, $multiple);
        }

      }

    }

    // How should we handle multiple invoking Shortcodes?
    if ($total_invoke_shortcodes > 1) {

      // Let's add dummy markup for Shortcodes that invoke CiviCRM.
      foreach ($invoke_shortcodes as $post_id => $shortcode_array) {

        // Set flag if there are multiple Shortcodes in this post.
        $multiple = (count($shortcode_array) > 1) ? 1 : 0;

        foreach ($shortcode_array as $key => $shortcode) {
          // Mimic invoke in multiple Shortcode context.
          $this->shortcode_markup[$post_id][$key] = $this->render_multiple($post_id, $shortcode, $multiple);
        }

      }

    }

    // A single Shortcode and any pathless Shortcodes need CiviCRM resources.
    if ($total_no_invoke_shortcodes > 0 || $total_shortcodes === 1) {
      // Add CiviCRM resources for front end.
      add_action('wp', [$this->civi, 'front_end_page_load'], 100);
    }

    // Multiple invoking Shortcodes need the CiviCRM CSS file.
    if ($total_invoke_shortcodes > 1) {
      // Add CSS resources for front end.
      add_action('wp_enqueue_scripts', [$this->civi, 'front_end_css_load'], 100);
    }

    // Flag that we have parsed Shortcodes.
    $this->shortcodes_parsed = TRUE;

    /**
     * Broadcast that Shortcodes have been parsed.
     *
     * @since 4.6
     */
    do_action('civicrm_shortcodes_parsed');

  }

  /**
   * Handles CiviCRM-defined Shortcodes.
   *
   * @since 4.6
   *
   * @param array $atts Shortcode attributes array.
   * @return string HTML for output.
   */
  public function render_single($atts) {

    // Do not parse Shortcodes in REST context for PUT, POST and DELETE methods.
    // Nonce is not necessary here.
    // phpcs:ignore WordPress.Security.NonceVerification.Missing
    if (defined('REST_REQUEST') && REST_REQUEST && (isset($_PUT) || isset($_POST) || isset($_DELETE))) {
      // Return the original Shortcode.
      $shortcode = '[civicrm';
      foreach ($atts as $att => $val) {
        $shortcode .= ' ' . $att . '="' . $val . '"';
      }
      $shortcode .= ']';
      return $shortcode;
    }

    // Check if we've already parsed this Shortcode.
    global $post;
    if (is_object($post)) {
      if (!empty($this->shortcode_markup)) {
        if (isset($this->shortcode_markup[$post->ID])) {

          // Set counter flag.
          if (!isset($this->shortcode_in_post[$post->ID])) {
            $this->shortcode_in_post[$post->ID] = 0;
          }
          else {
            $this->shortcode_in_post[$post->ID]++;
          }

          // This Shortcode must have been rendered.
          return $this->shortcode_markup[$post->ID][$this->shortcode_in_post[$post->ID]];

        }
      }
    }

    // Preprocess Shortcode attributes.
    $args = $this->preprocess_atts($atts);

    // Check for pathless Shortcode.
    if (empty($args['q'])) {

      $content = '<p>' . __('This Shortcode could not be handled. It could be malformed or used incorrectly.', 'civicrm') . '</p>';

      /**
       * Get the markup for "pathless" Shortcodes.
       *
       * This filter allows plugins or CiviCRM Extensions to modify the markup used
       * to display a Shortcode that has no CiviCRM route/path. This may be:
       *
       * * Accidental due to an improperly constructed Shortcode or
       * * Deliberate because a component may not require a route/path
       *
       * Used internally by:
       *
       * - afform_shortcode_content()
       *
       * @since 5.37
       *
       * @param str $content The default markup for an improperly constructed Shortcode.
       * @param array $atts The Shortcode attributes array.
       * @param array $args The Shortcode arguments array.
       * @param str Context flag - value is either 'single' or 'multiple'.
       */
      return apply_filters('civicrm_shortcode_get_markup', $content, $atts, $args, 'single');

    }

    // If there are *actual* CiviCRM query vars, let them take priority.
    if (!$this->civi->civicrm_in_wordpress()) {

      // Get the Shortcode Mode setting.
      $shortcode_mode = $this->civi->admin->get_shortcode_mode();

      /** This filter is documented in includes/civicrm.basepage.php */
      $basepage_mode = (bool) apply_filters('civicrm_force_basepage_mode', FALSE, $post);

      // Skip unless in "legacy mode" or "Base Page mode" is forced.
      if ($shortcode_mode !== 'legacy' || !$basepage_mode) {

        // invoke() requires environment variables to be set.
        foreach ($args as $key => $value) {
          if ($value !== NULL) {
            set_query_var($key, $value);
            $_REQUEST[$key] = $_GET[$key] = $value;
          }
        }

      }

    }

    if (!$this->civi->initialize()) {
      return '';
    }

    // Check permission.
    $argdata = $this->civi->get_request_args();
    if (!$this->civi->users->check_permission($argdata['args'])) {
      return $this->civi->users->get_permission_denied();
    }

    // Start buffering.
    ob_start();
    // Now, instead of echoing, Shortcode output ends up in buffer.
    $this->civi->invoke();
    // Save the output and flush the buffer.
    $content = ob_get_clean();

    return $content;

  }

  /**
   * Return a generic display for a Shortcode instead of a CiviCRM invocation.
   *
   * @since 4.6
   *
   * @param int $post_id The containing WordPress post ID.
   * @param string $shortcode The Shortcode being parsed.
   * @param bool $multiple Boolean flag, TRUE if post has multiple Shortcodes, FALSE otherwise.
   * @return string $markup Generic markup for multiple instances.
   */
  private function render_multiple($post_id = FALSE, $shortcode = FALSE, $multiple = 0) {

    // Get attributes.
    $atts = $this->get_atts($shortcode);

    // Pre-process Shortcode and retrieve args.
    $args = $this->preprocess_atts($atts);

    // Get pathless markup from filter callback.
    if (empty($args['q'])) {
      $markup = '';
      /** This filter is documented in includes/civicrm-shortcodes.php */
      return apply_filters('civicrm_shortcode_get_markup', $markup, $atts, $args, 'multiple');
    }

    // Get data for this Shortcode.
    $data = $this->get_data($atts, $args);

    // Sanity check.
    if ($data === FALSE) {
      return '';
    }

    // Did we get a title?
    $title = __('Content via CiviCRM', 'civicrm');
    if (!empty($data['title'])) {
      $title = $data['title'];
    }

    // Init title flag.
    $show_title = TRUE;

    // Default link.
    $link = get_permalink($post_id);

    // Default to no class.
    $class = '';

    // Access CiviCRM config object.
    $config = CRM_Core_Config::singleton();

    // Do we have multiple Shortcodes?
    if ($multiple !== 0) {

      $links = [];
      foreach ($args as $var => $arg) {
        if (!empty($arg) && $var !== 'q') {
          $links[] = $var . '=' . $arg;
        }
      }
      $query = implode('&', $links);

      // Params are: $absolute, $frontend, $forceBackend.
      $base_url = CRM_Utils_System::getBaseUrl(TRUE, FALSE, FALSE);

      // Init query parts.
      $queryParts = [];

      // When not using Clean URLs.
      if (!$config->cleanURL) {

        // Construct query parts.
        $queryParts[] = 'civiwp=CiviCRM';
        if (isset($args['q'])) {
          $queryParts[] = 'q=' . $args['q'];
        }
        if (isset($query)) {
          $queryParts[] = $query;
        }

        // Construct link.
        $link = trailingslashit($base_url) . '?' . implode('&', $queryParts);

      }
      else {

        // Clean URLs.
        if (isset($args['q'])) {
          $base_url = trailingslashit($base_url) . str_replace('civicrm/', '', $args['q']) . '/';
        }
        if (isset($query)) {
          $queryParts[] = $query;
        }
        $link = $base_url . '?' . implode('&', $queryParts);

      }

      // Add a class for styling purposes.
      $class = ' civicrm-shortcode-multiple';

    }

    // Test for hijacking.
    if (!$multiple) {

      if (isset($atts['hijack']) && 1 === (int) $atts['hijack']) {

        // Add title to array.
        $this->post_titles[$post_id] = $data['title'];

        // Override title.
        add_filter('the_title', [$this, 'get_title'], 100, 2);

        // Overwrite content.
        add_filter('the_content', [$this, 'get_content']);

        // Don't show title.
        $show_title = FALSE;

        // Add a class for styling purposes.
        $class = ' civicrm-shortcode-single';

      }

    }

    // Set some template variables.

    // Description.
    $description = FALSE;
    if (isset($data['text']) && !empty($data['text'])) {
      $description = $data['text'];
    }

    /**
     * Filter the CiviCRM Shortcode more link text.
     *
     * @since 4.6
     *
     * @param str The existing Shortcode more link text.
     */
    $link_text = apply_filters('civicrm_shortcode_more_link', __('Find out more...', 'civicrm'));

    // Provide an enticing link.
    $more_link = sprintf('<a href="%s">%s</a>', $link, $link_text);

    // Assume CiviCRM footer is not enabled.
    $empowered_enabled = FALSE;
    $footer = '';

    // Test config object for setting.
    if (1 === (int) $config->empoweredBy) {

      // Footer enabled - define it.
      $civi = __('CiviCRM.org - Growing and Sustaining Relationships', 'civicrm');
      $logo = '<div class="empowered-by-logo"><span>' . __('CiviCRM', 'civicrm') . '</span></div>';
      $civi_link = '<a href="https://civicrm.org/" title="' . $civi . '" target="_blank" class="empowered-by-link">' . $logo . '</a>';
      /* translators: %s: The link to the CiviCRM website. */
      $empowered = sprintf(__('Empowered by %s', 'civicrm'), $civi_link);

      /**
       * Filter the CiviCRM Shortcode footer text.
       *
       * @since 4.6
       *
       * @param str $empowered The existing Shortcode footer.
       */
      $footer = apply_filters('civicrm_shortcode_footer', $empowered);

      $empowered_enabled = TRUE;

    }

    // Start buffering.
    ob_start();

    // Include template.
    include CIVICRM_PLUGIN_DIR . 'assets/templates/civicrm.shortcode.php';

    // Save the output and flush the buffer.
    $markup = ob_get_clean();

    /**
     * Filter the computed CiviCRM Shortcode markup.
     *
     * @since 4.6
     *
     * @param str $markup The computed Shortcode markup.
     * @param int $post_id The numeric ID of the WordPress post.
     * @param string $shortcode The Shortcode being parsed.
     */
    return apply_filters('civicrm_shortcode_render_multiple', $markup, $post_id, $shortcode);

  }

  /**
   * In order to hijack the page, we need to override the context.
   *
   * @since 4.6
   *
   * @return string The overridden context code.
   */
  public function get_context() {
    return 'nonpage';
  }

  /**
   * In order to hijack the page, we need to override the content.
   *
   * @since 4.6
   *
   * @param string $content The content.
   * @return string $content The overridden content.
   */
  public function get_content($content) {

    global $post;

    // Is this the post?
    if (!array_key_exists($post->ID, $this->shortcode_markup)) {
      return $content;
    }

    // Bail if it has multiple Shortcodes.
    if (count($this->shortcode_markup[$post->ID]) > 1) {
      return $content;
    }

    return $this->shortcode_markup[$post->ID][0];

  }

  /**
   * In order to hijack the page, we need to override the title.
   *
   * @since 4.6
   *
   * @param string $title The existing title.
   * @param int $post_id The numeric ID of the WordPress post.
   * @return string $title The overridden title.
   */
  public function get_title($title, $post_id = 0) {

    // Bail if there is no Post ID.
    if (empty($post_id)) {
      return $title;
    }

    // Is this the post?
    if (!array_key_exists($post_id, $this->shortcode_markup)) {
      return $title;
    }

    // Bail if it has multiple Shortcodes.
    if (count($this->shortcode_markup[$post_id]) > 1) {
      return $title;
    }

    // Shortcodes may or may not override title.
    if (array_key_exists($post_id, $this->post_titles)) {
      $title = $this->post_titles[$post_id];
    }

    return $title;

  }

  /**
   * Override a WordPress page title with the CiviCRM entity title.
   *
   * Callback method for 'single_page_title' hook, always called from WordPress
   * front-end.
   *
   * @since 4.6
   * @since 5.33 Moved to this class.
   *
   * @param string $post_title The title of the WordPress page or post.
   * @param object $post The WordPress post object the title applies to.
   * @return string $civicrm_wp_title The title of the CiviCRM entity.
   */
  public function single_page_title($post_title, $post) {

    // Sanity check and override.
    global $civicrm_wp_title;
    if (!empty($civicrm_wp_title)) {
      return $civicrm_wp_title;
    }

    // Fallback.
    return $post_title;

  }

  /**
   * Detect and return CiviCRM Shortcodes in post content.
   *
   * @since 4.6
   * @since 5.44 Made recursive.
   *
   * @param str $content The content to parse.
   * @return array $shortcodes Array of Shortcodes.
   */
  private function get_for_post($content) {

    // Init return array.
    $shortcodes = [];

    // Attempt to discover all instances of the Shortcode.
    $pattern = get_shortcode_regex();

    if (
      preg_match_all('/' . $pattern . '/s', $content, $matches)
      && array_key_exists(2, $matches)
    ) {

      // Get keys for our Shortcode.
      $keys = array_keys($matches[2], 'civicrm');

      // Add found Shortcodes at this level.
      if (!empty($keys)) {
        foreach ($keys as $key) {
          $shortcodes[] = $matches[0][$key];
        }
      }

      // Recurse when nested Shortcodes are found.
      if (!empty($matches[5])) {
        foreach ($matches[5] as $match) {
          if (!empty($match)) {
            $shortcodes_deeper = $this->get_for_post($match);
            $shortcodes = array_merge($shortcodes, $shortcodes_deeper);
          }
        }
      }

    }

    return $shortcodes;

  }

  /**
   * Return attributes for a given CiviCRM Shortcode.
   *
   * @since 4.6
   *
   * @param string $shortcode The Shortcode to parse.
   * @return array $shortcode_atts Array of Shortcode attributes.
   */
  private function get_atts($shortcode) {

    // Strip all but attributes definitions.
    $text = str_replace('[civicrm ', '', $shortcode);
    $text = str_replace(']', '', $text);

    // Extract attributes.
    $shortcode_atts = shortcode_parse_atts($text);

    return $shortcode_atts;

  }

  /**
   * Preprocess CiviCRM-defined Shortcodes.
   *
   * @since 4.6
   *
   * @param array $atts Shortcode attributes array.
   * @return array $args Shortcode arguments array.
   */
  public function preprocess_atts($atts) {

    $defaults = [
      'component' => 'contribution',
      'action' => NULL,
      'mode' => NULL,
      'id' => NULL,
      'cid' => NULL,
      'gid' => NULL,
      'cs' => NULL,
      'force' => NULL,
    ];

    // Parse Shortcode attributes.
    $attributes = shortcode_atts($defaults, $atts, 'civicrm');

    $args = [
      'reset' => 1,
      'id'    => $attributes['id'],
      'force' => $attributes['force'],
    ];

    // Construct args for known components.
    switch ($attributes['component']) {

      case 'contribution':

        if ($attributes['mode'] === 'preview' || $attributes['mode'] === 'test') {
          $args['action'] = 'preview';
        }

        switch ($attributes['action']) {
          case 'setup':
            $args['q'] = 'civicrm/contribute/campaign';
            $args['action'] = 'add';
            $args['component'] = 'contribute';
            unset($args['id']);
            $args['pageId'] = $attributes['id'];
            break;

          case 'transact':
          default:
            $args['q'] = 'civicrm/contribute/transact';
            break;
        }
        break;

      case 'pcp':

        if ($attributes['mode'] === 'preview' || $attributes['mode'] === 'test') {
          $args['action'] = 'preview';
        }

        switch ($attributes['action']) {
          case 'transact':
            $args['q'] = 'civicrm/contribute/transact';
            $args['pcpId'] = $args['id'];
            $args['id'] = civicrm_api3('Pcp', 'getvalue', [
              'return' => 'page_id',
              'id' => $args['pcpId'],
            ]);
            break;

          case 'info':
          default:
            $args['q'] = 'civicrm/pcp/info';
            break;
        }
        break;

      case 'event':

        switch ($attributes['action']) {
          case 'register':
            $args['q'] = 'civicrm/event/register';
            if ($attributes['mode'] === 'preview' || $attributes['mode'] === 'test') {
              $args['action'] = 'preview';
            }
            break;

          case 'info':
            $args['q'] = 'civicrm/event/info';
            break;

          default:
            return FALSE;
        }
        break;

      case 'user-dashboard':

        $args['q'] = 'civicrm/user';
        unset($args['id']);
        break;

      case 'profile':

        if ($attributes['mode'] === 'edit') {
          $args['q'] = 'civicrm/profile/edit';
        }
        elseif ($attributes['mode'] === 'view') {
          $args['q'] = 'civicrm/profile/view';
        }
        elseif ($attributes['mode'] === 'search') {
          $args['q'] = 'civicrm/profile';
        }
        elseif ($attributes['mode'] === 'map') {
          $args['q'] = 'civicrm/profile/map';
          $args['map'] = 1;
        }
        else {
          $args['q'] = 'civicrm/profile/create';
        }
        $args['gid'] = $attributes['gid'];
        break;

      case 'petition':

        $args['q'] = 'civicrm/petition/sign';
        $args['sid'] = $args['id'];
        unset($args['id']);
        break;

    }

    /**
     * Filter the CiviCRM Shortcode arguments.
     *
     * This filter allows plugins or CiviCRM Extensions to modify the attributes
     * that the 'civicrm' Shortcode allows. Injected attributes and their values
     * will also become available in the $_REQUEST and $_GET arrays.
     *
     * @since 4.7.28
     *
     * @param array $args Existing Shortcode arguments.
     * @param array $attributes Shortcode attributes.
     */
    return apply_filters('civicrm_shortcode_preprocess_atts', $args, $attributes);

  }

  /**
   * Post-process CiviCRM-defined Shortcodes.
   *
   * @since 4.6
   *
   * @param array $atts Shortcode attributes array.
   * @param array $args Shortcode arguments array.
   * @return array|bool $data The array data used to build the Shortcode markup, or false on failure.
   */
  public function get_data($atts, $args) {

    // Init return array.
    $data = [];

    if (!$this->civi->initialize()) {
      return FALSE;
    }

    /**
     * Filter the base CiviCRM API parameters.
     *
     * This filter allows plugins or CiviCRM Extensions to modify the API call
     * when there are multiple Shortcodes being rendered.
     *
     * @since 4.7.28
     *
     * @param array $params Existing API params.
     * @param array $atts Shortcode attributes array.
     * @param array $args Shortcode arguments array.
     */
    $params = apply_filters('civicrm_shortcode_api_params', [
      'sequential' => 1,
    ], $atts, $args);

    // Get the CiviCRM entity via the API.
    switch ($atts['component']) {

      case 'contribution':

        // Add Contribution Page ID.
        $params['id'] = $args['id'];

        // Call API.
        $civi_entity = civicrm_api3('ContributionPage', 'getsingle', $params);

        // Set title.
        $data['title'] = $civi_entity['title'];

        // Set text, if present.
        $data['text'] = '';
        if (isset($civi_entity['intro_text'])) {
          $data['text'] = $civi_entity['intro_text'];
        }

        break;

      case 'event':

        // Add Event ID.
        $params['id'] = $args['id'];

        // Call API.
        $civi_entity = civicrm_api3('Event', 'getsingle', $params);

        // Set title.
        switch ($atts['action']) {
          case 'register':
            $data['title'] = sprintf(
              /* translators: %s: The event title. */
              __('Register for %s', 'civicrm'),
              $civi_entity['title']
            );
            break;

          case 'info':
          default:
            $data['title'] = '';
            if (!empty($civi_entity['title'])) {
              $data['title'] = $civi_entity['title'];
            }
            break;
        }

        // Set text, if present.
        $data['text'] = '';
        if (!empty($civi_entity['summary'])) {
          $data['text'] = $civi_entity['summary'];
        }
        // Override with "description" if "summary" is empty.
        if (empty($civi_entity['summary']) && !empty($civi_entity['description'])) {
          $data['text'] = $civi_entity['description'];
        }

        break;

      case 'user-dashboard':

        // Set title.
        $data['title'] = __('Dashboard', 'civicrm');
        break;

      case 'profile':

        // Add Profile ID.
        $params['id'] = $args['gid'];

        // Call API.
        $civi_entity = civicrm_api3('UFGroup', 'getsingle', $params);

        // Set title.
        $data['title'] = $civi_entity['title'];

        // Set text to empty.
        $data['text'] = '';
        break;

      case 'petition':

        // Add Petition ID.
        $params['id'] = $atts['id'];

        // Call API.
        $civi_entity = civicrm_api3('Survey', 'getsingle', $params);

        // Set title.
        $data['title'] = $civi_entity['title'];

        // Set text, if present.
        $data['text'] = '';
        if (isset($civi_entity['instructions'])) {
          $data['text'] = $civi_entity['instructions'];
        }

        break;

      default:
        // Do we need to protect against malformed Shortcodes?
        break;

    }

    /**
     * Filter the CiviCRM Shortcode data array.
     *
     * This filter allows plugins or CiviCRM Extensions to modify the data used
     * to display the Shortcode when there are multiple Shortcodes being rendered.
     *
     * @since 4.7.28
     *
     * @param array $data The existing Shortcode data.
     * @param array $atts Shortcode attributes array.
     * @param array $args Shortcode arguments array.
     */
    return apply_filters('civicrm_shortcode_get_data', $data, $atts, $args);

  }

}
