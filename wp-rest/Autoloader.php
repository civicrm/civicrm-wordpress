<?php
/**
 * Autoloader class.
 *
 * @since 0.1
 */

namespace CiviCRM_WP_REST;

class Autoloader {

  /**
   * @var string
   * Instance.
   * @since 0.1
   */
  private static $instance = NULL;

  /**
   * @var string
   * Namespace.
   * @since 0.1
   */
  private $namespace = 'CiviCRM_WP_REST';

  /**
   * @var array
   * Autoloader directory sources.
   * @since 0.1
   */
  private static $source_directories = [];

  /**
   * Constructor.
   *
   * @since 0.1
   */
  private function __construct() {

    $this->register_autoloader();

  }

  /**
   * Creates an instance of this class.
   *
   * @since 0.1
   */
  private static function instance() {

    if (!self::$instance) {
      self::$instance = new self();
    }

  }

  /**
   * Adds a directory source.
   *
   * @since 0.1
   * @param string $source_path The source path
   */
  public static function add_source(string $source_path) {

    // make sure we have an instance
    self::instance();

    if (!is_readable(trailingslashit($source_path))) {
      return \WP_Error('civicrm_wp_rest_error', sprintf(__('The source %s is not readable.', 'civicrm'), $source));
    }

    self::$source_directories[] = $source_path;

  }

  /**
   * Registers the autoloader.
   *
   * @since 0.1
   * @return bool Wehather the autoloader has been registered or not
   */
  private function register_autoloader() {

    return spl_autoload_register([$this, 'autoload']);

  }

  /**
   * Loads the classes.
   *
   * @since 0.1
   * @param string $class_name The class name to load
   */
  private function autoload($class_name) {

    $parts = explode('\\', $class_name);

    if ($this->namespace !== $parts[0]) {
      return;
    }

    // remove namespace and join class path
    $class_path = str_replace('_', '-', implode(DIRECTORY_SEPARATOR, array_slice($parts, 1)));

    array_map(function($source_path) use ($class_path) {

      $path = $source_path . $class_path . '.php';

      if (!file_exists($path)) {
        return;
      }

      require $path;

    }, static::$source_directories);

  }

}
