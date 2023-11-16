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
 * Define CiviCRM_For_WordPress_Admin_Page_Options Class.
 *
 * @since 5.34
 */
class CiviCRM_For_WordPress_Admin_Page_Options {

  /**
   * @var object
   * Plugin object reference.
   * @since 5.34
   * @access public
   */
  public $civi;

  /**
   * @var object
   * Admin object reference.
   * @since 5.34
   * @access public
   */
  public $admin;

  /**
   * @var object
   * Admin page slug.
   * @since 5.34
   * @access public
   */
  public $slug = 'civi_options';

  /**
   * Instance constructor.
   *
   * @since 5.34
   */
  public function __construct() {

    // Bail if CiviCRM is not installed.
    if (!CIVICRM_INSTALLED) {
      return;
    }

    // Store reference to CiviCRM plugin object.
    $this->civi = civi_wp();

    // Store reference to admin object.
    $this->admin = civi_wp()->admin;

    // Wait for admin class to register hooks.
    add_action('civicrm/admin/hooks/registered', [$this, 'register_hooks']);

  }

  /**
   * Register hooks.
   *
   * @since 5.34
   */
  public function register_hooks() {

    // Add items to the CiviCRM admin menu.
    add_action('admin_menu', [$this, 'add_menu_items'], 9);

    // Add our meta boxes.
    add_action('civicrm/page/options/add_meta_boxes', [$this, 'meta_boxes_options_add']);

    // Add AJAX handlers.
    add_action('wp_ajax_civicrm_basepage', [$this, 'ajax_save_basepage']);
    add_action('wp_ajax_civicrm_shortcode', [$this, 'ajax_save_shortcode']);
    add_action('wp_ajax_civicrm_email_sync', [$this, 'ajax_save_email_sync']);
    add_action('wp_ajax_civicrm_refresh_permissions', [$this, 'ajax_refresh_permissions']);
    add_action('wp_ajax_civicrm_clear_caches', [$this, 'ajax_clear_caches']);

  }

  /**
   * Get the capability required to access the Settings Page.
   *
   * @since 5.37
   */
  public function access_capability() {

    /**
     * Return default capability but allow overrides.
     *
     * @since 5.37
     *
     * @param str The default access capability.
     */
    return apply_filters('civicrm/admin/settings/cap', 'manage_options');

  }

  /**
   * Adds CiviCRM sub-menu items to WordPress admin menu.
   *
   * @since 5.34
   */
  public function add_menu_items() {

    if (!$this->civi->initialize()) {
      return;
    }

    // Get access capability.
    $capability = $this->access_capability();

    // Add Settings submenu item.
    $options_page = add_submenu_page(
      'CiviCRM',
      __('CiviCRM Settings for WordPress', 'civicrm'),
      __('Settings', 'civicrm'),
      $capability,
      $this->slug,
      [$this, 'page_options']
    );

    // Register our form submit hander.
    add_action('load-' . $options_page, [$this, 'form_submitted']);

    // Add resources prior to page load.
    add_action('admin_head-' . $options_page, [$this, 'admin_head']);
    add_action('admin_print_styles-' . $options_page, [$this, 'admin_css']);

  }

  /**
   * Enqueue scripts on the pages that need them.
   *
   * @since 5.34
   */
  public function admin_head() {

    // Enqueue WordPress scripts.
    wp_enqueue_script('common');
    wp_enqueue_script('jquery-ui-sortable');
    wp_enqueue_script('dashboard');

    // Enqueue Javascript.
    wp_enqueue_script(
      'civicrm-options-script',
      CIVICRM_PLUGIN_URL . 'assets/js/civicrm.options.js',
      ['jquery'],
      CIVICRM_PLUGIN_VERSION,
      FALSE
    );

    // Init settings and localisation array.
    $vars = [
      'settings' => [
        'ajax_url' => admin_url('admin-ajax.php'),
      ],
      'localisation' => [
        'update' => __('Update', 'civicrm'),
        'saving' => __('Saving...', 'civicrm'),
        'saved' => __('Saved', 'civicrm'),
        'refresh' => __('Refresh', 'civicrm'),
        'refreshing' => __('Refreshing...', 'civicrm'),
        'refreshed' => __('Refreshed', 'civicrm'),
        'cache' => __('Clear Caches', 'civicrm'),
        'clearing' => __('Clearing...', 'civicrm'),
        'cleared' => __('Cleared', 'civicrm'),
      ],
    ];

    // Localise the WordPress way.
    wp_localize_script(
      'civicrm-options-script',
      'CiviCRM_Options_Vars',
      $vars
    );

  }

  /**
   * Enqueue stylesheet on this page.
   *
   * @since 5.34
   */
  public function admin_css() {

    // Enqueue common CSS.
    wp_enqueue_style(
      'civicrm-admin-styles',
      CIVICRM_PLUGIN_URL . 'assets/css/civicrm.admin.css',
      NULL,
      CIVICRM_PLUGIN_VERSION,
      'all'
    );

  }

  // ---------------------------------------------------------------------------
  // Page Loader
  // ---------------------------------------------------------------------------

  /**
   * Render the CiviCRM Settings page.
   *
   * @since 5.34
   */
  public function page_options() {

    // Get the current screen object.
    $screen = get_current_screen();

    /**
     * Allow meta boxes to be added to this screen.
     *
     * The Screen ID to use is: "civicrm_page_cwps_settings".
     *
     * Used internally by:
     *
     * - self::meta_boxes_options_add()
     *
     * @since 5.34
     *
     * @param str $screen_id The ID of the current screen.
     */
    do_action('civicrm/page/options/add_meta_boxes', $screen->id);

    // Get the column CSS class.
    $columns = absint($screen->get_columns());
    $columns_css = '';
    if ($columns) {
      $columns_css = " columns-$columns";
    }

    // Include template file.
    include CIVICRM_PLUGIN_DIR . 'assets/templates/pages/page.options.php';

  }

  /**
   * Get the URL for the form action.
   *
   * @since 5.34
   *
   * @return string $target_url The URL for the admin form action.
   */
  public function page_submit_url_get() {

    // Our array of arguments.
    $args = [
      'page' => $this->slug,
    ];

    // Sanitise admin page url.
    $target_url = add_query_arg($args, admin_url('admin.php'));

    // --<
    return $target_url;

  }

  // ---------------------------------------------------------------------------
  // Meta Box Loaders
  // ---------------------------------------------------------------------------

  /**
   * Register Integration Page meta boxes.
   *
   * @since 5.34
   *
   * @param str $screen_id The Admin Page Screen ID.
   */
  public function meta_boxes_options_add($screen_id) {

    // Define valid Screen IDs.
    $screen_ids = [
      'civicrm_page_' . $this->slug,
    ];

    // Bail if not the Screen ID we want.
    if (!in_array($screen_id, $screen_ids)) {
      return;
    }

    // Bail if user cannot access the Settings Page.
    $capability = $this->access_capability();
    if (!current_user_can($capability)) {
      return;
    }

    // Init data.
    $data = [];

    // Create "WordPress Base Page" metabox.
    add_meta_box(
      'civicrm_options_basepage',
      __('WordPress Base Page', 'civicrm'),
      [$this, 'meta_box_options_basepage_render'],
      $screen_id,
      'normal',
      'core',
      $data
    );

    // Create "Shortcode Display Mode" metabox.
    add_meta_box(
      'civicrm_options_shortcode',
      __('Shortcode Display Mode', 'civicrm'),
      [$this, 'meta_box_options_shortcode_render'],
      $screen_id,
      'normal',
      'core',
      $data
    );

    // Create "Email Sync" metabox.
    add_meta_box(
      'civicrm_options_email',
      __('Contact Email to User Email Sync', 'civicrm'),
      [$this, 'meta_box_options_email_render'],
      $screen_id,
      'normal',
      'core',
      $data
    );

    // Create "Clear Cache" metabox.
    add_meta_box(
      'civicrm_options_cache',
      __('Clear Caches', 'civicrm'),
      [$this, 'meta_box_options_cache_render'],
      $screen_id,
      'side',
      'core',
      $data
    );

    // Create "Permissions and Capabilities" metabox.
    add_meta_box(
      'civicrm_options_permissions',
      __('Permissions and Capabilities', 'civicrm'),
      [$this, 'meta_box_options_permissions_render'],
      $screen_id,
      'side',
      'core',
      $data
    );

    // Create "Useful Links" metabox.
    add_meta_box(
      'civicrm_options_emailinks',
      __('Useful Links', 'civicrm'),
      [$this, 'meta_box_options_links_render'],
      $screen_id,
      'side',
      'core',
      $data
    );

  }

  // ---------------------------------------------------------------------------
  // Meta Box Renderers
  // ---------------------------------------------------------------------------

  /**
   * Render "WordPress Base Page" meta box.
   *
   * @since 5.34
   *
   * @param mixed $unused Unused param.
   * @param array $metabox Array containing id, title, callback, and args elements.
   */
  public function meta_box_options_basepage_render($unused, $metabox) {

    // Get the setting.
    $basepage_slug = civicrm_api3('Setting', 'getvalue', [
      'name' => 'wpBasePage',
      'group' => 'CiviCRM Preferences',
    ]);

    // Did we get a value?
    if (!empty($basepage_slug)) {

      // Define the query for our Base Page.
      $args = [
        'post_type' => 'page',
        'name' => strtolower($basepage_slug),
        'post_status' => 'publish',
        'posts_per_page' => 1,
      ];

      // Do the query.
      $pages = get_posts($args);

    }

    // Default error message.
    $message = __('Could not find the WordPress Base Page.', 'civicrm');

    // Find the Base Page object.
    $basepage = NULL;
    if (!empty($pages) && is_array($pages)) {
      $basepage = array_pop($pages);
    }

    // Define the params for the Pages dropdown.
    $params = [
      'post_type' => 'page',
      'sort_column' => 'menu_order, post_title',
      'show_option_none' => __('- Select a Base Page -', 'civicrm'),
    ];

    // If the Base Page is set, add its ID.
    if ($basepage instanceof WP_Post) {
      $params['selected'] = $basepage->ID;
    }

    // Determine whether the notice should be hidden.
    $hidden = '';
    if ($basepage instanceof WP_Post) {
      $hidden = ' display: none;';
    }

    // Set AJAX submit button options.
    $options_ajax = [
      'style' => 'float: right;',
      'data-security' => esc_attr(wp_create_nonce('civicrm_basepage')),
      'disabled' => NULL,
    ];

    // Set POST submit button options.
    $options_post = [
      'style' => 'float: right;',
    ];

    /**
     * Filters the Base Page POST submit button attributes.
     *
     * @since 5.34
     *
     * @param array $options_post The existing button attributes.
     */
    $options_post = apply_filters('civicrm/metabox/basepage/submit/options', $options_post);

    // Include template file.
    include CIVICRM_PLUGIN_DIR . 'assets/templates/metaboxes/metabox.options.basepage.php';

  }

  /**
   * Render "Shortcode" meta box.
   *
   * @since 5.44
   *
   * @param mixed $unused Unused param.
   * @param array $metabox Array containing id, title, callback, and args elements.
   */
  public function meta_box_options_shortcode_render($unused, $metabox) {

    if (!$this->civi->initialize()) {
      return;
    }

    // Get the Shortcode Mode setting.
    $shortcode_mode = $this->civi->admin->get_shortcode_mode();

    // Set selected attributes.
    $selected_legacy = $shortcode_mode === 'legacy' ? 'selected="selected"' : '';
    $selected_modern = $shortcode_mode === 'modern' ? 'selected="selected"' : '';

    // Set AJAX submit button options.
    $options_ajax = [
      'style' => 'float: right;',
      'data-security' => esc_attr(wp_create_nonce('civicrm_shortcode')),
      'disabled' => NULL,
    ];

    // Set POST submit button options.
    $options_post = [
      'style' => 'float: right;',
    ];

    /**
     * Filters the Shortcode POST submit button attributes.
     *
     * @since 5.44
     *
     * @param array $options_post The existing button attributes.
     */
    $options_post = apply_filters('civicrm/metabox/shortcode/submit/options', $options_post);

    // Include template file.
    include CIVICRM_PLUGIN_DIR . 'assets/templates/metaboxes/metabox.options.shortcode.php';

  }

  /**
   * Render "Contact Email to User Email Sync" meta box.
   *
   * @since 5.34
   *
   * @param mixed $unused Unused param.
   * @param array $metabox Array containing id, title, callback, and args elements.
   */
  public function meta_box_options_email_render($unused, $metabox) {

    if (!$this->civi->initialize()) {
      return;
    }

    // Get the setting.
    $email_sync_select = civicrm_api3('Setting', 'getvalue', [
      'name' => 'syncCMSEmail',
      'group' => 'CiviCRM Preferences',
    ]);

    // Set selected attributes.
    $selected_yes = $email_sync_select ? 'selected="selected"' : '';
    $selected_no = $email_sync_select ? '' : 'selected="selected"';

    // Set AJAX submit button options.
    $options_ajax = [
      'style' => 'float: right;',
      'data-security' => esc_attr(wp_create_nonce('civicrm_email_sync')),
      'disabled' => NULL,
    ];

    // Set POST submit button options.
    $options_post = [
      'style' => 'float: right;',
    ];

    /**
     * Filters the Email Sync POST submit button attributes.
     *
     * @since 5.34
     *
     * @param array $options_post The existing button attributes.
     */
    $options_post = apply_filters('civicrm/metabox/email_sync/submit/options', $options_post);

    // Include template file.
    include CIVICRM_PLUGIN_DIR . 'assets/templates/metaboxes/metabox.options.email.php';

  }

  /**
   * Render "Permissions" meta box.
   *
   * @since 5.52
   *
   * @param mixed $unused Unused param.
   * @param array $metabox Array containing id, title, callback, and args elements.
   */
  public function meta_box_options_permissions_render($unused, $metabox) {

    // Get the custom role.
    $custom_role = $this->civi->users->has_custom_role();

    // Set selected attributes.
    $selected_disable = empty($custom_role) ? 'selected="selected"' : '';
    $selected_enable = !empty($custom_role) ? 'selected="selected"' : '';

    // Set submit button options.
    $options = [
      'style' => 'float: right;',
      'data-security' => esc_attr(wp_create_nonce('civicrm_refresh_permissions')),
    ];

    // Include template file.
    include CIVICRM_PLUGIN_DIR . 'assets/templates/metaboxes/metabox.options.permissions.php';

  }

  /**
   * Render "Clear Cache" meta box.
   *
   * @since 5.34
   *
   * @param mixed $unused Unused param.
   * @param array $metabox Array containing id, title, callback, and args elements.
   */
  public function meta_box_options_cache_render($unused, $metabox) {

    // Set submit button options.
    $options = [
      'style' => 'float: right;',
      'data-security' => esc_attr(wp_create_nonce('civicrm_clear_caches')),
    ];

    // Include template file.
    include CIVICRM_PLUGIN_DIR . 'assets/templates/metaboxes/metabox.options.cache.php';

  }

  /**
   * Render "Useful Links" meta box.
   *
   * @since 5.34
   *
   * @param mixed $unused Unused param.
   * @param array $metabox Array containing id, title, callback, and args elements.
   */
  public function meta_box_options_links_render($unused, $metabox) {

    if (!$this->civi->initialize()) {
      return;
    }

    // Construct an array of admin links.
    $admin_links = [
      'urls' => [
        'url' => $this->civi->admin->get_admin_link('civicrm/admin/setting/url', 'reset=1'),
        'text' => __('Settings - Resource URLs', 'civicrm'),
      ],
      'uploads' => [
        'url' => $this->civi->admin->get_admin_link('civicrm/admin/setting/path', 'reset=1'),
        'text' => __('Settings - Upload Directories', 'civicrm'),
      ],
      'permissions' => [
        'url' => $this->civi->admin->get_admin_link('civicrm/admin/access/wp-permissions', 'reset=1'),
        'text' => __('WordPress Access Control', 'civicrm'),
      ],
      'extensions' => [
        'url' => $this->civi->admin->get_admin_link('civicrm/admin/extensions', 'reset=1'),
        'text' => __('CiviCRM Extensions', 'civicrm'),
      ],
    ];

    /**
     * Filters the admin links array.
     *
     * @since 5.34
     *
     * @param array $admin_links The default array of admin links.
     */
    $admin_links = apply_filters('civicrm/metabox/links/admin', $admin_links);

    // Construct an array of maintenance links.
    $maintenance_links = [
      'menu' => [
        'url' => $this->civi->admin->get_admin_link('civicrm/menu/rebuild', 'reset=1'),
        'text' => __('Rebuild the CiviCRM menu', 'civicrm'),
      ],
      'triggers' => [
        'url' => $this->civi->admin->get_admin_link('civicrm/menu/rebuild', 'reset=1&triggerRebuild=1'),
        'text' => __('Rebuild the CiviCRM database triggers', 'civicrm'),
      ],
      'upgrade' => [
        'url' => $this->civi->admin->get_admin_link('civicrm/upgrade', 'reset=1'),
        'text' => __('Upgrade CiviCRM', 'civicrm'),
        'description' => __('Please note: you need to update the CiviCRM plugin directory first.', 'civicrm'),
      ],
    ];

    /**
     * Filters the maintenance links array.
     *
     * @since 5.34
     *
     * @param array $maintenance_links The default array of admin links.
     */
    $maintenance_links = apply_filters('civicrm/metabox/links/maintenance', $maintenance_links);

    // Include template file.
    include CIVICRM_PLUGIN_DIR . 'assets/templates/metaboxes/metabox.options.links.php';

  }

  // ---------------------------------------------------------------------------
  // Form Handlers
  // ---------------------------------------------------------------------------

  /**
   * Perform actions when the form has been submitted.
   *
   * @since 5.34
   */
  public function form_submitted() {

    // Nonce is irrelevant at this stage.
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    // phpcs:disable WordPress.Security.NonceVerification.Missing

    if (!empty($_POST['civicrm_basepage_post_submit'])) {
      // Save Base Page.
      $this->form_nonce_check();
      $this->form_save_basepage();
      $this->form_redirect();
    }
    elseif (!empty($_POST['civicrm_shortcode_post_submit'])) {
      // Save Shortcode Mode.
      $this->form_nonce_check();
      $this->form_save_shortcode();
      $this->form_redirect();
    }
    elseif (!empty($_POST['civicrm_email_post_submit'])) {
      // Save Email Sync.
      $this->form_nonce_check();
      $this->form_save_email_sync();
      $this->form_redirect();
    }
    elseif (!empty($_POST['civicrm_permissions_submit'])) {
      // Refresh permissions.
      $this->form_nonce_check();
      $this->civi->users->refresh_capabilities();
      $this->form_redirect();
    }
    elseif (!empty($_POST['civicrm_cache_submit'])) {
      // Clear caches.
      $this->form_nonce_check();
      $this->civi->admin->clear_caches();
      $this->form_redirect();
    }

    // phpcs:enable WordPress.Security.NonceVerification.Recommended
    // phpcs:enable WordPress.Security.NonceVerification.Missing

  }

  /**
   * Save the CiviCRM Base Page Setting.
   *
   * @since 5.34
   */
  private function form_save_basepage() {

    // Bail if there's no valid Post ID.
    // Nonce is checked in self::form_nonce_check().
    // phpcs:disable WordPress.Security.NonceVerification.Missing
    $post_id = empty($_POST['page_id']) ? 0 : (int) sanitize_text_field(wp_unslash($_POST['page_id']));
    // phpcs:enable WordPress.Security.NonceVerification.Missing
    if ($post_id === 0) {
      return;
    }

    // Bail if we don't find a post object.
    $post = get_post($post_id);
    if (!($post instanceof WP_Post)) {
      return;
    }

    // Save the setting.
    civicrm_api3('Setting', 'create', [
      'wpBasePage' => $post->post_name,
    ]);

  }

  /**
   * Save the CiviCRM Shortcode Mode Setting.
   *
   * @since 5.44
   */
  private function form_save_shortcode() {

    // Bail if there is no valid chosen value.
    // Nonce is checked in self::form_nonce_check().
    // phpcs:disable WordPress.Security.NonceVerification.Missing
    $chosen = isset($_POST['shortcode_mode']) ? sanitize_text_field(wp_unslash($_POST['shortcode_mode'])) : 0;
    // phpcs:enable WordPress.Security.NonceVerification.Missing
    if ($chosen === 0 || !in_array($chosen, $this->civi->admin->get_shortcode_modes())) {
      return;
    }

    // Save the setting.
    update_option('shortcode_mode', $chosen);

  }

  /**
   * Save the CiviCRM Email Sync Setting.
   *
   * @since 5.34
   */
  private function form_save_email_sync() {

    // Bail if there is no valid chosen value.
    // Nonce is checked in self::form_nonce_check().
    // phpcs:disable WordPress.Security.NonceVerification.Missing
    $chosen = isset($_POST['sync_email']) ? sanitize_text_field(wp_unslash($_POST['sync_email'])) : 0;
    // phpcs:enable WordPress.Security.NonceVerification.Missing
    if ($chosen === 0) {
      return;
    }

    // Setting is actually a boolean.
    $sync_email = $chosen === 'no' ? FALSE : TRUE;

    // Save the setting.
    civicrm_api3('Setting', 'create', [
      'syncCMSEmail' => $sync_email,
    ]);

  }

  /**
   * Check the nonce.
   *
   * @since 5.34
   */
  private function form_nonce_check() {

    // Do we trust the source of the data?
    check_admin_referer('civicrm_options_form_action', 'civicrm_options_form_nonce');

  }

  /**
   * Redirect to the Settings page with an extra param.
   *
   * @since 5.34
   */
  private function form_redirect() {

    // Our array of arguments.
    $args = [
      'page' => $this->slug,
      'settings-updated' => 'true',
    ];

    // Redirect to our admin page.
    wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));

  }

  // ---------------------------------------------------------------------------
  // AJAX Handlers
  // ---------------------------------------------------------------------------

  /**
   * Save the CiviCRM Base Page Setting.
   *
   * @since 5.34
   */
  public function ajax_save_basepage() {

    // Default response.
    $data = [
      'section' => 'basepage',
      'result' => '',
      'notice' => __('Unable to save the WordPress Base Page.', 'civicrm'),
      'message' => __('Please select a Page from the drop-down for CiviCRM to use as its Base Page. If CiviCRM was able to create one automatically, there should be one with the title "CiviCRM". If not, please select another suitable WordPress Page.', 'civicrm'),
      'saved' => FALSE,
    ];

    // Since this is an AJAX request, check security.
    $result = check_ajax_referer('civicrm_basepage', FALSE, FALSE);
    if ($result === FALSE) {
      $data['notice'] = __('Authentication failed. Unable to save the WordPress Base Page.', 'civicrm');
      wp_send_json($data);
    }

    // Bail if there's no valid Post ID.
    $post_id = empty($_POST['value']) ? 0 : (int) sanitize_text_field(wp_unslash($_POST['value']));
    if ($post_id === 0) {
      $data['notice'] = __('No Page ID detected. Unable to save the WordPress Base Page.', 'civicrm');
      wp_send_json($data);
    }

    // Bail if we don't find a post object.
    $post = get_post($post_id);
    if (!($post instanceof WP_Post)) {
      $data['notice'] = __('Could not find selected Page. Unable to save the WordPress Base Page.', 'civicrm');
      wp_send_json($data);
    }

    // Save the setting.
    civicrm_api3('Setting', 'create', [
      'wpBasePage' => $post->post_name,
    ]);

    // Retrieve the setting in case hook callbacks have altered it.
    // TODO: find out why this *doesn't* change when hooks *do* change it.
    $actual = civicrm_api3('Setting', 'getvalue', [
      'name' => 'wpBasePage',
      'group' => 'CiviCRM Preferences',
    ]);

    // Query for our Base Page.
    $pages = get_posts([
      'post_type' => 'page',
      'name' => strtolower($actual),
      'post_status' => 'publish',
      'posts_per_page' => 1,
    ]);

    // Bail if the Base Page was not found.
    if (empty($pages) || !is_array($pages)) {
      $data['notice'] = __('Could not get data for the selected Page.', 'civicrm');
      wp_send_json($data);
    }

    // Grab what should be the only item.
    $basepage = array_pop($pages);

    // Data response.
    $data = [
      'section' => 'basepage',
      'result' => $basepage->ID,
      'message' => __('It appears that your Base Page has been set. Looking good.', 'civicrm'),
      'saved' => TRUE,
    ];

    // Return the data.
    wp_send_json($data);

  }

  /**
   * Save the CiviCRM Shortcode Mode Setting.
   *
   * @since 5.44
   */
  public function ajax_save_shortcode() {

    // Default response.
    $data = [
      'section' => 'shortcode',
      'message' => __('Could not save the selected setting.', 'civicrm'),
      'saved' => FALSE,
    ];

    // Since this is an AJAX request, check security.
    $result = check_ajax_referer('civicrm_shortcode', FALSE, FALSE);
    if ($result === FALSE) {
      $data['notice'] = __('Authentication failed. Could not save the selected setting.', 'civicrm');
      wp_send_json($data);
    }

    // Bail if there is no valid chosen value.
    $chosen = isset($_POST['value']) ? sanitize_text_field(wp_unslash($_POST['value'])) : 0;
    if ($chosen === 0 || !in_array($chosen, $this->civi->admin->get_shortcode_modes())) {
      $data['notice'] = __('Unrecognised parameter. Could not save the selected setting.', 'civicrm');
      wp_send_json($data);
    }

    // Set the Shortcode Mode setting.
    $this->civi->admin->set_shortcode_mode($chosen);

    // Data response.
    $data = [
      'section' => 'shortcode',
      'result' => $chosen,
      'message' => __('Setting saved.', 'civicrm'),
      'saved' => TRUE,
    ];

    // Return the data.
    wp_send_json($data);

  }

  /**
   * Save the CiviCRM Email Sync Setting.
   *
   * @since 5.34
   */
  public function ajax_save_email_sync() {

    // Default response.
    $data = [
      'section' => 'email_sync',
      'message' => __('Could not save the selected setting.', 'civicrm'),
      'saved' => FALSE,
    ];

    // Since this is an AJAX request, check security.
    $result = check_ajax_referer('civicrm_email_sync', FALSE, FALSE);
    if ($result === FALSE) {
      $data['notice'] = __('Authentication failed. Could not save the selected setting.', 'civicrm');
      wp_send_json($data);
    }

    // Bail if there is no valid chosen value.
    $chosen = isset($_POST['value']) ? sanitize_text_field(wp_unslash($_POST['value'])) : 0;
    if ($chosen === 0) {
      $data['notice'] = __('Unrecognised parameter. Could not save the selected setting.', 'civicrm');
      wp_send_json($data);
    }

    // Setting is actually a boolean.
    $sync_email = $chosen === 'no' ? FALSE : TRUE;

    // Save the setting.
    civicrm_api3('Setting', 'create', [
      'syncCMSEmail' => $sync_email,
    ]);

    // Retrieve the setting in case hook callbacks have altered it.
    // TODO: find out why this *doesn't* change when hooks *do* change it.
    $actual = civicrm_api3('Setting', 'getvalue', [
      'name' => 'syncCMSEmail',
      'group' => 'CiviCRM Preferences',
    ]);

    // Data response.
    $data = [
      'section' => 'email_sync',
      'result' => $actual ? 'yes' : 'no',
      'message' => __('Setting saved.', 'civicrm'),
      'saved' => TRUE,
    ];

    // Return the data.
    wp_send_json($data);

  }

  /**
   * Refresh the CiviCRM permissions.
   *
   * @since 5.52
   */
  public function ajax_refresh_permissions() {

    // Default response.
    $data = [
      'section' => 'refresh_permissions',
      'notice' => __('Could not refresh the CiviCRM permissions.', 'civicrm'),
      'saved' => FALSE,
    ];

    // Since this is an AJAX request, check security.
    $result = check_ajax_referer('civicrm_refresh_permissions', FALSE, FALSE);
    if ($result === FALSE) {
      $data['notice'] = __('Authentication failed. Could not refresh the CiviCRM permissions.', 'civicrm');
      wp_send_json($data);
    }

    // Bail if there is no valid chosen value.
    $chosen = isset($_POST['value']) ? sanitize_text_field(wp_unslash($_POST['value'])) : 0;
    if ($chosen === 0 || !in_array($chosen, ['enable', 'disable'])) {
      $data['notice'] = __('Unrecognised parameter. Could not refresh the CiviCRM permissions.', 'civicrm');
      wp_send_json($data);
    }

    // Always refresh the CiviCRM permissions.
    $this->civi->users->refresh_capabilities();

    // Have we enabled the custom role?
    if ($chosen === 'enable') {
      // Create the role if it doesn't exist.
      if (!$this->civi->users->has_custom_role()) {
        $this->civi->users->create_custom_role();
      }
      // Refresh the custom role's permissions.
      $this->civi->users->refresh_custom_role_capabilities();
    }

    // Have we disabled the custom role?
    if ($chosen === 'disable') {
      // Delete the role if it exists.
      if ($this->civi->users->has_custom_role()) {
        $this->civi->users->delete_custom_role();
      }
    }

    // Data response.
    $data = [
      'section' => 'refresh_permissions',
      'notice' => __('CiviCRM permissions refreshed.', 'civicrm'),
      'saved' => TRUE,
    ];

    // Return the data.
    wp_send_json($data);

  }

  /**
   * Clear the CiviCRM caches.
   *
   * @since 5.34
   */
  public function ajax_clear_caches() {

    // Default response.
    $data = [
      'section' => 'clear_caches',
      'notice' => __('Could not clear the CiviCRM caches.', 'civicrm'),
      'saved' => FALSE,
    ];

    // Since this is an AJAX request, check security.
    $result = check_ajax_referer('civicrm_clear_caches', FALSE, FALSE);
    if ($result === FALSE) {
      $data['notice'] = __('Authentication failed. Could not clear the CiviCRM caches.', 'civicrm');
      wp_send_json($data);
    }

    // Bail if there is no valid value.
    $chosen = isset($_POST['value']) ? (int) sanitize_text_field(wp_unslash($_POST['value'])) : 0;
    if ($chosen !== 1) {
      $data['notice'] = __('Unrecognised parameter. Could not clear the CiviCRM caches.', 'civicrm');
      wp_send_json($data);
    }

    // Go ahead and clear the caches.
    $this->civi->admin->clear_caches();

    // Data response.
    $data = [
      'section' => 'clear_caches',
      'notice' => __('CiviCRM caches cleared.', 'civicrm'),
      'saved' => TRUE,
    ];

    // Return the data.
    wp_send_json($data);

  }

}
