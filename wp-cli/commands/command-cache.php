<?php
/**
 * Flush the CiviCRM cache.
 *
 * ## EXAMPLES
 *
 *     $ wp civicrm cache flush
 *
 * @since 5.69
 */
class CLI_Tools_CiviCRM_Command_Cache extends CLI_Tools_CiviCRM_Command {

  /**
   * Flush the CiviCRM cache.
   *
   * ## EXAMPLES
   *
   *     $ wp civicrm cache flush
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function flush($args, $assoc_args) {

    // Bootstrap CiviCRM.
    $this->bootstrap_civicrm();

    $config = CRM_Core_Config::singleton();

    // Clear db caching.
    $config->clearDBCache();

    // Also cleanup the templates_c directory.
    $config->cleanup(1, FALSE);

    // Also cleanup the session object.
    $session = CRM_Core_Session::singleton();
    $session->reset(1);

    WP_CLI::success('CiviCRM cache cleared.');

  }

}
