<?php
/**
 * Perform a query on the CiviCRM database.
 *
 * ## EXAMPLES
 *
 *     $ wp civicrm sql-query 'select id,name from civicrm_group;'
 *
 * @since 5.69
 */
class CLI_Tools_CiviCRM_Command_SQL_Query extends CLI_Tools_CiviCRM_Command {

  /**
   * Perform a query on the CiviCRM database. Deprecated: use `wp civicrm db query` instead.
   *
   * ## OPTIONS
   *
   * <query>
   * : The SQL query to perform.
   *
   * ## EXAMPLES
   *
   *     $ wp civicrm sql-query 'select id,name from civicrm_group;'
   *     +----+---------------------------+
   *     | id | name                      |
   *     +----+---------------------------+
   *     |  1 | Administrators            |
   *     |  4 | Advisory Board            |
   *     |  2 | Newsletter Subscribers    |
   *     |  3 | Summer Program Volunteers |
   *     +----+---------------------------+
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function __invoke($args, $assoc_args) {

    WP_CLI::log(WP_CLI::colorize('%CDeprecated command:%n %cuse `wp civicrm db query` instead.%n'));

    // Pass on to "wp civicrm db query".
    $options = ['launch' => FALSE, 'return' => FALSE];
    $command = 'civicrm db query' . (empty($args[0]) ? '' : " '" . $args[0] . "'");
    WP_CLI::runcommand($command, $options);

  }

}
