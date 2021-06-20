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
   * The stored shortcodes.
   * @since 4.6
   * @access public
   */
  public $shortcodes = [];

  /**
   * @var array
   * The array of rendered shortcode markup.
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
   * Register hooks to handle the presence of shortcodes in content.
   *
   * @since 4.6
   */
  public function register_hooks() {

    // Register the CiviCRM shortcode.
    add_shortcode('civicrm', [$this, 'render_single']);

    // Add CiviCRM core resources when a shortcode is detected in the post content.
    add_action('wp', [$this, 'prerender'], 10, 1);

  }

  /**
   * Determine if a CiviCRM shortcode is present in any of the posts about to be displayed.
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

    // A counter's useful.
    $shortcodes_present = 0;

    /*
     * Let's loop through the results.
     * This also has the effect of bypassing the logic in:
     * @see https://github.com/civicrm/civicrm-wordpress/pull/36
     */
    if (have_posts()) {
      while (have_posts()) {

        the_post();

        global $post;

        // Check for existence of shortcode in content.
        if (has_shortcode($post->post_content, 'civicrm')) {

          // Get CiviCRM shortcodes in this post.
          $shortcodes_array = $this->get_for_post($post->post_content);

          // Sanity check.
          if (!empty($shortcodes_array)) {

            // Add it to our property.
            $this->shortcodes[$post->ID] = $shortcodes_array;

            // Bump shortcode counter.
            $shortcodes_present += count($this->shortcodes[$post->ID]);

          }

        }

      }
    }

    // Reset loop.
    rewind_posts();

    // Did we get any?
    if ($shortcodes_present) {

      // We need CiviCRM initialised prior to parsing shortcodes.
      if (!$this->civi->initialize()) {
        return;
      }

      // How should we handle multiple shortcodes?
      if ($shortcodes_present > 1) {

        // Add CSS resources for front end.
        add_action('wp_enqueue_scripts', [$this->civi, 'front_end_css_load'], 100);

        // Let's add dummy markup.
        foreach ($this->shortcodes as $post_id => $shortcode_array) {

          // Set flag if there are multple shortcodes in this post.
          $multiple = (count($shortcode_array) > 1) ? 1 : 0;

          foreach ($shortcode_array as $shortcode) {

            // Mimic invoke in multiple shortcode context.
            $this->shortcode_markup[$post_id][] = $this->render_multiple($post_id, $shortcode, $multiple);

          }

        }

      }
      else {

        // Add core resources for front end.
        add_action('wp', [$this->civi, 'front_end_page_load'], 100);

        /*
         * Since we have only one shortcode, run the_loop again.
         * The DB query has already been done, so this has no significant impact.
         */
        if (have_posts()) {
          while (have_posts()) {

            the_post();

            global $post;

            // Is this the post?
            if (!array_key_exists($post->ID, $this->shortcodes)) {
              continue;
            }

            // The shortcode must be the first item in the shortcodes array.
            $shortcode = $this->shortcodes[$post->ID][0];

            // Check to see if a shortcode component has been repeated?
            $atts = $this->get_atts($shortcode);

            // Test for hijacking.
            if (isset($atts['hijack']) && $atts['hijack'] == '1') {
              add_filter('civicrm_context', [$this, 'get_context']);
            }

            // Store corresponding markup.
            $this->shortcode_markup[$post->ID][] = do_shortcode($shortcode);

            // Test for hijacking.
            if (isset($atts['hijack']) && $atts['hijack'] == '1') {

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

    }

    // Flag that we have parsed shortcodes.
    $this->shortcodes_parsed = TRUE;

    /**
     * Broadcast that shortcodes have been parsed.
     *
     * @since 4.6
     */
    do_action('civicrm_shortcodes_parsed');

  }

  /**
   * Handles CiviCRM-defined shortcodes.
   *
   * @since 4.6
   *
   * @param array $atts Shortcode attributes array.
   * @return string HTML for output.
   */
  public function render_single($atts) {

    // Do not parse shortcodes in REST context for PUT, POST and DELETE methods.
    if (defined('REST_REQUEST') && REST_REQUEST && (isset($_PUT) || isset($_POST) || isset($_DELETE))) {
      // Return the original shortcode.
      $shortcode = '[civicrm';
      foreach ($atts as $att => $val) {
        $shortcode .= ' ' . $att . '="' . $val . '"';
      }
      $shortcode .= ']';
      return $shortcode;
    }

    // Check if we've already parsed this shortcode.
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

          // This shortcode must have been rendered.
          return $this->shortcode_markup[$post->ID][$this->shortcode_in_post[$post->ID]];

        }
      }
    }

    // Preprocess shortcode attributes.
    $args = $this->preprocess_atts($atts);

    // Sanity check for improperly constructed shortcode.
    if ($args === FALSE) {
      return '<p>' . __('Do not know how to handle this shortcode.', 'civicrm') . '</p>';
    }

    // If the shortcode has a path (`q`), fetch content by invoking the civicrm route
    if (!empty($args['q'])) {
      // invoke() requires environment variables to be set.
      foreach ($args as $key => $value) {
        if ($value !== NULL) {
          set_query_var($key, $value);
          $_REQUEST[$key] = $_GET[$key] = $value;
        }
      }

      // Kick out if not CiviCRM.
      if (!$this->civi->initialize()) {
        return '';
      }

      // Check permission.
      $argdata = $this->civi->get_request_args();
      if (!$this->civi->users->check_permission($argdata['args'])) {
        return $this->civi->users->get_permission_denied();;
      }

      // CMW: why do we need this? Nothing that follows uses it.
      require_once ABSPATH . WPINC . '/pluggable.php';

      // Start buffering.
      ob_start();
      // Now, instead of echoing, shortcode output ends up in buffer.
      $this->civi->invoke();
      // Save the output and flush the buffer.
      $content = ob_get_clean();
    }
    // If there is no path, rely on civicrm_shortcode_get_markup to provide the markup.
    else {
      $content = '<p>' . __('Do not know how to handle this shortcode.', 'civicrm') . '</p>';
      /**
       * Get the markup for "pathless" Shortcodes.
       *
       * This filter allows plugins or CiviCRM Extensions to modify the markup used
       * to display a shortcode that has no CiviCRM route/path. This may be:
       *
       * * Accidental due to an improperly constructed shortcode or
       * * Deliberate because a component may not require a route/path
       *
       * @since 5.37
       *
       * @param str $markup The default markup for an improperly constructed shortcode.
       * @param array $atts The shortcode attributes array.
       * @param array $args The shortcode arguments array.
       * @param str Context flag - value is either 'single' or 'multiple'.
       * @return str The modified shortcode markup.
       */
      $content = apply_filters('civicrm_shortcode_get_markup', $content, $atts, $args, 'single');
    }

    return $content;
  }

  /**
   * Return a generic display for a shortcode instead of a CiviCRM invocation.
   *
   * @since 4.6
   *
   * @param int $post_id The containing WordPress post ID.
   * @param string $shortcode The shortcode being parsed.
   * @param bool $multiple Boolean flag, TRUE if post has multiple shortcodes, FALSE otherwise.
   * @return string $markup Generic markup for multiple instances.
   */
  private function render_multiple($post_id = FALSE, $shortcode = FALSE, $multiple = 0) {

    // Get attributes.
    $atts = $this->get_atts($shortcode);

    // Pre-process shortcode and retrieve args.
    $args = $this->preprocess_atts($atts);

    // Sanity check for improperly constructed shortcode.
    if ($args === FALSE) {
      return '<p>' . __('Do not know how to handle this shortcode.', 'civicrm') . '</p>';
    }

    // Get pathless markup from filter callback
    if (empty($args['q'])) {
      $markup = '<p>' . __('Do not know how to handle this shortcode.', 'civicrm') . '</p>';
      return apply_filters('civicrm_shortcode_get_markup', $markup, $atts, $args, 'multiple');
    }

    // Get data for this shortcode.
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

    // Do we have multiple shortcodes?
    if ($multiple != 0) {

      $links = [];
      foreach ($args as $var => $arg) {
        if (!empty($arg) && $var != 'q') {
          $links[] = $var . '=' . $arg;
        }
      }
      $query = implode('&', $links);

      // $absolute, $frontend, $forceBackend
      $base_url = $this->civi->get_base_url(TRUE, FALSE, FALSE);

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

      if (isset($atts['hijack']) && $atts['hijack'] == '1') {

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

    // Provide an enticing link.
    $more_link = sprintf(
      '<a href="%s">%s</a>',
      $link,

      /**
       * Filter the CiviCRM shortcode more link text.
       *
       * @since 4.6
       *
       * @param str The existing shortcode more link text.
       * @return str The modified shortcode more link text.
       */
      apply_filters('civicrm_shortcode_more_link', __('Find out more...', 'civicrm'))

    );

    // Assume CiviCRM footer is not enabled.
    $empowered_enabled = FALSE;
    $footer = '';

    // Test config object for setting.
    if ($config->empoweredBy == 1) {

      // Footer enabled - define it.
      $civi = __('CiviCRM.org - Growing and Sustaining Relationships', 'civicrm');
      $logo = '<div class="empowered-by-logo"><span>' . __('CiviCRM', 'civicrm') . '</span></div>';
      $civi_link = '<a href="https://civicrm.org/" title="' . $civi . '" target="_blank" class="empowered-by-link">' . $logo . '</a>';
      $empowered = sprintf(__('Empowered by %s', 'civicrm'), $civi_link);

      /**
       * Filter the CiviCRM shortcode footer text.
       *
       * @since 4.6
       *
       * @param str $empowered The existing shortcode footer.
       * @return str $empowered The modified shortcode footer.
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
     * Filter the computed CiviCRM shortcode markup.
     *
     * @since 4.6
     *
     * @param str $markup The computed shortcode markup.
     * @param int $post_id The numeric ID of the WordPress post.
     * @param string $shortcode The shortcode being parsed.
     * @return str $markup The modified shortcode markup.
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
   * @return string The overridden content.
   */
  public function get_content($content) {

    global $post;

    // Is this the post?
    if (!array_key_exists($post->ID, $this->shortcode_markup)) {
      return $content;
    }

    // Bail if it has multiple shortcodes.
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
  public function get_title($title, $post_id) {

    // Is this the post?
    if (!array_key_exists($post_id, $this->shortcode_markup)) {
      return $title;
    }

    // Bail if it has multiple shortcodes.
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

    // Fallback
    return $post_title;

  }

  /**
   * Detect and return CiviCRM shortcodes in post content.
   *
   * @since 4.6
   *
   * @param str $content The content to parse.
   * @return array $shortcodes Array of shortcodes.
   */
  private function get_for_post($content) {

    // Init return array.
    $shortcodes = [];

    // Attempt to discover all instances of the shortcode.
    $pattern = get_shortcode_regex();

    if (
      preg_match_all('/' . $pattern . '/s', $content, $matches)
      && array_key_exists(2, $matches)
      && in_array('civicrm', $matches[2])
    ) {

      // Get keys for our shortcode.
      $keys = array_keys($matches[2], 'civicrm');

      foreach ($keys as $key) {
        $shortcodes[] = $matches[0][$key];
      }

    }

    return $shortcodes;

  }

  /**
   * Return attributes for a given CiviCRM shortcode.
   *
   * @since 4.6
   *
   * @param $shortcode The shortcode to parse.
   * @return array $shortcode_atts Array of shortcode attributes.
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
   * Preprocess CiviCRM-defined shortcodes.
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
    $shortcode_atts = shortcode_atts($defaults, $atts, 'civicrm');
    extract($shortcode_atts);

    $args = [
      'reset' => 1,
      'id'    => $id,
      'force' => $force,
    ];

    // Construct args for known components.
    switch ($component) {

      case 'contribution':

        if ($mode == 'preview' || $mode == 'test') {
          $args['action'] = 'preview';
        }

        switch($action) {
          case 'transact':
            $args['q'] = 'civicrm/contribute/transact';
            break;

          case 'setup':
            $args['q'] = 'civicrm/contribute/campaign';
            $args['action'] = 'add';
            $args['component'] = 'contribute';
            break;

          default:
            $args['q'] = 'civicrm/contribute/transact';
            break;
        }
        break;

      case 'pcp':

        if ($mode == 'preview' || $mode == 'test') {
          $args['action'] = 'preview';
        }

        switch ($action) {
          case 'transact':
            $args['q'] = 'civicrm/contribute/transact';
            $args['pcpId'] = $args['id'];
            $args['id'] = civicrm_api3('Pcp', 'getvalue', [
              'return' => 'page_id',
              'id' => $args['pcpId'],
            ]);
            break;

          case 'setup':
            $args['q'] = 'civicrm/contribute/campaign';
            $args['action'] = 'add';
            $args['component'] = 'contribute';
            $args['id'] = civicrm_api3('Pcp', 'getvalue', [
              'return' => 'page_id',
              'id' => $args['id'],
            ]);
            break;

          case 'info':
          default:
            $args['q'] = 'civicrm/pcp/info';
            break;
        }
        break;

      case 'event':

        switch ($action) {
          case 'register':
            $args['q'] = 'civicrm/event/register';
            if ($mode == 'preview' || $mode == 'test') {
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

        if ($mode == 'edit') {
          $args['q'] = 'civicrm/profile/edit';
        }
        elseif ($mode == 'view') {
          $args['q'] = 'civicrm/profile/view';
        }
        elseif ($mode == 'search') {
          $args['q'] = 'civicrm/profile';
        }
        else {
          $args['q'] = 'civicrm/profile/create';
        }
        $args['gid'] = $gid;
        break;

      case 'petition':

        $args['q'] = 'civicrm/petition/sign';
        $args['sid'] = $args['id'];
        unset($args['id']);
        break;

    }

    /**
     * Filter the CiviCRM shortcode arguments.
     *
     * This filter allows plugins or CiviCRM Extensions to modify the attributes
     * that the 'civicrm' shortcode allows. Injected attributes and their values
     * will also become available in the $_REQUEST and $_GET arrays.
     *
     * @since 4.7.28
     *
     * @param array $args Existing shortcode arguments.
     * @param array $shortcode_atts Shortcode attributes.
     * @return array $args Modified shortcode arguments.
     */
    $args = apply_filters('civicrm_shortcode_preprocess_atts', $args, $shortcode_atts);

    return $args;

  }

  /**
   * Post-process CiviCRM-defined shortcodes.
   *
   * @since 4.6
   *
   * @param array $atts Shortcode attributes array.
   * @param array $args Shortcode arguments array.
   * @return array|bool $data The array data used to build the shortcode markup, or false on failure.
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
     * when there are multiple shortcodes being rendered.
     *
     * @since 4.7.28
     *
     * @param array $params Existing API params.
     * @param array $atts Shortcode attributes array.
     * @param array $args Shortcode arguments array.
     * @return array $params Modified API params.
     */
    $params = apply_filters('civicrm_shortcode_api_params', [
      'version' => 3,
      'sequential' => '1',
    ], $atts, $args);

    // Get the CiviCRM entity via the API.
    switch ($atts['component']) {

      case 'contribution':

        // Add Contribution Page ID.
        $params['id'] = $args['id'];

        // Call API.
        $civi_entity = civicrm_api('contribution_page', 'getsingle', $params);

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
        $civi_entity = civicrm_api('event', 'getsingle', $params);

        // Set title.
        switch ($atts['action']) {
          case 'register':
            $data['title'] = sprintf(
              __('Register for %s', 'civicrm'),
              $civi_entity['title']
            );
            break;

          case 'info':
          default:
            $data['title'] = $civi_entity['title'];
            break;
        }

        // Set text, if present.
        $data['text'] = '';
        if (isset($civi_entity['summary'])) {
          $data['text'] = $civi_entity['summary'];
        }
        if (
          // Summary is not present or is empty.
          (!isset($civi_entity['summary']) || empty($civi_entity['summary']))
          &&
          // We do have a description.
          isset($civi_entity['description']) && !empty($civi_entity['description'])
        ) {
          // Override with description.
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
        $civi_entity = civicrm_api('uf_group', 'getsingle', $params);

        // Set title.
        $data['title'] = $civi_entity['title'];

        // Set text to empty.
        $data['text'] = '';
        break;

      case 'petition':

        // Add Petition ID.
        $params['id'] = $atts['id'];

        // Call API.
        $civi_entity = civicrm_api('survey', 'getsingle', $params);

        // Set title.
        $data['title'] = $civi_entity['title'];

        // Set text, if present.
        $data['text'] = '';
        if (isset($civi_entity['instructions'])) {
          $data['text'] = $civi_entity['instructions'];
        }

        break;

      default:
        // Do we need to protect against malformed shortcodes?
        break;

    }

    /**
     * Filter the CiviCRM shortcode data array.
     *
     * This filter allows plugins or CiviCRM Extensions to modify the data used
     * to display the shortcode when there are multiple shortcodes being rendered.
     *
     * @since 4.7.28
     *
     * @param array $data The existing shortcode data.
     * @param array $atts Shortcode attributes array.
     * @param array $args Shortcode arguments array.
     * @return array $data The modified shortcode data.
     */
    return apply_filters('civicrm_shortcode_get_data', $data, $atts, $args);

  }

}
