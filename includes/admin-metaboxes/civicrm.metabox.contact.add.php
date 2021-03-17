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
 * Define CiviCRM_For_WordPress_Admin_Metabox_Contact_Add Class.
 *
 * @since 5.34
 */
class CiviCRM_For_WordPress_Admin_Metabox_Contact_Add {

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
   * Instance constructor.
   *
   * @since 5.34
   */
  public function __construct() {

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

    // Bail if the current WordPress User cannot add Contacts.
    if (!$this->civi->users->check_civicrm_permission('add_contacts')) {
      return;
    }

    // Add our meta boxes.
    add_action('wp_dashboard_setup', [$this, 'meta_box_add']);

    // Add resources prior to page load.
    add_action('admin_enqueue_scripts', [$this, 'enqueue_js']);
    add_action('admin_enqueue_scripts', [$this, 'enqueue_css']);

    // Register our form submit hander.
    add_action('admin_init', [$this, 'form_submitted']);

    // Register AJAX handler.
    add_action('wp_ajax_civicrm_contact_add', [$this, 'ajax_contact_add']);

  }

  /**
   * Enqueue Javascript on the dashboard.
   *
   * @since 5.34
   *
   * @param str $hook The filename of the displayed screen.
   */
  public function enqueue_js($hook) {

    // Bail if not the dashboard.
    if ('index.php' != $hook) {
      return;
    }

    // Enqueue Javascript.
    wp_enqueue_script(
      'civicrm-contact-add-script',
      CIVICRM_PLUGIN_URL . 'assets/js/civicrm.contact.add.js',
      ['jquery'],
      CIVICRM_PLUGIN_VERSION
    );

    // Init settings and localisation array.
    $vars = [
      'settings' => [
        'ajax_url' => admin_url('admin-ajax.php'),
      ],
      'localisation' => [
        'add' => __('Add Contact', 'civicrm'),
        'adding' => __('Adding...', 'civicrm'),
        'added' => __('Contact Added', 'civicrm'),
      ],
    ];

    // Localise the WordPress way.
    wp_localize_script(
      'civicrm-contact-add-script',
      'CiviCRM_Quick_Add_Vars',
      $vars
    );

  }

  /**
   * Enqueue stylesheet on the dashboard.
   *
   * @since 5.34
   *
   * @param str $hook The filename of the displayed screen.
   */
  public function enqueue_css($hook) {

    // Bail if not the dashboard.
    if ('index.php' != $hook) {
      return;
    }

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
  // Meta Box Loader
  // ---------------------------------------------------------------------------

  /**
   * Register "Quick Add Contact" meta box.
   *
   * @since 5.34
   */
  public function meta_box_add() {

    // Bail if user cannot access CiviCRM.
    if (!current_user_can('access_civicrm')) {
      return;
    }

    // Init data.
    $data = [];

    // Create "Quick Add Contact" metabox.
    add_meta_box(
      'civicrm_metabox_contact_add',
      __('Quick Add Contact to CiviCRM', 'civicrm'),
      // Callback.
      [$this, 'meta_box_render'],
      // Screen ID.
      'dashboard',
      // Column: options are 'normal' and 'side'.
      'side',
      // Vertical placement: options are 'core', 'high', 'low'.
      'high',
      $data
    );

  }

  // ---------------------------------------------------------------------------
  // Meta Box Renderer
  // ---------------------------------------------------------------------------

  /**
   * Render "Quick Add Contact" meta box.
   *
   * @since 5.34
   *
   * @param mixed $unused Unused param.
   * @param array $metabox Array containing id, title, callback, and args elements.
   */
  public function meta_box_render($unused = NULL, $metabox) {

    if (!$this->civi->initialize()) {
      return;
    }

    // Check our session for data.
    $session = CRM_Core_Session::singleton();
    $recents = $session->get('quick_add_recents');

    // Maybe add a class to the "Recently Added" wrapper.
    $visiblity_class = '';
    if (!empty($recents)) {
      $visiblity_class = ' contacts-added';
    }

    // Detect error message.
    $error = '';
    $error_css = ' display: none;';
    if (!empty($_GET['quick-add-error'])) {
      switch ($_GET['quick-add-error']) {

        case 'civicrm':
          $error = __('Failed to init CiviCRM.', 'civicrm');
          break;

        case 'first-name':
          $error = __('Please enter a first name.', 'civicrm');
          break;

        case 'last-name':
          $error = __('Please enter a last name.', 'civicrm');
          break;

        case 'email':
          $error = __('Please enter a valid email.', 'civicrm');
          break;

        case 'api':
          $error = __('Could not create Contact.', 'civicrm');
          break;

        case 'missing':
          $error = __('Could not find the created Contact.', 'civicrm');
          break;

      }

      $error_css = '';
    }

    // Set submit button options.
    $options = [
      'data-security' => esc_attr(wp_create_nonce('civicrm_metabox_contact_add')),
    ];

    // Include template file.
    include CIVICRM_PLUGIN_DIR . 'assets/templates/metaboxes/metabox.contact.add.php';

  }

  // ---------------------------------------------------------------------------
  // Form Handler
  // ---------------------------------------------------------------------------

  /**
   * Perform actions when the form has been submitted.
   *
   * @since 5.34
   */
  public function form_submitted() {

    if (!empty($_POST['civicrm_quick_add_submit'])) {
      // Save Contact.
      $this->form_nonce_check();
      $this->form_save_contact();
      $this->form_redirect();
    }

  }

  /**
   * Save the CiviCRM Basepage Setting.
   *
   * @since 5.34
   */
  public function form_save_contact() {

    if (!$this->civi->initialize()) {
      $this->form_redirect(['quick-add-error' => 'civicrm']);
    }

    // Bail if there's no valid First Name.
    $first_name = empty($_POST['civicrm_quick_add_first_name']) ? '' : trim($_POST['civicrm_quick_add_first_name']);

    if ($first_name === '') {
      $this->form_redirect(['quick-add-error' => 'first-name']);
    }

    // Bail if there's no valid Last Name.
    $last_name = empty($_POST['civicrm_quick_add_last_name']) ? '' : trim($_POST['civicrm_quick_add_last_name']);
    if ($last_name === '') {
      $this->form_redirect(['quick-add-error' => 'last-name']);
    }

    // Bail if there's no valid Email.
    $email = empty($_POST['civicrm_quick_add_email']) ? '' : trim($_POST['civicrm_quick_add_email']);
    if (!is_email($email)) {
      $this->form_redirect(['quick-add-error' => 'email']);
    }

    // Build params to create Contact.
    $params = [
      'version' => 3,
      'contact_type' => 'Individual',
      'first_name' => $first_name,
      'last_name' => $last_name,
      'email' => $email,
    ];

    // Call the API.
    $result = civicrm_api('Contact', 'create', $params);

    // Bail if there's an error.
    if (!empty($result['is_error']) && $result['is_error'] == 1) {
      $this->form_redirect(['quick-add-error' => 'api']);
    }

    // Bail if there are no results.
    if (empty($result['values'])) {
      $this->form_redirect(['quick-add-error' => 'missing']);
    }

    // The result set should contain only one item.
    $contact = array_pop($result['values']);

    // Construct list item containing link to "View Contact" screen.
    $url = $this->civi->admin->get_admin_link('civicrm/contact/view', 'reset=1&cid=' . $contact['id']);
    $link = '<li><a href="' . $url . '" target="_blank">' . $contact['display_name'] . '</a></li>';

    // Check our session for existing data.
    $session = CRM_Core_Session::singleton();
    $recents = $session->get('quick_add_recents');

    // Maybe init array.
    if (empty($recents) || !is_array($recents)) {
      $recents = [$link];
    }
    else {

      // Keep the list to a maximum of 5.
      if (count($recents) > 4) {
        $discard = array_pop($recents);
      }

      // Prepend this link to it.
      array_unshift($recents, $link);

    }

    // Resave data in session.
    $session->set('quick_add_recents', $recents);

  }

  /**
   * Check the nonce.
   *
   * @since 5.34
   */
  private function form_nonce_check() {

    // Do we trust the source of the data?
    check_admin_referer('civicrm_quick_add_action', 'civicrm_quick_add_nonce');

  }

  /**
   * Redirect to the Settings page with an extra param.
   *
   * @since 5.34
   *
   * @param array $args The query arguments.
   */
  private function form_redirect($args = []) {

    // Maybe use default array of arguments.
    if (empty($args)) {
      $args = [
        'contact-added' => 'true',
      ];
    }

    // Redirect to our admin page.
    wp_safe_redirect(add_query_arg($args, admin_url('index.php')));
    exit;

  }

  // ---------------------------------------------------------------------------
  // AJAX Handler
  // ---------------------------------------------------------------------------

  /**
   * Save the Contact details to CiviCRM.
   *
   * @since 5.34
   */
  public function ajax_contact_add() {

    // Default response.
    $data = [
      'notice' => __('Could not save the contact.', 'civicrm'),
      'added' => FALSE,
    ];

    // Since this is an AJAX request, check security.
    $result = check_ajax_referer('civicrm_metabox_contact_add', FALSE, FALSE);
    if ($result === FALSE) {
      $data['notice'] = __('Authentication failed.', 'civicrm');
      wp_send_json($data);
    }

    // Bail if CiviCRM not inited.
    if (!$this->civi->initialize()) {
      $data['notice'] = __('CiviCRM not loaded.', 'civicrm');
      wp_send_json($data);
    }

    // Bail if user cannot create Contacts.
    if (!CRM_Core_Permission::check('add contacts')) {
      $data['notice'] = __('Permission denied.', 'civicrm');
      wp_send_json($data);
    }

    // Bail if there is no valid data.
    $data = isset($_POST['value']) ? (array) $_POST['value'] : [];
    if (empty($data)) {
      $data['notice'] = __('No data received.', 'civicrm');
      wp_send_json($data);
    }

    // Bail if first name is not valid.
    if (empty($data['first_name'])) {
      $data['notice'] = __('Please enter a first name.', 'civicrm');
      wp_send_json($data);
    }

    // Bail if last name is not valid.
    if (empty($data['last_name'])) {
      $data['notice'] = __('Please enter a last name.', 'civicrm');
      wp_send_json($data);
    }

    // Bail if email is not valid.
    if (!is_email($data['email'])) {
      $data['notice'] = __('Please enter a valid email.', 'civicrm');
      wp_send_json($data);
    }

    // Build params to create Contact.
    $params = [
      'version' => 3,
      'contact_type' => 'Individual',
      'first_name' => $data['first_name'],
      'last_name' => $data['last_name'],
      'email' => $data['email'],
    ];

    // Call the API.
    $result = civicrm_api('Contact', 'create', $params);

    // Bail if there's an error.
    if (!empty($result['is_error']) && $result['is_error'] == 1) {
      $data['notice'] = sprintf(__('Could not create Contact: %s', 'civicrm'), $result['error_message']);
      wp_send_json($data);
    }

    // Bail if there are no results.
    if (empty($result['values'])) {
      $data['notice'] = __('Could not find the created Contact.', 'civicrm');
      wp_send_json($data);
    }

    // The result set should contain only one item.
    $contact = array_pop($result['values']);

    // Construct list item containing link to "View Contact" screen.
    $url = $this->civi->admin->get_admin_link('civicrm/contact/view', 'reset=1&cid=' . $contact['id']);
    $link = '<li><a href="' . $url . '" target="_blank">' . $contact['display_name'] . '</a></li>';

    // Check our session for existing data.
    $session = CRM_Core_Session::singleton();
    $recents = $session->get('quick_add_recents');

    // Maybe init array.
    if (empty($recents) || !is_array($recents)) {
      $recents = [$link];
    }
    else {

      // Keep the list to a maximum of 5.
      if (count($recents) > 4) {
        $discard = array_pop($recents);
      }

      // Prepend this link to it.
      array_unshift($recents, $link);

    }

    // Resave data in session.
    $session->set('quick_add_recents', $recents);

    // Data response.
    $data = [
      'notice' => __('Contact added.', 'civicrm'),
      'data' => $contact,
      'link' => $link,
      'saved' => TRUE,
    ];

    // Return the data.
    wp_send_json($data);

  }

}
