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
   * Export the whole database that CiviCRM has credentials for and print to STDOUT or save to a file.
   *
   * This command is useful on servers where the user may not have direct access to the `mysqldump`
   * command and the user wants to export the entire database in which the CiviCRM tables reside.
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

    // Grab associative arguments.
    $tables = \WP_CLI\Utils\get_flag_value($assoc_args, 'tables', FALSE);

    // Bootstrap CiviCRM.
    $this->bootstrap_civicrm();

    if (!defined('CIVICRM_DSN')) {
      WP_CLI::error('CIVICRM_DSN is not defined.');
    }

    $mysqldump_binary = \WP_CLI\Utils\force_env_on_nix_systems('mysqldump');
    $dsn = DB::parseDSN(CIVICRM_DSN);

    // Build command and escaped shell arguments.
    $command = $mysqldump_binary . " --opt --triggers --routines --events --host={$dsn['hostspec']} --user={$dsn['username']} --password='{$dsn['password']}' %s";
    $command_esc_args = [$dsn['database']];
    if (!empty($tables)) {
      $requested_tables = explode(',', $tables);
      unset($assoc_args['tables']);
      $command .= ' --tables';
      foreach ($requested_tables as $table) {
        $command .= ' %s';
        $command_esc_args[] = trim($table);
      }
    }

    // Process command and escaped shell arguments.
    $escaped_command = call_user_func_array(
      '\WP_CLI\Utils\esc_cmd',
      array_merge(
        [$command],
        $command_esc_args
      )
    );

    \WP_CLI\Utils\run_mysql_command($escaped_command, $assoc_args);

    // Maybe show some feedback.
    $result_file = \WP_CLI\Utils\get_flag_value($assoc_args, 'result-file', FALSE);
    if (!empty($result_file)) {
      WP_CLI::success(sprintf('Exported to %s', $assoc_args['result-file']));
    }

  }

}
