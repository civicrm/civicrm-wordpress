<?php
/**
 * Manage CiviCRM through the command-line.
 *
 * ## EXAMPLES
 *
 *     # Download the latest stable CiviCRM core archive.
 *     $ wp civicrm core download
 *     Checking file to download...
 *     Downloading file...
 *     Success: CiviCRM downloaded to /tmp/
 *
 *     # Install the current stable version of CiviCRM with localization files.
 *     $ wp civicrm core install --l10n
 *     Success: Installed 1 of 1 plugins.
 *     Success: CiviCRM localization downloaded and extracted to: /wp-content/plugins/civicrm
 *
 *     # Check for the latest stable version of CiviCRM.
 *     $ wp civicrm core check-update
 *     +-----------+---------+-------------------------------------------------------------------------------------------+
 *     | Package   | Version | Package URL                                                                               |
 *     +-----------+---------+-------------------------------------------------------------------------------------------+
 *     | WordPress | 5.67.0  | https://storage.googleapis.com/civicrm/civicrm-stable/5.67.0/civicrm-5.67.0-wordpress.zip |
 *     | L10n      | 5.67.0  | https://storage.googleapis.com/civicrm/civicrm-stable/5.67.0/civicrm-5.67.0-l10n.tar.gz   |
 *     +-----------+---------+-------------------------------------------------------------------------------------------+
 *
 *     # Check the CiviCRM database config.
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
 * @since 5.69
 */
class CLI_Tools_CiviCRM_Command extends CLI_Tools_CiviCRM_Command_Base {

  /**
   * Adds our description and sub-commands.
   *
   * @since 5.69
   *
   * @param object $command The command.
   * @return array $info The array of information about the command.
   */
  private function command_to_array($command) {

    $info = [
      'name' => $command->get_name(),
      'description' => $command->get_shortdesc(),
      'longdesc' => $command->get_longdesc(),
    ];

    foreach ($command->get_subcommands() as $subcommand) {
      $info['subcommands'][] = $this->command_to_array($subcommand);
    }

    if (empty($info['subcommands'])) {
      $info['synopsis'] = (string) $command->get_synopsis();
    }

    return $info;

  }

}
