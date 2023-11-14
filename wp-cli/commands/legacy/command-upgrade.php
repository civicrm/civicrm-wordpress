<?php
/**
 * Upgrade the CiviCRM plugin files and database.
 *
 * ## EXAMPLES
 *
 *     # Update to the version of CiviCRM in the supplied archive.
 *     $ wp civicrm upgrade --zipfile=~/civicrm-5.57.1-wordpress.zip
 *
 * @since 5.69
 */
class CLI_Tools_CiviCRM_Command_Upgrade extends CLI_Tools_CiviCRM_Command {

  /**
   * Upgrade the CiviCRM plugin files and database. Deprecated: use `wp civicrm core update` instead.
   *
   * ## OPTIONS
   *
   * [--zipfile=<zipfile>]
   * : Path to your CiviCRM zip file.
   *
   * [--tarfile=<tarfile>]
   * : Path to your CiviCRM .tar.gz file. Not currently available.
   *
   * [--backup-dir=<backup-dir>]
   * : Path to your CiviCRM backup directory. Default is one level above ABSPATH.
   *
   * [--v]
   * : Run the upgrade queue with verbose output.
   *
   * [--vv]
   * : Run the upgrade queue with extra verbose output.
   *
   * [--yes]
   * : Answer yes to the confirmation messages.
   *
   * ## EXAMPLES
   *
   *     # Update to the version of CiviCRM in the supplied archive.
   *     $ wp civicrm upgrade --zipfile=~/civicrm-5.57.1-wordpress.zip
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function __invoke($args, $assoc_args) {

    WP_CLI::log(WP_CLI::colorize('%CDeprecated command:%n %cuse `wp civicrm core update` instead.%n'));

    // Grab associative arguments.
    $zipfile = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'zipfile', '');
    $tarfile = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'l10n-tarfile', '');
    $backup_root_dir = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'backup-dir', '');
    $v = (bool) \WP_CLI\Utils\get_flag_value($assoc_args, 'v', FALSE);
    $vv = (bool) \WP_CLI\Utils\get_flag_value($assoc_args, 'vv', FALSE);
    $yes = (bool) \WP_CLI\Utils\get_flag_value($assoc_args, 'yes', FALSE);

    // Bail when .tar.gz archive is specified.
    if (!empty($tarfile)) {
      WP_CLI::error('CiviCRM .tar.gz archives are not supported.');
    }

    // Bail when no .zip archive is specified.
    if (empty($zipfile)) {
      WP_CLI::error('You must supply a CiviCRM zip archive.');
    }

    // Choose higher verbosity when both are specified.
    if (!empty($v) && !empty($vv)) {
      $v = FALSE;
    }

    // ----------------------------------------------------------------------------
    // We can't use "wp civicrm core backup" because of its new backup schema.
    // ----------------------------------------------------------------------------

    // Build backup directory when not specified.
    if (empty($backup_root_dir)) {
      $backup_root_dir = trailingslashit(dirname(ABSPATH)) . 'backup';
    }

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

    // Get the path to the CiviCRM plugin directory.
    $plugin_path = $this->plugin_path_get();

    // Build backup filename and path.
    $date = date('YmdHis');
    $filename = 'civicrm';
    $backup_working_dir = trailingslashit($backup_root_dir) . trailingslashit('plugins') . $date;
    $backup_sql_file = trailingslashit($backup_working_dir) . $filename . '.sql';
    $backup_plugin_path = trailingslashit($backup_working_dir) . $filename;

    WP_CLI::log('');
    WP_CLI::log('The upgrade process involves:');
    WP_CLI::log(sprintf('1. Backing up database as => %s', $backup_sql_file));
    WP_CLI::log(sprintf('2. Backing up current CiviCRM code as => %s', $backup_plugin_path));
    WP_CLI::log(sprintf('3. Unpacking zipfile to => %s', $plugin_path));
    WP_CLI::log('4. Executing "civicrm/upgrade?reset=1" just as a browser would.');
    WP_CLI::log('');

    // Let's give folks a chance to exit now.
    WP_CLI::confirm(WP_CLI::colorize('%GDo you want to continue?%n'), $assoc_args);

    // ----------------------------------------------------------------------------
    // Backup procedure.
    // ----------------------------------------------------------------------------

    // Create working backup directory.
    if (!@mkdir($backup_working_dir, 0777, TRUE)) {
      $error = error_get_last();
      WP_CLI::error("Failed to create directory '{$backup_working_dir}': {$error['message']}.");
    }

    // Use "wp civicrm sql-dump" to dump database.
    $options = ['launch' => FALSE, 'return' => FALSE];
    WP_CLI::runcommand("civicrm sql-dump --result-file={$backup_sql_file}", $options);
    WP_CLI::success('1. Database backed up.');

    // Move existing CiviCRM plugin directory to backup directory.
    if (!@rename($plugin_path, $backup_plugin_path)) {
      $error = error_get_last();
      WP_CLI::error(sprintf('Failed to backup CiviCRM project directory %s to %s: %s', $project_path, $backup_plugin_path, $error['message']));
    }
    WP_CLI::log('');
    WP_CLI::success('2. Code backed up.');

    // ----------------------------------------------------------------------------
    // Subsequent commands can remain the same.
    // ----------------------------------------------------------------------------

    // Use "wp civicrm core update" to upgrade CiviCRM.
    $command = 'civicrm core update --zipfile=' . $zipfile . (empty($yes) ? '' : ' --yes');
    $options = ['launch' => FALSE, 'return' => FALSE];
    WP_CLI::runcommand($command, $options);

    // Use "wp civicrm core update-db" to upgrade the CiviCRM database.
    $command = 'civicrm core update-db' . (empty($v) ? '' : ' --v') . (empty($vv) ? '' : ' --vv') . (empty($yes) ? '' : ' --yes');
    $options = ['launch' => TRUE, 'return' => FALSE];
    WP_CLI::runcommand($command, $options);

  }

}
