<?php
/**
 * Quickly enter the MySQL command line.
 *
 * ## EXAMPLES
 *
 *     $ wp civicrm sql-cli
 *
 * @since 5.69
 */
class CLI_Tools_CiviCRM_Command_SQL_CLI extends CLI_Tools_CiviCRM_Command {

  /**
   * Quickly enter the MySQL command line. Deprecated: use `wp civicrm db cli` instead.
   *
   * ## EXAMPLES
   *
   *     $ wp civicrm sql-cli
   *
   *     Welcome to the MySQL monitor.  Commands end with ; or \g.
   *     Your MySQL connection id is 180
   *     Server version: 5.7.34 MySQL Community Server (GPL)
   *
   *     mysql>
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function __invoke($args, $assoc_args) {

    WP_CLI::log(WP_CLI::colorize('%CDeprecated command:%n %cuse `wp civicrm db cli` instead.%n'));

    // Pass on to "wp civicrm db cli".
    $options = ['launch' => FALSE, 'return' => FALSE];
    WP_CLI::runcommand('civicrm db cli', $options);

  }

}
