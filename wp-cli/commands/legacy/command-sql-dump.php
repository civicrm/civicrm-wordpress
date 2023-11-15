<?php
/**
 * Export the whole CiviCRM database and print to STDOUT or save to a file.
 *
 * ## EXAMPLES
 *
 *     $ wp civicrm sql-dump
 *
 * @since 5.69
 */
class CLI_Tools_CiviCRM_Command_SQL_Dump extends CLI_Tools_CiviCRM_Command {

  /**
   * Dump the whole database that CiviCRM has credentials for and print to STDOUT or save to a file. Deprecated: use `wp civicrm db dump` instead.
   *
   * This command is useful on servers where the user may not have direct access to the `mysqldump`
   * command and the user wants to dump the entire database in which the CiviCRM tables reside.
   * For more granular exports of the CiviCRM tables, functions, procedures and views, use the
   * `wp civicrm db export` command instead.
   *
   * ## OPTIONS
   *
   * [--tables=<tables>]
   * : The comma separated list of specific tables to export. Excluding this parameter will export all tables in the database.
   *
   * [--result-file=<result-file>]
   * : The path to the saved file.
   *
   * ## EXAMPLES
   *
   *     $ wp civicrm sql-dump
   *
   *     $ wp civicrm sql-dump --result-file=/tmp/civi-db.sql
   *     Success: Exported to /tmp/civi-db.sql
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function __invoke($args, $assoc_args) {

    WP_CLI::log(WP_CLI::colorize('%CDeprecated command:%n %cuse `wp civicrm db dump` instead.%n'));

    // Grab associative arguments.
    $tables = \WP_CLI\Utils\get_flag_value($assoc_args, 'tables', FALSE);
    $result_file = \WP_CLI\Utils\get_flag_value($assoc_args, 'result-file', FALSE);

    // Build command.
    $command = 'civicrm db dump' .
      (empty($tables) ? '' : ' --tables=' . $tables) .
      (empty($result_file) ? '' : ' --result-file=' . $result_file);

    // Pass on to "wp civicrm db dump".
    $options = ['launch' => FALSE, 'return' => FALSE];
    WP_CLI::runcommand($command, $options);

  }

}
