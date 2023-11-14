<?php
/**
 * WP-CLI integration based on CiviCRM `cv` functionality.
 *
 * @see https://github.com/civicrm/cv
 * @see https://github.com/christianwach/cli-tools-for-civicrm
 *
 * @since 5.69
 */

/*
 * Check constants here because it is possible to include this file early using
 * the `--require` flag. The command may also be bundled as a "package" which
 * loads before WordPress itself.
 *
 * @see https://make.wordpress.org/cli/handbook/references/config/#global-parameters
 * @see https://make.wordpress.org/cli/handbook/guides/commands-cookbook/#overview
 * @see https://make.wordpress.org/cli/handbook/guides/sharing-wp-cli-packages/
 */

// Bail if WP-CLI is not present.
if (!class_exists('WP_CLI')) {
  return;
}

// Bail if either legacy or current WP-CLI tools are already loaded.
if (class_exists('CiviCRM_Command') || class_exists('CLI_Tools_CiviCRM_Command')) {
  return;
}

// Bail if identifying constant is already set.
if (defined('CIVICRM_WPCLI_LOADED')) {
  return;
}

// Make this the one true command.
define('CIVICRM_WPCLI_LOADED', 1);

// Set up commands.
WP_CLI::add_hook('before_wp_load', function() {

  // Include files.
  require_once __DIR__ . '/commands/command-base.php';
  require_once __DIR__ . '/commands/command-civicrm.php';
  require_once __DIR__ . '/commands/command-core.php';
  require_once __DIR__ . '/commands/command-api-v3.php';
  require_once __DIR__ . '/commands/command-cache.php';
  require_once __DIR__ . '/commands/command-db.php';
  require_once __DIR__ . '/commands/command-debug.php';
  require_once __DIR__ . '/commands/command-job.php';
  require_once __DIR__ . '/commands/command-pipe.php';

  // ----------------------------------------------------------------------------
  // Add commands.
  // ----------------------------------------------------------------------------

  // Add top-level commands.
  WP_CLI::add_command('civicrm', 'CLI_Tools_CiviCRM_Command');
  WP_CLI::add_command('cv', 'CLI_Tools_CiviCRM_Command');

  // Add default API command.
  WP_CLI::add_command('civicrm api', 'CLI_Tools_CiviCRM_Command_API_V3', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_API_V3::check_dependencies']);
  WP_CLI::add_command('cv api', 'CLI_Tools_CiviCRM_Command_API_V3', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_API_V3::check_dependencies']);

  // Add API v3 command.
  WP_CLI::add_command('civicrm api3', 'CLI_Tools_CiviCRM_Command_API_V3', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_API_V3::check_dependencies']);
  WP_CLI::add_command('cv api3', 'CLI_Tools_CiviCRM_Command_API_V3', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_API_V3::check_dependencies']);

  // Add Cache Clear command.
  WP_CLI::add_command('civicrm cache', 'CLI_Tools_CiviCRM_Command_Cache', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_Cache::check_dependencies']);
  WP_CLI::add_command('cv cache', 'CLI_Tools_CiviCRM_Command_Cache', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_Cache::check_dependencies']);

  // Add Core command.
  WP_CLI::add_command('civicrm core', 'CLI_Tools_CiviCRM_Command_Core');
  WP_CLI::add_command('cv core', 'CLI_Tools_CiviCRM_Command_Core');

  // Add DB command.
  WP_CLI::add_command('civicrm db', 'CLI_Tools_CiviCRM_Command_DB', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_DB::check_dependencies']);
  WP_CLI::add_command('cv db', 'CLI_Tools_CiviCRM_Command_DB', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_DB::check_dependencies']);

   // Add Debug command.
  WP_CLI::add_command('civicrm debug', 'CLI_Tools_CiviCRM_Command_Debug', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_Debug::check_dependencies']);
  WP_CLI::add_command('cv debug', 'CLI_Tools_CiviCRM_Command_Debug', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_Debug::check_dependencies']);

  // Add Job command.
  WP_CLI::add_command('civicrm job', 'CLI_Tools_CiviCRM_Command_Job', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_Job::check_dependencies']);
  WP_CLI::add_command('cv job', 'CLI_Tools_CiviCRM_Command_Job', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_Job::check_dependencies']);

  // Add Pipe command.
  WP_CLI::add_command('civicrm pipe', 'CLI_Tools_CiviCRM_Command_Pipe', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_Pipe::check_dependencies']);
  WP_CLI::add_command('cv pipe', 'CLI_Tools_CiviCRM_Command_Pipe', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_Pipe::check_dependencies']);

  // ----------------------------------------------------------------------------
  // Add deprecated legacy commands.
  // ----------------------------------------------------------------------------

  // Include legacy files.
  require_once __DIR__ . '/commands/legacy/command-cache-clear.php';
  require_once __DIR__ . '/commands/legacy/command-debug-disable.php';
  require_once __DIR__ . '/commands/legacy/command-debug-enable.php';
  require_once __DIR__ . '/commands/legacy/command-install.php';
  require_once __DIR__ . '/commands/legacy/command-mail-queue.php';
  require_once __DIR__ . '/commands/legacy/command-member-records.php';
  require_once __DIR__ . '/commands/legacy/command-restore.php';
  require_once __DIR__ . '/commands/legacy/command-sql-cli.php';
  require_once __DIR__ . '/commands/legacy/command-sql-conf.php';
  require_once __DIR__ . '/commands/legacy/command-sql-connect.php';
  require_once __DIR__ . '/commands/legacy/command-sql-dump.php';
  require_once __DIR__ . '/commands/legacy/command-sql-query.php';
  require_once __DIR__ . '/commands/legacy/command-update-cfg.php';
  require_once __DIR__ . '/commands/legacy/command-upgrade.php';
  require_once __DIR__ . '/commands/legacy/command-upgrade-db.php';

  // Deprecated: Add Cache Clear command.
  WP_CLI::add_command('civicrm cache-clear', 'CLI_Tools_CiviCRM_Command_Cache_Clear', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_Cache_Clear::check_dependencies']);
  WP_CLI::add_command('cv cache-clear', 'CLI_Tools_CiviCRM_Command_Cache_Clear', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_Cache_Clear::check_dependencies']);

  // Deprecated: Add Debug Disable command.
  WP_CLI::add_command('civicrm disable-debug', 'CLI_Tools_CiviCRM_Command_Debug_Disable', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_Debug_Disable::check_dependencies']);
  WP_CLI::add_command('cv disable-debug', 'CLI_Tools_CiviCRM_Command_Debug_Disable', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_Debug_Disable::check_dependencies']);

  // Deprecated: Add Debug Enable command.
  WP_CLI::add_command('civicrm enable-debug', 'CLI_Tools_CiviCRM_Command_Debug_Enable', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_Debug_Enable::check_dependencies']);
  WP_CLI::add_command('cv enable-debug', 'CLI_Tools_CiviCRM_Command_Debug_Enable', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_Debug_Enable::check_dependencies']);

  // Deprecated: Add Install command.
  WP_CLI::add_command('civicrm install', 'CLI_Tools_CiviCRM_Command_Install');
  WP_CLI::add_command('cv install', 'CLI_Tools_CiviCRM_Command_Install');

  // Deprecated: Add Process Mail Queue command.
  WP_CLI::add_command('civicrm process-mail-queue', 'CLI_Tools_CiviCRM_Command_Mail_Queue', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_Mail_Queue::check_dependencies']);
  WP_CLI::add_command('cv process-mail-queue', 'CLI_Tools_CiviCRM_Command_Mail_Queue', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_Mail_Queue::check_dependencies']);

  // Deprecated: Add Member Records command.
  WP_CLI::add_command('civicrm member-records', 'CLI_Tools_CiviCRM_Command_Member_Records', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_Member_Records::check_dependencies']);
  WP_CLI::add_command('cv member-records', 'CLI_Tools_CiviCRM_Command_Member_Records', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_Member_Records::check_dependencies']);

  // Deprecated: Add Restore command.
  WP_CLI::add_command('civicrm restore', 'CLI_Tools_CiviCRM_Command_Restore', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_Restore::check_dependencies']);
  WP_CLI::add_command('cv restore', 'CLI_Tools_CiviCRM_Command_Restore', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_Restore::check_dependencies']);

  // Deprecated: Add SQL CLI command.
  WP_CLI::add_command('civicrm sql-cli', 'CLI_Tools_CiviCRM_Command_SQL_CLI', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_SQL_CLI::check_dependencies']);
  WP_CLI::add_command('cv sql-cli', 'CLI_Tools_CiviCRM_Command_SQL_CLI', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_SQL_CLI::check_dependencies']);

  // Deprecated: Add SQL Config command.
  WP_CLI::add_command('civicrm sql-conf', 'CLI_Tools_CiviCRM_Command_SQL_Conf', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_SQL_Conf::check_dependencies']);
  WP_CLI::add_command('cv sql-conf', 'CLI_Tools_CiviCRM_Command_SQL_Conf', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_SQL_Conf::check_dependencies']);

  // Deprecated: Add SQL Connect command.
  WP_CLI::add_command('civicrm sql-connect', 'CLI_Tools_CiviCRM_Command_SQL_Connect', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_SQL_Connect::check_dependencies']);
  WP_CLI::add_command('cv sql-connect', 'CLI_Tools_CiviCRM_Command_SQL_Connect', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_SQL_Connect::check_dependencies']);

  // Deprecated: Add SQL Dump command.
  WP_CLI::add_command('civicrm sql-dump', 'CLI_Tools_CiviCRM_Command_SQL_Dump', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_SQL_Dump::check_dependencies']);
  WP_CLI::add_command('cv sql-dump', 'CLI_Tools_CiviCRM_Command_SQL_Dump', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_SQL_Dump::check_dependencies']);

  // Deprecated: Add SQL Query command.
  WP_CLI::add_command('civicrm sql-query', 'CLI_Tools_CiviCRM_Command_SQL_Query', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_SQL_Query::check_dependencies']);
  WP_CLI::add_command('cv sql-query', 'CLI_Tools_CiviCRM_Command_SQL_Query', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_SQL_Query::check_dependencies']);

  // Deprecated: Add Update Config command.
  WP_CLI::add_command('civicrm update-cfg', 'CLI_Tools_CiviCRM_Command_Update_Config', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_Update_Config::check_dependencies']);
  WP_CLI::add_command('cv update-cfg', 'CLI_Tools_CiviCRM_Command_Update_Config', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_Update_Config::check_dependencies']);

  // Deprecated: Add Upgrade command.
  WP_CLI::add_command('civicrm upgrade', 'CLI_Tools_CiviCRM_Command_Upgrade', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_Upgrade::check_dependencies']);
  WP_CLI::add_command('cv upgrade', 'CLI_Tools_CiviCRM_Command_Upgrade', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_Upgrade::check_dependencies']);

  // Deprecated: Add Upgrade DB command.
  WP_CLI::add_command('civicrm upgrade-db', 'CLI_Tools_CiviCRM_Command_Upgrade_DB', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_Upgrade_DB::check_dependencies']);
  WP_CLI::add_command('cv upgrade-db', 'CLI_Tools_CiviCRM_Command_Upgrade_DB', ['before_invoke' => 'CLI_Tools_CiviCRM_Command_Upgrade_DB::check_dependencies']);

  // ----------------------------------------------------------------------------
  // Define CiviCRM paths.
  // ----------------------------------------------------------------------------

  // Set paths early.
  global $civicrm_paths;
  $wp_cli_config = WP_CLI::get_config();

  // If --path is set, save for later use by CiviCRM.
  if (!empty($wp_cli_config['path'])) {
    $civicrm_paths['cms.root']['path'] = $wp_cli_config['path'];
  }

  // If --url is set, save for later use by CiviCRM.
  if (!empty($wp_cli_config['url'])) {
    $civicrm_paths['cms.root']['url'] = $wp_cli_config['url'];
  }

});
