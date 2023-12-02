<?php
/**
 * Show the CiviCRM database connection details.
 *
 * ## EXAMPLES
 *
 *     $ wp civicrm sql-conf
 *
 * @since 5.69
 */
class CLI_Tools_CiviCRM_Command_SQL_Conf extends CLI_Tools_CiviCRM_Command {

  /**
   * Show the CiviCRM database connection details. Deprecated: use `wp civicrm db config` instead.
   *
   * ## EXAMPLES
   *
   *     $ wp civicrm sql-conf
   *
   * @alias conf
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function __invoke($args, $assoc_args) {

    WP_CLI::log(WP_CLI::colorize('%CDeprecated command:%n %cuse `wp civicrm db config` instead.%n'));

    // Pass on to "wp civicrm db config".
    $options = ['launch' => FALSE, 'return' => FALSE];
    WP_CLI::runcommand('civicrm db config --format=pretty', $options);

  }

}
