<?php
/**
 * Get a string which connects to the CiviCRM database.
 *
 * ## EXAMPLES
 *
 *     $ wp civicrm sql-connect
 *     mysql --database=civicrm_db_name --host=db_host --user=db_username --password=db_password
 *
 * @since 5.69
 */
class CLI_Tools_CiviCRM_Command_SQL_Connect extends CLI_Tools_CiviCRM_Command {

  /**
   * Get a string which connects to the CiviCRM database. Deprecated: use `wp civicrm db connect` instead.
   *
   * ## EXAMPLES
   *
   *     $ wp civicrm sql-connect
   *     mysql --database=civicrm_db_name --host=db_host --user=db_username --password=db_password
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function __invoke($args, $assoc_args) {

    WP_CLI::log(WP_CLI::colorize('%CDeprecated command:%n %cuse `wp civicrm db connect` instead.%n'));

    // Pass on to "wp civicrm db connect".
    $options = ['launch' => FALSE, 'return' => FALSE];
    WP_CLI::runcommand('civicrm db connect', $options);

  }

}
