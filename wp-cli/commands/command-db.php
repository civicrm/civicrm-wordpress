<?php
/**
 * Utilities for interacting with the CiviCRM database.
 *
 * ## EXAMPLES
 *
 *     $ wp civicrm db cli
 *
 *     Welcome to the MySQL monitor.  Commands end with ; or \g.
 *     Your MySQL connection id is 180
 *     Server version: 5.7.34 MySQL Community Server (GPL)
 *
 *     mysql>
 *
 *     $ wp civicrm db config --format=table
 *     +----------+----------------+
 *     | Field    | Value          |
 *     +----------+----------------+
 *     | phptype  | mysqli         |
 *     | dbsyntax | mysqli         |
 *     | username | db_username    |
 *     | password | db_password    |
 *     | protocol | tcp            |
 *     | hostspec | localhost      |
 *     | port     | false          |
 *     | socket   | false          |
 *     | database | civicrm_dbname |
 *     | new_link | true           |
 *     +----------+----------------+
 *
 *     $ wp civicrm db query 'select id,name from civicrm_group;'
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
 */
class CLI_Tools_CiviCRM_Command_DB extends CLI_Tools_CiviCRM_Command {

  /**
   * Drop all CiviCRM tables, views, functions and stored procedures from the database.
   *
   * ## OPTIONS
   *
   * [--also-include=<also-include>]
   * : A comma separated list of additional tables to drop based on wildcard search.
   *
   * [--yes]
   * : Answer yes to the confirmation message.
   *
   * ## EXAMPLES
   *
   *     # Clear all CiviCRM entities from the database.
   *     $ wp civicrm db clear
   *     Dropping CiviCRM database tables...
   *     ...
   *
   *     # Use an extra wildcard when some table names are not registered with CiviCRM.
   *     # In this case, also clear tables for the "Canadian Tax Receipts" extension.
   *     $ wp civicrm db clear --also-include='cdntaxreceipts_*'
   *     Dropping CiviCRM database tables...
   *     ...
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function clear($args, $assoc_args) {

    // Let's give folks a chance to bail.
    WP_CLI::confirm(WP_CLI::colorize('%GAre you sure you want to all CiviCRM entities from the database?%n'), $assoc_args);

    // Get all CiviCRM database entities.
    $functions = $this->cividb_functions_get();
    $procedures = $this->cividb_procedures_get();
    $tables = $this->cividb_tables_get($assoc_args);
    $views = $this->cividb_views_get($assoc_args);

    // Get an instance of wpdb with CiviCRM credentials.
    $cividb = $this->cividb_get();
    $cividb->query('SET FOREIGN_KEY_CHECKS = 0');

    // Drop all the CiviCRM database tables.
    if (!empty($tables)) {
      WP_CLI::log('Dropping CiviCRM database tables...');
      foreach ($tables as $table) {
        $query = 'DROP TABLE IF EXISTS ' . \WP_CLI\Utils\esc_sql_ident($table);
        WP_CLI::debug($query, 'civicrm');
        $cividb->query($query);
      }
      WP_CLI::success('CiviCRM database tables dropped.');
    }

    // Drop all the the CiviCRM database views.
    if (!empty($views)) {
      WP_CLI::log('Dropping CiviCRM database views...');
      foreach ($views as $view) {
        $query = 'DROP VIEW IF EXISTS ' . \WP_CLI\Utils\esc_sql_ident($view);
        WP_CLI::debug($query, 'civicrm');
        $cividb->query($query);
      }
      WP_CLI::success('CiviCRM database views dropped.');
    }

    // Drop all the the CiviCRM database functions.
    if (!empty($functions)) {
      WP_CLI::log('Dropping CiviCRM database functions...');
      foreach ($functions as $function) {
        $query = 'DROP FUNCTION IF EXISTS ' . \WP_CLI\Utils\esc_sql_ident($function);
        WP_CLI::debug($query, 'civicrm');
        $cividb->query($query);
      }
      WP_CLI::success('CiviCRM database functions dropped.');
    }

    // Drop all the the CiviCRM database procedures.
    if (!empty($procedures)) {
      WP_CLI::log('Dropping CiviCRM database procedures...');
      foreach ($procedures as $procedure) {
        $query = 'DROP PROCEDURE IF EXISTS ' . \WP_CLI\Utils\esc_sql_ident($procedure);
        WP_CLI::debug($query, 'civicrm');
        $cividb->query($query);
      }
      WP_CLI::success('CiviCRM database procedures dropped.');
    }

    $cividb->query('SET FOREIGN_KEY_CHECKS = 1');

  }

  /**
   * Quickly enter the MySQL command line.
   *
   * ## EXAMPLES
   *
   *     $ wp civicrm db cli
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
  public function cli($args, $assoc_args) {

    // Get CiviCRM credentials.
    $dsn = $this->cividb_dsn_get();

    $mysql_args = [
      'host' => $dsn['hostspec'],
      'database' => $dsn['database'],
      'user' => $dsn['username'],
      'pass' => $dsn['password'],
    ];

    \WP_CLI\Utils\run_mysql_command('mysql --no-defaults', $mysql_args);

  }

  /**
   * Show the CiviCRM database connection details.
   *
   * ## OPTIONS
   *
   * [--format=<format>]
   * : Render output in a particular format.
   * ---
   * default: table
   * options:
   *   - table
   *   - json
   *   - pretty
   * ---
   *
   * ## EXAMPLES
   *
   *     $ wp civicrm db config --format=table
   *     +----------+----------------+
   *     | Field    | Value          |
   *     +----------+----------------+
   *     | phptype  | mysqli         |
   *     | dbsyntax | mysqli         |
   *     | username | db_username    |
   *     | password | db_password    |
   *     | protocol | tcp            |
   *     | hostspec | localhost      |
   *     | port     | false          |
   *     | socket   | false          |
   *     | database | civicrm_dbname |
   *     | new_link | true           |
   *     +----------+----------------+
   *
   * @alias conf
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function config($args, $assoc_args) {

    // Get CiviCRM credentials.
    $dsn = $this->cividb_dsn_get();

    $format = \WP_CLI\Utils\get_flag_value($assoc_args, 'format', 'table');
    switch ($format) {

      // Pretty-print output.
      case 'pretty':
        WP_CLI::log(print_r($dsn, TRUE));
        break;

      // Display output as json.
      case 'json':
        WP_CLI::log(json_encode($dsn));
        break;

      // Display output as table (default).
      case 'table':
      default:
        $assoc_args['format'] = $format;
        $assoc_args['fields'] = array_keys($dsn);
        $formatter = $this->formatter_get($assoc_args);
        $formatter->display_item($dsn);

    }

  }

  /**
   * Get a string which connects to the CiviCRM database.
   *
   * ## EXAMPLES
   *
   *     $ wp civicrm db connect
   *     mysql --database=civicrm_db_name --host=db_host --user=db_username --password=db_password
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function connect($args, $assoc_args) {

    // Get CiviCRM credentials.
    $dsn = $this->cividb_dsn_get();

    $command = sprintf(
      'mysql --database=%s --host=%s --user=%s --password=%s',
      $dsn['database'],
      $dsn['hostspec'],
      $dsn['username'],
      $dsn['password']
    );

    if (isset($dsn['port']) && !empty($dsn['port'])) {
      $command .= ' --port=' . $dsn['port'];
    }

    WP_CLI::log($command);

  }

  /**
   * Drop an entire CiviCRM database.
   *
   * It is not possible to drop the CiviCRM database when it is shared with WordPress.
   *
   * To create a fresh CiviCRM database, use `wp civicrm db import` to load an existing
   * database file or make sure you have the CiviCRM plugin installed (e.g. using the
   *  `wp civicrm core install` command) and then call `wp civicrm core activate`.
   *
   * ## OPTIONS
   *
   * [--yes]
   * : Answer yes to the confirmation message.
   *
   * ## EXAMPLES
   *
   *     # Drop the CiviCRM database.
   *     $ wp civicrm db drop
   *
   *     # Drop the CiviCRM database without the confirm message.
   *     $ wp civicrm db drop --yes
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function drop($args, $assoc_args) {

    // Use "wp civicrm db is-shared" to check if the CiviCRM database is shared with WordPress.
    $command = 'civicrm db is-shared --show-result';
    $options = ['launch' => FALSE, 'return' => TRUE];
    $shared = WP_CLI::runcommand($command, $options);

    // Bail if sharing database with WordPress.
    if (!empty($shared)) {
      WP_CLI::error('You cannot drop the CiviCRM database when it is shared with WordPress.');
    }

    // Let's give folks a chance to bail.
    WP_CLI::confirm(WP_CLI::colorize('%GAre you sure you want to drop the CiviCRM database?%n'), $assoc_args);

    // Get CiviCRM credentials.
    $dsn = $this->cividb_dsn_get();

    // Use "wp civicrm db query" to drop the CiviCRM database.
    $command = 'civicrm db query ' . sprintf('DROP DATABASE IF EXISTS %s', $dsn['database']);
    $options = ['launch' => FALSE, 'return' => FALSE];
    WP_CLI::runcommand($command, $options);

  }

  /**
   * Drop the CiviCRM tables and views from the database.
   *
   * ## OPTIONS
   *
   * [--tables-only]
   * : Drop only tables.
   *
   * [--views-only]
   * : Drop only views.
   *
   * [--also-include=<also-include>]
   * : A comma separated list of additional tables to drop based on wildcard search.
   *
   * [--yes]
   * : Answer yes to the confirmation message.
   *
   * ## EXAMPLES
   *
   *     # Drop all CiviCRM tables and views.
   *     $ wp civicrm db drop-tables
   *
   *     # Drop just the CiviCRM tables.
   *     $ wp civicrm db drop-tables --tables-only
   *
   *     # Drop just the CiviCRM views.
   *     $ wp civicrm db drop-tables --views-only
   *
   *     # Use an extra wildcard when some table names are not registered with CiviCRM.
   *     # In this case, also drop tables for the "Canadian Tax Receipts" extension.
   *     $ wp civicrm db drop-tables --also-include='cdntaxreceipts_*' --tables-only
   *
   * @subcommand drop-tables
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function drop_tables($args, $assoc_args) {

    // Grab associative arguments.
    $tables_only = (bool) \WP_CLI\Utils\get_flag_value($assoc_args, 'tables-only', FALSE);
    $views_only = (bool) \WP_CLI\Utils\get_flag_value($assoc_args, 'views-only', FALSE);

    // Let's give folks a chance to bail.
    if (empty($views_only) && empty($tables_only)) {
      $message = 'tables and views';
    }
    elseif (!empty($views_only)) {
      $message = 'views';
    }
    elseif (!empty($tables_only)) {
      $message = 'tables';
    }
    WP_CLI::confirm(sprintf(WP_CLI::colorize('%GAre you sure you want to drop the CiviCRM %s from the database?%n'), $message), $assoc_args);

    // Get CiviCRM tables and views.
    $tables = $this->cividb_tables_get($assoc_args);
    $views = $this->cividb_views_get($assoc_args);

    // Get an instance of wpdb with CiviCRM credentials.
    $cividb = $this->cividb_get();
    $cividb->query('SET FOREIGN_KEY_CHECKS = 0');

    // Drop all the CiviCRM database tables.
    if (empty($views_only)) {
      WP_CLI::log('Dropping CiviCRM database tables...');
      foreach ($tables as $table) {
        $query = 'DROP TABLE IF EXISTS ' . \WP_CLI\Utils\esc_sql_ident($table);
        WP_CLI::debug($query, 'civicrm');
        $cividb->query($query);
      }
      WP_CLI::success('CiviCRM database tables dropped.');
    }

    // Drop all the the CiviCRM database views.
    if (empty($tables_only)) {
      WP_CLI::log('Dropping CiviCRM database views...');
      foreach ($views as $view) {
        $query = 'DROP VIEW ' . \WP_CLI\Utils\esc_sql_ident($view);
        WP_CLI::debug($query, 'civicrm');
        $cividb->query($query);
      }
      WP_CLI::success('CiviCRM database views dropped.');
    }

    $cividb->query('SET FOREIGN_KEY_CHECKS = 1');

  }

  /**
   * Dump the whole database that CiviCRM has credentials for and print to STDOUT or save to a file.
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
   *     $ wp civicrm db dump
   *
   *     $ wp civicrm db dump --result-file=/tmp/civi-db.sql
   *     Success: Exported to /tmp/civi-db.sql
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function dump($args, $assoc_args) {

    // Grab associative arguments.
    $tables = \WP_CLI\Utils\get_flag_value($assoc_args, 'tables', FALSE);

    // Get CiviCRM credentials.
    $dsn = $this->cividb_dsn_get();

    // Build command and escaped shell arguments.
    $mysqldump_binary = \WP_CLI\Utils\force_env_on_nix_systems('mysqldump');
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

  /**
   * Export the whole CiviCRM database and print to STDOUT or save to a file.
   *
   * By default, CiviCRM loads its tables into the WordPress database but it is also possible
   * to configure CiviCRM to have its own database. To keep things contained, this command
   * only exports the tables, views, triggers, routines and events that are part of CiviCRM.
   *
   * ## OPTIONS
   *
   * [--tables=<tables>]
   * : Comma separated list of tables to export based on wildcard search. Excluding this parameter will export all CiviCRM tables in the database.
   *
   * [--result-file=<result-file>]
   * : The path to the saved file. Excluding this parameter will export to STDOUT.
   *
   * [--also-include=<also-include>]
   * : A comma separated list of additional wildcards to search.
   *
   * ## EXAMPLES
   *
   *     # Export database to STDOUT.
   *     $ wp civicrm db export
   *     -- MySQL dump 10.13  Distrib 5.7.34, for osx11.0 (x86_64)
   *     --
   *     -- Host: localhost    Database: civicrm_db
   *     -- ------------------------------------------------------
   *     -- Server version  5.7.34
   *     ...
   *
   *     # Export database to file.
   *     $ wp civicrm db export --result-file=/tmp/civi-db.sql
   *     Success: Exported to /tmp/civi-db.sql
   *
   *     # Restrict the exported tables using a wildcard argument as a filter.
   *     $ wp civicrm db export --tables='*_log' --result-file=/tmp/civi-db.sql
   *     Success: Exported to /tmp/civi-db.sql
   *
   *     # Use an extra wildcard when some table names are not registered with CiviCRM.
   *     $ wp civicrm db export --also-include='cdntaxreceipts_*' --result-file=/tmp/civi-db.sql
   *     Success: Exported to /tmp/civi-db.sql
   *
   *     # Restrict the exported tables using a wildcard argument as a filter.
   *     # Also uses an extra wildcard when some table names are not registered with CiviCRM.
   *     # In this case, also exports tables for the "Canadian Tax Receipts" extension.
   *     $ wp civicrm db export --tables='*_log' --also-include='cdntaxreceipts_*' --result-file=/tmp/civi-db.sql
   *     Success: Exported to /tmp/civi-db.sql
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function export($args, $assoc_args) {

    // Grab associative arguments.
    $tables = \WP_CLI\Utils\get_flag_value($assoc_args, 'tables', FALSE);
    $also_include = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'also-include', '');

    // Get CiviCRM credentials.
    $dsn = $this->cividb_dsn_get();

    // Do we want only certain CiviCRM tables?
    $table_args = '';
    if (!empty($tables)) {
      $requested_tables = explode(',', $tables);
      foreach ($requested_tables as $table) {
        $table_args .= ' ' . trim($table);
      }
      unset($assoc_args['tables']);
    }

    // Maybe add extra filters.
    $also_include_args = '';
    if (!empty($also_include)) {
      $also_include_args = " --also-include={$also_include}";
      unset($assoc_args['also-include']);
    }

    // Get the list of tables.
    $tables_command = "civicrm db tables{$table_args}{$also_include_args} --format=csv";
    WP_CLI::debug('Tables Command: ' . $tables_command, 'civicrm');
    $options = ['launch' => FALSE, 'return' => TRUE];
    $tables = WP_CLI::runcommand($tables_command, $options);
    $tables = explode(',', $tables);

    // Build command and escaped shell arguments.
    $mysqldump_binary = \WP_CLI\Utils\force_env_on_nix_systems('mysqldump');
    $command = $mysqldump_binary . " --opt --triggers --routines --events --host={$dsn['hostspec']} --user={$dsn['username']} --password='{$dsn['password']}' %s";
    $command_esc_args = [$dsn['database']];
    $command .= ' --tables';
    foreach ($tables as $table) {
      $command .= ' %s';
      $command_esc_args[] = trim($table);
    }

    // Process command and escaped shell arguments.
    $escaped_command = call_user_func_array(
      '\WP_CLI\Utils\esc_cmd',
      array_merge(
        [$command],
        $command_esc_args
      )
    );

    WP_CLI::debug('Final "mysqldump" Command: ' . $escaped_command, 'civicrm');
    \WP_CLI\Utils\run_mysql_command($escaped_command, $assoc_args);

    // Maybe show some feedback.
    $result_file = \WP_CLI\Utils\get_flag_value($assoc_args, 'result-file', FALSE);
    if (!empty($result_file)) {
      WP_CLI::success(sprintf('Exported to %s', $assoc_args['result-file']));
    }

  }

  /**
   * Get the list of CiviCRM functions in the database.
   *
   * ## OPTIONS
   *
   * [<function>...]
   * : List functions based on wildcard search, e.g. 'civicrm_*' or 'civicrm_event?'.
   *
   * [--format=<format>]
   * : Render output in a particular format.
   * ---
   * default: list
   * options:
   *   - list
   *   - json
   *   - csv
   * ---
   *
   * ## EXAMPLES
   *
   *     $ wp civicrm db functions
   *     civicrm_strip_non_numeric
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function functions($args, $assoc_args) {

    // Grab associative arguments.
    $format = \WP_CLI\Utils\get_flag_value($assoc_args, 'format', 'list');

    // Let's use an instance of wpdb with CiviCRM credentials.
    $cividb = $this->cividb_get();

    // Default query.
    $dsn = $this->cividb_dsn_get();
    $functions_sql = "SHOW FUNCTION STATUS WHERE Db = '{$dsn['database']}'";

    // Perform query.
    $functions = $cividb->get_col($functions_sql, 1);

    // Filter by `$args` wildcards.
    if ($args) {
      $functions = $this->names_filter($args, $functions);
    }

    // Render output.
    if ('csv' === $format) {
      WP_CLI::log(implode(',', $functions));
    }
    elseif ('json' === $format) {
      $json = json_encode($functions);
      if (JSON_ERROR_NONE !== json_last_error()) {
        WP_CLI::error(sprintf(WP_CLI::colorize('Failed to encode JSON: %Y%s.%n'), json_last_error_msg()));
      }
      echo $json . "\n";
    }
    else {
      foreach ($functions as $function) {
        WP_CLI::log($function);
      }
    }

  }

  /**
   * Loads a whole CiviCRM database.
   *
   * ## OPTIONS
   *
   * [--load-file=<load-file>]
   * : The path to the database file.
   *
   * ## EXAMPLES
   *
   *     $ wp civicrm db import /tmp/civicrm.sql
   *
   * @alias load
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function import($args, $assoc_args) {

    // Grab associative arguments.
    $load_file = \WP_CLI\Utils\get_flag_value($assoc_args, 'load-file', FALSE);

    // Get CiviCRM credentials.
    $dsn = $this->cividb_dsn_get();

    $mysql_args = [
      'host'     => $dsn['hostspec'],
      'database' => $dsn['database'],
      'user'     => $dsn['username'],
      'pass' => $dsn['password'],
      'execute'  => 'SOURCE ' . $load_file,
    ];

    \WP_CLI\Utils\run_mysql_command('/usr/bin/env mysql', $mysql_args);

  }

  /**
   * Check if CiviCRM shares a database with WordPress.
   *
   * ## OPTIONS
   *
   * [--show-result]
   * : Print the result to STDOUT. Note that the echoed boolean is the reverse of the exit status.
   *
   * ## EXAMPLES
   *
   *     # Check if CiviCRM shares a database with WordPress. Exit status 0 if shared, otherwise 1.
   *     $ wp civicrm db is-shared
   *     $ echo $?
   *     0
   *
   *     # Show whether CiviCRM shares a database with WordPress. Prints 1 if shared, otherwise 0.
   *     $ wp civicrm db is-shared --show-result
   *     1
   *
   *     # Shell command that shows if CiviCRM shares a database with WordPress.
   *     if wp civicrm db is-shared; then echo "Yup"; else echo "Nope"; fi
   *     Nope
   *
   * @subcommand is-shared
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function is_shared($args, $assoc_args) {

    // Grab associative arguments.
    $show = (bool) \WP_CLI\Utils\get_flag_value($assoc_args, 'show-result', FALSE);

    // Get CiviCRM credentials.
    $dsn = $this->cividb_dsn_get();

    // True when host and database name are identical.
    $shared = FALSE;
    if ($dsn['hostspec'] === DB_HOST && $dsn['database'] === DB_NAME) {
      $shared = TRUE;
    }

    // Exit with code if not showing result.
    if (empty($show)) {
      if (!empty($shared)) {
        WP_CLI::halt(0);
      }
      else {
        WP_CLI::halt(1);
      }
    }

    // Show result.
    if (empty($shared)) {
      echo "0\n";
    }
    else {
      echo "1\n";
    }

  }

  /**
   * Get the list of CiviCRM stored procedures in the database.
   *
   * ## OPTIONS
   *
   * [<procedure>...]
   * : List procedures based on wildcard search, e.g. 'civicrm_*'.
   *
   * [--format=<format>]
   * : Render output in a particular format.
   * ---
   * default: list
   * options:
   *   - list
   *   - json
   *   - csv
   * ---
   *
   * ## EXAMPLES
   *
   *     $ wp civicrm db procedures
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function procedures($args, $assoc_args) {

    // Grab associative arguments.
    $format = \WP_CLI\Utils\get_flag_value($assoc_args, 'format', 'list');

    // Let's use an instance of wpdb with CiviCRM credentials.
    $cividb = $this->cividb_get();

    // Default query.
    $dsn = $this->cividb_dsn_get();
    $procedures_sql = "SHOW PROCEDURE STATUS WHERE Db = '{$dsn['database']}'";

    // Perform query.
    $procedures = $cividb->get_col($procedures_sql, 1);

    // Filter by `$args` wildcards.
    if ($args) {
      $procedures = $this->names_filter($args, $procedures);
    }

    // Render output.
    if ('csv' === $format) {
      WP_CLI::log(implode(',', $procedures));
    }
    elseif ('json' === $format) {
      $json = json_encode($procedures);
      if (JSON_ERROR_NONE !== json_last_error()) {
        WP_CLI::error(sprintf(WP_CLI::colorize('Failed to encode JSON: %Y%s.%n'), json_last_error_msg()));
      }
      echo $json . "\n";
    }
    else {
      foreach ($procedures as $procedure) {
        WP_CLI::log($procedure);
      }
    }

  }

  /**
   * Perform a query on the CiviCRM database.
   *
   * ## OPTIONS
   *
   * <query>
   * : The SQL query to perform.
   *
   * ## EXAMPLES
   *
   *     $ wp civicrm db query 'select id,name from civicrm_group;'
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
  public function query($args, $assoc_args) {

    if (!isset($args[0])) {
      WP_CLI::error('No query specified.');
    }

    $query = $args[0];

    // Get CiviCRM credentials.
    $dsn = $this->cividb_dsn_get();

    $mysql_args = [
      'host'     => $dsn['hostspec'],
      'database' => $dsn['database'],
      'user'     => $dsn['username'],
      'pass' => $dsn['password'],
      'execute'  => $query,
    ];

    \WP_CLI\Utils\run_mysql_command('/usr/bin/env mysql --no-defaults', $mysql_args);

  }

  /**
   * Gets a set of CiviCRM tables in the database.
   *
   * ## OPTIONS
   *
   * [<table>...]
   * : List tables based on wildcard search, e.g. 'civicrm_*_group' or 'civicrm_event?'.
   *
   * [--tables-only]
   * : Restrict returned tables to those that are not views.
   *
   * [--views-only]
   * : Restrict returned tables to those that are views.
   *
   * [--also-include=<also-include>]
   * : A comma separated list of additional wildcards to search.
   *
   * [--format=<format>]
   * : Render output in a particular format.
   * ---
   * default: list
   * options:
   *   - list
   *   - json
   *   - csv
   * ---
   *
   * ## EXAMPLES
   *
   *     # Use a wildcard to get matching table names from the set of CiviCRM tables.
   *     $ wp civicrm db tables 'civicrm_*_group' --tables-only
   *     civicrm_campaign_group
   *     civicrm_custom_group
   *     civicrm_dedupe_rule_group
   *     civicrm_mailing_group
   *     civicrm_option_group
   *     civicrm_uf_group
   *
   *     # Use wildcards to get matching view names from the set of CiviCRM multi-lingual views.
   *     % wp civicrm db tables 'civicrm_*_group_de_de' 'civicrm_*_group_en_us' --views-only
   *     civicrm_custom_group_de_de
   *     civicrm_custom_group_en_us
   *     civicrm_option_group_de_de
   *     civicrm_option_group_en_us
   *     civicrm_uf_group_de_de
   *     civicrm_uf_group_en_us
   *
   *     # Use an extra wildcard when some table names are not registered with CiviCRM.
   *     # In this case, include tables for the "Canadian Tax Receipts" extension.
   *     $ wp civicrm db tables '*_log' --also-include='cdntaxreceipts_*' --tables-only
   *     cdntaxreceipts_log
   *     civicrm_action_log
   *     civicrm_job_log
   *     civicrm_log
   *     civicrm_membership_log
   *     civicrm_system_log
   *     civirule_rule_log
   *
   *     # When CiviCRM shares a database with WordPress, use an extra wildcard to include
   *     # WordPress tables in a query. Here `$wpdb->prefix` is set to the default 'wp_'.
   *     % wp civicrm db tables '*_user*' --also-include='wp_*' --tables-only
   *     civicrm_user_job
   *     log_civicrm_user_job
   *     wp_usermeta
   *     wp_users
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function tables($args, $assoc_args) {

    // Grab associative arguments.
    $tables_only = (bool) \WP_CLI\Utils\get_flag_value($assoc_args, 'tables-only', FALSE);
    $views_only = (bool) \WP_CLI\Utils\get_flag_value($assoc_args, 'views-only', FALSE);
    $also_include = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'also-include', '');
    $format = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'format', 'list');

    // Bail if incompatible args have been supplied.
    if (!empty($tables_only) && !empty($views_only)) {
      WP_CLI::error('You cannot supply --tables-only and --views-only at the same time.');
    }

    // Let's use an instance of wpdb with CiviCRM credentials.
    $cividb = $this->cividb_get();

    // Default query.
    $tables_sql = 'SHOW TABLES';

    // Override query with table type restriction if needed.
    if (!empty($tables_only)) {
      $tables_sql = 'SHOW FULL TABLES WHERE Table_Type = "BASE TABLE"';
    }
    elseif (!empty($views_only)) {
      $tables_sql = 'SHOW FULL TABLES WHERE Table_Type = "VIEW"';
    }

    // Perform query.
    $tables = $cividb->get_col($tables_sql, 0);

    // Pre-filter with CiviCRM tables and views only.
    $pre_filter = [
      'civicrm_*',
      'log_civicrm_*',
      'snap_civicrm_*',
    ];

    // Add in any extra wildcard filters.
    if (!empty($also_include)) {
      $wildcards = explode(',', $also_include);
      foreach ($wildcards as $wildcard) {
        $pre_filter[] = trim($wildcard);
      }
    }

    // Pre-filter now.
    $tables = $this->names_filter($pre_filter, $tables);

    // When tables are part of the query, add tables that are present in civicrm tables.
    if (empty($views_only)) {
      $civicrm_tables = array_keys(CRM_Core_DAO_AllCoreTables::tables());
      foreach ($civicrm_tables as $table) {
        if (!in_array($table, $tables)) {
          $tables[] = $table;
        }
      }
    }

    // Filter by `$args` wildcards.
    if ($args) {
      $tables = $this->names_filter($args, $tables);
    }

    // Render output.
    if ('csv' === $format) {
      WP_CLI::log(implode(',', $tables));
    }
    elseif ('json' === $format) {
      $json = json_encode($tables);
      if (JSON_ERROR_NONE !== json_last_error()) {
        WP_CLI::error(sprintf(WP_CLI::colorize('Failed to encode JSON: %Y%s.%n'), json_last_error_msg()));
      }
      echo $json . "\n";
    }
    else {
      foreach ($tables as $table) {
        WP_CLI::log($table);
      }
    }

  }

  // ----------------------------------------------------------------------------
  // Private methods.
  // ----------------------------------------------------------------------------

  /**
   * Gets the instance of wpdb with CiviCRM credentials.
   *
   * @since 5.69
   *
   * @return object $cividb The instance of wpdb with CiviCRM credentials.
   */
  private function cividb_get() {

    // Return instance if we have it.
    static $cividb;
    if (isset($cividb)) {
      return $cividb;
    }

    // Let's use an instance of wpdb with CiviCRM credentials.
    $dsn = $this->cividb_dsn_get();
    $cividb = new wpdb($dsn['username'], $dsn['password'], $dsn['database'], $dsn['hostspec']);

    return $cividb;

  }

  /**
   * Gets the CiviCRM database credentials.
   *
   * @since 5.69
   *
   * @return array $dsn The array of CiviCRM database credentials.
   */
  private function cividb_dsn_get() {

    // Bootstrap CiviCRM.
    $this->bootstrap_civicrm();

    // Bail if we can't fetch database credentials.
    if (!defined('CIVICRM_DSN')) {
      WP_CLI::error('CIVICRM_DSN is not defined.');
    }

    // Parse the CiviCRM credentials.
    $dsn = DB::parseDSN(CIVICRM_DSN);

    return $dsn;

  }

  /**
   * Gets the CiviCRM database functions.
   *
   * @since 5.69
   *
   * @return array $functions The array of CiviCRM database functions.
   */
  private function cividb_functions_get() {

    // Use "wp civicrm db functions" to find the CiviCRM database functions.
    $command = "civicrm db functions 'civicrm_*' --format=json";
    $options = ['launch' => FALSE, 'return' => TRUE];
    $core_functions = WP_CLI::runcommand($command, $options);

    // Convert to array.
    $functions = json_decode($core_functions, TRUE);
    if (JSON_ERROR_NONE !== json_last_error()) {
      WP_CLI::error(sprintf(WP_CLI::colorize('Failed to decode JSON: %y%s.%n'), json_last_error_msg()));
    }

    return $functions;

  }

  /**
   * Gets the CiviCRM database procedures.
   *
   * @since 5.69
   *
   * @return array $procedures The array of CiviCRM database procedures.
   */
  private function cividb_procedures_get() {

    // Use "wp civicrm db procedures" to find the CiviCRM database procedures.
    $command = 'civicrm db procedures --format=json';
    $options = ['launch' => FALSE, 'return' => TRUE];
    $core_procedures = WP_CLI::runcommand($command, $options);

    // Convert to array.
    $procedures = json_decode($core_procedures, TRUE);
    if (JSON_ERROR_NONE !== json_last_error()) {
      WP_CLI::error(sprintf(WP_CLI::colorize('Failed to decode JSON: %y%s.%n'), json_last_error_msg()));
    }

    return $procedures;

  }

  /**
   * Gets the CiviCRM database tables.
   *
   * @since 5.69
   *
   * @param array $assoc_args The WP-CLI associative arguments.
   * @return array $tables The array of CiviCRM database tables.
   */
  private function cividb_tables_get($assoc_args) {

    // Maybe add extra filters.
    $also_include = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'also-include', '');
    $also_include_args = '';
    if (!empty($also_include)) {
      $also_include_args = " --also-include={$also_include}";
    }

    // Use "wp civicrm db tables" to find the CiviCRM database tables.
    $command = "civicrm db tables{$also_include_args} --tables-only --format=json";
    $options = ['launch' => FALSE, 'return' => TRUE];
    $core_tables = WP_CLI::runcommand($command, $options);

    // Convert to array.
    $tables = json_decode($core_tables, TRUE);
    if (JSON_ERROR_NONE !== json_last_error()) {
      WP_CLI::error(sprintf(WP_CLI::colorize('Failed to decode JSON: %y%s.%n'), json_last_error_msg()));
    }

    return $tables;

  }

  /**
   * Gets the CiviCRM database views.
   *
   * @since 5.69
   *
   * @param array $assoc_args The WP-CLI associative arguments.
   * @return array $views The array of CiviCRM database views.
   */
  private function cividb_views_get($assoc_args) {

    // Maybe add extra filters.
    $also_include = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'also-include', '');
    $also_include_args = '';
    if (!empty($also_include)) {
      $also_include_args = " --also-include={$also_include}";
    }

    // Use "wp civicrm db tables" to find the CiviCRM database views.
    $command = "civicrm db tables 'civicrm_*'{$also_include_args} --views-only --format=json";
    $options = ['launch' => FALSE, 'return' => TRUE];
    $core_views = WP_CLI::runcommand($command, $options);

    // Convert to array.
    $views = json_decode($core_views, TRUE);
    if (JSON_ERROR_NONE !== json_last_error()) {
      WP_CLI::error(sprintf(WP_CLI::colorize('Failed to decode JSON: %y%s.%n'), json_last_error_msg()));
    }

    return $views;

  }

  /**
   * Filters an array of CiviCRM database entity names.
   *
   * @since 5.69
   *
   * @param array $wildcards The array of wildcards.
   * @param array $tables The array of CiviCRM table names.
   * @return array $filtered The filtered array of CiviCRM table names.
   */
  private function names_filter($wildcards, $tables) {

    // Build filtered array.
    $args_tables = [];
    foreach ($wildcards as $wildcard) {
      if (FALSE !== strpos($wildcard, '*') || FALSE !== strpos($wildcard, '?')) {
        $args_tables = array_merge(
          $args_tables,
          array_filter(
            $tables,
            function ($v) use ($wildcard) {
              // WP-CLI itself uses fnmatch() so ignore the civilint warning.
              // phpcs:disable
              return fnmatch($wildcard, $v);
              // phpcs:enable
            }
          )
        );
      }
      else {
        $args_tables[] = $wildcard;
      }
    }

    // Clean up.
    $args_tables = array_values(array_unique($args_tables));
    $filtered = array_values(array_intersect($tables, $args_tables));

    return $filtered;

  }

}
