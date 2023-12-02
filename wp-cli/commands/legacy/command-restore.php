<?php
/**
 * Restore the CiviCRM plugin files and database.
 *
 * ## EXAMPLES
 *
 *     $ wp civicrm restore
 *
 * @since 5.69
 */
class CLI_Tools_CiviCRM_Command_Restore extends CLI_Tools_CiviCRM_Command {

  /**
   * Restore the CiviCRM plugin files and database. Deprecated: use `wp civicrm core restore` instead.
   *
   * ## OPTIONS
   *
   * [--restore-dir=<restore-dir>]
   * : Path to your CiviCRM backup directory.
   *
   * [--backup-dir=<backup-dir>]
   * : Path to your CiviCRM backup directory. Default is one level above ABSPATH.
   *
   * [--yes]
   * : Answer yes to the confirmation message.
   *
   * ## EXAMPLES
   *
   *     $ wp civicrm restore --restore-dir=/Users/haystack/Sites/civicrm/attendance.latest/backup/plugins/20230207152318
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function __invoke($args, $assoc_args) {

    // Grab associative arguments.
    $restore_dir = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'restore-dir', '');
    $backup_root_dir = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'backup-dir', '');
    $yes = (bool) \WP_CLI\Utils\get_flag_value($assoc_args, 'yes', FALSE);

    // ----------------------------------------------------------------------------
    // Validate before proceeding.
    // ----------------------------------------------------------------------------

    // Bail when no restore directory is specified.
    if (empty($restore_dir)) {
      WP_CLI::error('You must supply a restore directory.');
    }

    // Bail when no restore directory is found.
    if (!is_dir($restore_dir)) {
      WP_CLI::error('Could not locate the restore directory.');
    }

    // Bail when no SQL file is found.
    $sql_file = $restore_dir . '/civicrm.sql';
    if (!file_exists($sql_file)) {
      WP_CLI::error('Could not locate "civicrm.sql" file in the restore directory.');
    }

    // Bail when no CiviCRM directory is found.
    $code_dir = $restore_dir . '/civicrm';
    if (!is_dir($code_dir)) {
      WP_CLI::error('Could not locate the CiviCRM directory inside "restore-dir".');
    }
    elseif (!file_exists("$code_dir/civicrm/civicrm-version.txt") && !file_exists("$code_dir/civicrm/civicrm-version.php")) {
      WP_CLI::error('The CiviCRM directory inside "restore-dir" does not seem to be a valid CiviCRM codebase.');
    }

    // Get the path to the CiviCRM plugin directory.
    $plugin_path = $this->plugin_path_get();

    // Bootstrap CiviCRM.
    $this->bootstrap_civicrm();

    // Bail if we can't fetch database credentials.
    if (!defined('CIVICRM_DSN')) {
      WP_CLI::error('CIVICRM_DSN is not defined.');
    }

    // Parse the CiviCRM credentials.
    $dsn = DB::parseDSN(CIVICRM_DSN);

    // Build backup directory when not specified.
    if (empty($backup_root_dir)) {
      $backup_root_dir = trailingslashit(dirname(ABSPATH)) . 'backup';
    }

    // Issue warning.
    WP_CLI::log(WP_CLI::colorize('%CDeprecated command:%n %cuse `wp civicrm core backup` and `wp civicrm core restore` instead.%n'));

    WP_CLI::log('');
    WP_CLI::log('Process involves:');
    WP_CLI::log(sprintf("1. Restoring '{$restore_dir}/civicrm' to '%s'.", $plugin_path));
    WP_CLI::log(sprintf("2. Dropping and creating the '%s' database.", $dsn['database']));
    WP_CLI::log("3. Loading the '{$restore_dir}/civicrm.sql' file into the database.");
    WP_CLI::log('');
    WP_CLI::log(sprintf("Note: Before restoring, a backup will be taken in the '%s' directory.", "{$backup_root_dir}/plugins/restore"));
    WP_CLI::log('');

    // Let's give folks a chance to exit now.
    WP_CLI::confirm(WP_CLI::colorize('%GDo you want to continue?%n'), $assoc_args);

    // ----------------------------------------------------------------------------
    // Repeat the backup procedure from `wp civicrm upgrade`.
    // ----------------------------------------------------------------------------

    // Maybe create destination directory.
    $backup_root_dir = untrailingslashit($backup_root_dir);
    if (!is_dir($backup_root_dir)) {
      if (!is_writable(dirname($backup_root_dir))) {
        WP_CLI::error("Insufficient permission to create directory '{$backup_root_dir}'.");
      }
      WP_CLI::log("Creating directory '{$backup_root_dir}'.");
      // Recursively create directory.
      if (!@mkdir($backup_root_dir, 0777, TRUE)) {
        $error = error_get_last();
        WP_CLI::error("Failed to create directory '{$backup_root_dir}': {$error['message']}.");
      }
    }

    // Sanity check.
    if (!is_writable($backup_root_dir)) {
      WP_CLI::error("'{$backup_root_dir}' is not writable by current user.");
    }

    // Build backup filename and path.
    $date = date('YmdHis');
    $filename = 'civicrm';
    $backup_working_dir = trailingslashit($backup_root_dir) . 'plugins/restore/' . $date;
    $backup_sql_file = trailingslashit($backup_working_dir) . $filename . '.sql';
    $backup_plugin_path = trailingslashit($backup_working_dir) . $filename;

    // Create working backup directory.
    if (!@mkdir($backup_working_dir, 0777, TRUE)) {
      $error = error_get_last();
      WP_CLI::error("Failed to create directory '{$backup_working_dir}': {$error['message']}.");
    }

    // Use "wp civicrm sql-dump" to dump database.
    WP_CLI::log('');
    WP_CLI::log(WP_CLI::colorize('%GBacking up database...%n'));
    $options = ['launch' => FALSE, 'return' => FALSE];
    WP_CLI::runcommand("civicrm sql-dump --result-file={$backup_sql_file}", $options);

    // Move existing CiviCRM plugin directory to backup directory.
    WP_CLI::log('');
    WP_CLI::log(WP_CLI::colorize('%GBacking up existing plugin...%n'));
    if (!@rename($plugin_path, $backup_plugin_path)) {
      $error = error_get_last();
      WP_CLI::error(sprintf('Failed to backup CiviCRM plugin directory %s to %s: %s', $plugin_path, $backup_plugin_path, $error['message']));
    }
    WP_CLI::success('Codebase backed up.');

    // ----------------------------------------------------------------------------
    // Restore procedure.
    // ----------------------------------------------------------------------------

    // Move backup CiviCRM plugin directory to plugins directory.
    WP_CLI::log('');
    WP_CLI::log(WP_CLI::colorize('%GRestoring codebase...%n'));
    if (!@rename($code_dir, $plugin_path)) {
      $error = error_get_last();
      WP_CLI::error(sprintf('Failed to restore CiviCRM plugin directory %s to %s: %s', $code_dir, $plugin_path, $error['message']));
    }

    WP_CLI::success('Codebase restored.');

    // Use "wp civicrm db query" and "wp civicrm db import" to restore database.
    WP_CLI::log('');
    WP_CLI::log(WP_CLI::colorize('%GRestoring database...%n'));

    // Use "wp civicrm db query" to drop the CiviCRM database.
    $command = 'civicrm db query ' . sprintf("'DROP DATABASE IF EXISTS %s'", $dsn['database']);
    $options = ['launch' => FALSE, 'return' => FALSE];
    WP_CLI::runcommand($command, $options);
    WP_CLI::success('Database dropped.');

    // Use "run_mysql_command" to re-create the CiviCRM database.
    $mysql_args = [
      'host'     => $dsn['hostspec'],
      'user'     => $dsn['username'],
      'pass' => $dsn['password'],
      'execute'  => sprintf('CREATE DATABASE %s', $dsn['database']),
    ];
    \WP_CLI\Utils\run_mysql_command('/usr/bin/env mysql --no-defaults', $mysql_args);
    WP_CLI::success('Database created.');

    // Load restore tables.
    WP_CLI::log('Loading "civicrm.sql" file from restore directory...');
    $command = 'civicrm db import --load-file=' . $sql_file;
    $options = ['launch' => FALSE, 'return' => FALSE];
    WP_CLI::runcommand($command, $options);
    WP_CLI::success('Database restored.');

    // Clear caches.
    WP_CLI::log('');
    WP_CLI::log(WP_CLI::colorize('%GClearing caches...%n'));
    $command = 'civicrm cache flush';
    $options = ['launch' => FALSE, 'return' => FALSE];
    WP_CLI::runcommand($command, $options);

    WP_CLI::success('Restore process completed.');

  }

}
