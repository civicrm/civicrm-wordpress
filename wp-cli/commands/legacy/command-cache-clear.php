<?php
/**
 * Flush the CiviCRM cache.
 *
 * ## EXAMPLES
 *
 *     $ wp civicrm cache-clear
 *
 * @since 5.69
 */
class CLI_Tools_CiviCRM_Command_Cache_Clear extends CLI_Tools_CiviCRM_Command {

  /**
   * Flush the CiviCRM cache. Deprecated: use `wp civicrm cache flush` instead.
   *
   * ## EXAMPLES
   *
   *     $ wp civicrm cache-clear
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function __invoke($args, $assoc_args) {

    WP_CLI::log(WP_CLI::colorize('%CDeprecated command:%n %cuse `wp civicrm cache flush` instead.%n'));

    // Pass on to "wp civicrm cache flush".
    $options = ['launch' => FALSE, 'return' => FALSE];
    WP_CLI::runcommand('civicrm cache flush', $options);

  }

}
