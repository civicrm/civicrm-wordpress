<?php
/**
 * Zip extractor class.
 *
 * @since 5.69
 */

// Make sure WP_Upgrader exists.
if (!class_exists('WP_Upgrader')) {
  require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
}

/**
 * Zip extractor class.
 *
 * @since 5.69
 */
class CLI_Tools_CiviCRM_Zip_Extractor extends WP_Upgrader {

  /**
   * @var bool
   * Whether a bulk upgrade/installation is being performed.
   * @since 5.69
   * @access public
   */
  public $bulk = FALSE;

  /**
   * Initializes the extract strings.
   *
   * @since 5.69
   */
  public function extract_strings() {
    $this->strings['unpack_package'] = 'Unpacking the archive...';
    $this->strings['installing_package'] = 'Installing the archive...';
    $this->strings['remove_old'] = 'Removing the existing directory...';
    $this->strings['remove_old_failed'] = 'Could not remove the existing directory.';
    $this->strings['process_failed'] = 'Extraction failed.';
    $this->strings['process_success'] = 'Extraction completed successfully.';
  }

  /**
   * Extracts a zip archive to a directory.
   *
   * @since 5.69
   *
   * @param string $zipfile The path to the zipfile.
   * @param string $destination The directory name to extract to.
   * @param array $settings The array of extraction settings.
   * @return array|false|WP_Error The result on success, otherwise a WP_Error, or false if unable to connect to the filesystem.
   */
  public function extract($zipfile, $destination, $settings) {

    $this->extract_strings();

    $options = [
      'package' => $zipfile,
      'destination' => untrailingslashit($destination),
    ];

    $defaults = [
      'clear_destination' => TRUE,
      'clear_working' => TRUE,
      'abort_if_destination_exists' => FALSE,
    ];

    $settings = wp_parse_args($settings, $defaults);

    $options = $options + $settings;

    return $this->run($options);

  }

}
