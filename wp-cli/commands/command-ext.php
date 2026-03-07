<?php
/**
 * Utilities for interacting with CiviCRM Extensions.
 *
 * ## EXAMPLES
 *
 *     $ wp civicrm ext download org.example.foobar
 *     Gathering Extension information:
 *     +----------+-------------+---------+--------+
 *     | Location | Label       | Version | Status |
 *     +----------+-------------+---------+--------+
 *     | remote   | Foo Bar Baz | 1.5     |        |
 *     +----------+-------------+---------+--------+
 *     Do you want to download the remote Extension? [y/n] y
 *     Success: CiviCRM Extension downloaded.
 *
 *     $ wp civicrm ext list --refresh
 *     Success: CiviCRM Extensions refreshed.
 *
 *     $ wp civicrm ext list --local
 *     +----------+---------------+---------------+---------+--------------+-------------+--------+----------------------------+
 *     | Location | Key           | Name          | Version | Label        | Status      | Type   | Path                       |
 *     +----------+---------------+---------------+---------+--------------+-------------+--------+----------------------------+
 *     | local    | authx         | authx         | 5.71.0  | AuthX        | installed   | module | /path/to/ext/authx         |
 *     | local    | civi_campaign | civi_campaign | 5.71.0  | CiviCampaign | disabled    | module | /path/to/ext/civi_campaign |
 *     |                                              ... more rows ...                                                        |
 *     +----------+---------------+---------------+---------+--------------+-------------+--------+----------------------------+
 *
 *     $ wp civicrm ext update-db
 *     Applying available database upgrades for Extensions.
 *     Success: Database upgrades for Extensions completed.
 *
 * @since 6.14
 */
class CLI_Tools_CiviCRM_Command_Ext extends CLI_Tools_CiviCRM_Command {

  /**
   * Disable a CiviCRM Extension.
   *
   * This command does not output parseable data. For parseable output,
   * consider using `wp civicrm api extension.disable`.
   *
   * ## EXAMPLES
   *
   *     $ wp civicrm ext disable org.example.foobar
   *     Gathering Extension information:
   *     +-------------+---------+-----------+
   *     | Label       | Version | Status    |
   *     +-------------+---------+-----------+
   *     | Foo Bar Baz | 1.5     | installed |
   *     +-------------+---------+-----------+
   *     Do you want to disable the Extension? [y/n] y
   *     Success: CiviCRM Extension disabled.
   *
   * ## OPTIONS
   *
   * <key-or-name>
   * : The extension full key ("org.example.foobar") or short name ("foobar").
   *
   * [--extpath=<extpath>]
   * : Path to the Extension. May use a wildcard (\"*\").
   *
   * [--yes]
   * : Answer yes to the confirmation message. Not needed when called with --quiet.
   *
   * @since 6.14
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function disable($args, $assoc_args) {

    // Grab associative arguments.
    $extpath = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'extpath', '');

    // Grab Extension key.
    $key_or_name = $args[0];

    // Use "wp civicrm ext info" to get info for the Extension.
    $command = 'civicrm ext info ' . $key_or_name . ' --local --format=json';
    $options = ['launch' => FALSE, 'return' => TRUE, 'parse' => 'json', 'exit_error' => FALSE, 'command_args' => ['--quiet']];
    $result = WP_CLI::runcommand($command, $options);

    // Skip if not found.
    if (empty($result)) {
      WP_CLI::error(sprintf(WP_CLI::colorize('%gCould not find Extension:%n %y%s.%n'), $key_or_name));
    }

    // Bail if already disabled.
    foreach ($result as $extension) {
      // There should only be one item in the array.
      if ('disabled' === $extension['status']) {
        WP_CLI::log(sprintf(WP_CLI::colorize('%gExtension already disabled:%n %y%s.%n'), $key_or_name));
        WP_CLI::halt(0);
      }
    }

    // Show existing information.
    if (empty(WP_CLI::get_config('quiet'))) {
      WP_CLI::log(WP_CLI::colorize('%GGathering Extension information:%n'));
      $feedback = [];
      foreach ($result as $extension) {
        $feedback[] = [
          'Label' => $extension['label'],
          'Version' => $extension['version'],
          'Status' => $extension['status'],
        ];
      }
      $assoc_args['format'] = 'table';
      $assoc_args['fields'] = ['Label', 'Version', 'Status'];
      $formatter = $this->formatter_get($assoc_args);
      $formatter->display_items($feedback);

      // Let's give folks a chance to exit.
      WP_CLI::confirm(sprintf(WP_CLI::colorize('%gDo you want to disable Extension:%n %y%s%n%G?%n'), $key_or_name));
    }

    // Let's take as much info as we can from the Extension data.
    foreach ($result as $extension) {
      if ($key_or_name !== $extension['key']) {
        $key_or_name = $extension['key'];
      }
    }

    // Build API vars.
    $vars = 'keys=' . $key_or_name;
    if (!empty($extpath)) {
      $vars .= ' path=' . $extpath;
    }

    // Use "wp civicrm api" to do the enabling.
    $command = 'civicrm api extension.disable ' . $vars . ' --format=json';
    $options = ['launch' => FALSE, 'return' => TRUE, 'parse' => 'json', 'exit_error' => FALSE, 'command_args' => ['--quiet']];
    $result = WP_CLI::runcommand($command, $options);

    // Show error if present.
    if (!empty($result['is_error']) && 1 === (int) $result['is_error']) {
      WP_CLI::error(sprintf(WP_CLI::colorize('Failed to disable CiviCRM Extension: %y%s%n'), $result['error_message']));
    }

    WP_CLI::success('CiviCRM Extension disabled.');

  }

  /**
   * Download a CiviCRM Extension.
   *
   * ## EXAMPLES
   *
   *     $ wp civicrm ext download org.example.foobar
   *     Gathering Extension information:
   *     +----------+-------------+---------+--------+
   *     | Location | Label       | Version | Status |
   *     +----------+-------------+---------+--------+
   *     | remote   | Foo Bar Baz | 1.5     |        |
   *     +----------+-------------+---------+--------+
   *     Do you want to download the remote Extension? [y/n] y
   *     Success: CiviCRM Extension downloaded.
   *
   *     $ wp civicrm ext download foobar --install --quiet
   *
   * ## OPTIONS
   *
   * <key-or-name>
   * : The extension full key ("org.example.foobar") or short name ("foobar").
   *
   * [--install]
   * : Install the specified CiviCRM Extension after downloading.
   *
   * [--yes]
   * : Answer yes to the confirmation message. Not needed when called with --quiet.
   *
   * @alias dl
   *
   * @since 6.14
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function download($args, $assoc_args) {

    // Grab associative arguments.
    $install = (bool) \WP_CLI\Utils\get_flag_value($assoc_args, 'install', FALSE);

    // Tease out key and URL when present.
    $key_or_name = $args[0];
    $url = '';
    if (FALSE !== strpos($args[0], '@')) {
      list ($key_or_name, $url) = explode('@', $args[0], 2);
    }

    // Use "wp civicrm ext info" to get info for the Extension.
    $command = 'civicrm ext info ' . $key_or_name . ' --format=json';
    $options = ['launch' => FALSE, 'return' => TRUE, 'parse' => 'json', 'exit_error' => FALSE, 'command_args' => ['--quiet']];
    $result = WP_CLI::runcommand($command, $options);

    // Skip if not found.
    if (empty($result)) {
      WP_CLI::error(sprintf(WP_CLI::colorize('%gCould not find Extension:%n %y%s.%n'), $key_or_name));
    }

    // Do we have local and remote info?
    $local_exists = FALSE;
    $remote_exists = FALSE;
    foreach ($result as $extension) {
      if ('local' === $extension['location']) {
        $local_exists = TRUE;
      }
      if ('remote' === $extension['location']) {
        $remote_exists = TRUE;
      }
    }

    // Skip if no remote has been found. Could be a custom Extension, for example.
    if ($local_exists && !$remote_exists) {
      WP_CLI::error(sprintf(WP_CLI::colorize('%gCould not find a remote Extension to download:%n %y%s.%n'), $key_or_name));
    }

    // Show existing information.
    if (empty(WP_CLI::get_config('quiet'))) {
      WP_CLI::log(WP_CLI::colorize('%GGathering Extension information:%n'));
      $feedback = [];
      foreach ($result as $extension) {
        $feedback[] = [
          'Location' => $extension['location'],
          'Label' => $extension['label'],
          'Version' => $extension['version'],
          'Status' => $extension['status'],
        ];
      }
      $assoc_args['format'] = 'table';
      $assoc_args['fields'] = ['Location', 'Label', 'Version', 'Status'];
      $formatter = $this->formatter_get($assoc_args);
      $formatter->display_items($feedback);

      // Let's give folks a chance to exit.
      if ($local_exists && $remote_exists) {
        WP_CLI::confirm(WP_CLI::colorize('%GDo you want to overwrite with the remote Extension?%n'), $assoc_args);
      }
      if (!$local_exists && $remote_exists) {
        WP_CLI::confirm(WP_CLI::colorize('%GDo you want to download the remote Extension?%n'), $assoc_args);
      }
    }

    // Let's take as much info as we can from the Extension data.
    foreach ($result as $extension) {
      if ('remote' === $extension['location']) {
        if (empty($url) && !empty($extension['location'])) {
          $url = $extension['downloadUrl'];
        }
        if ($key_or_name !== $extension['key']) {
          $key_or_name = $extension['key'];
        }
      }
    }

    // Build API vars.
    $vars = 'key=' . $key_or_name;
    if (!empty($url)) {
      $vars .= ' url=' . $url;
    }
    if (empty($install)) {
      $vars .= ' install=0';
    }

    // Use "wp civicrm api" to do the download.
    $command = 'civicrm api extension.download ' . $vars . ' --format=json --quiet';
    $options = ['launch' => FALSE, 'return' => TRUE, 'parse' => 'json', 'exit_error' => FALSE, 'command_args' => ['--quiet']];
    $result = WP_CLI::runcommand($command, $options);

    // Show error if present.
    if (!empty($result['is_error']) && 1 === (int) $result['is_error']) {
      WP_CLI::error(sprintf(WP_CLI::colorize('Failed to download CiviCRM Extension: %y%s%n'), $result['error_message']));
    }

    if (empty($install)) {
      WP_CLI::success('CiviCRM Extension downloaded.');
    }
    else {
      WP_CLI::success('CiviCRM Extension downloaded and installed.');
    }

  }

  /**
   * Enable a CiviCRM Extension.
   *
   * This command does not output parseable data. For parseable output,
   * consider using `wp civicrm api extension.enable`.
   *
   * ## EXAMPLES
   *
   *     $ wp civicrm ext enable org.example.foobar
   *     Gathering Extension information:
   *     +-------------+---------+-------------+
   *     | Label       | Version | Status      |
   *     +-------------+---------+-------------+
   *     | Foo Bar Baz | 1.5     | uninstalled |
   *     +-------------+---------+-------------+
   *     Do you want to enable the Extension? [y/n] y
   *     Success: CiviCRM Extension enabled.
   *
   * ## OPTIONS
   *
   * <key-or-name>
   * : The extension full key ("org.example.foobar") or short name ("foobar").
   *
   * [--extpath=<extpath>]
   * : Path to the Extension. May use a wildcard (\"*\").
   *
   * [--yes]
   * : Answer yes to the confirmation message. Not needed when called with --quiet.
   *
   * @since 6.14
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function enable($args, $assoc_args) {

    // Grab associative arguments.
    $extpath = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'extpath', '');

    // Grab Extension key.
    $key_or_name = $args[0];

    // Use "wp civicrm ext info" to get info for the Extension.
    $command = 'civicrm ext info ' . $key_or_name . ' --local --format=json';
    $options = ['launch' => FALSE, 'return' => TRUE, 'parse' => 'json', 'exit_error' => FALSE, 'command_args' => ['--quiet']];
    $result = WP_CLI::runcommand($command, $options);

    // Skip if not found.
    if (empty($result)) {
      WP_CLI::error(sprintf(WP_CLI::colorize('%gCould not find Extension:%n %y%s.%n'), $key_or_name));
    }

    // Bail if already enabled.
    foreach ($result as $extension) {
      // There should only be one item in the array.
      if ('enabled' === $extension['status']) {
        WP_CLI::log(sprintf(WP_CLI::colorize('%gExtension already enabled:%n %y%s.%n'), $key_or_name));
        WP_CLI::halt(1);
      }
    }

    // Show existing information.
    if (empty(WP_CLI::get_config('quiet'))) {
      WP_CLI::log(WP_CLI::colorize('%GGathering Extension information:%n'));
      $feedback = [];
      foreach ($result as $extension) {
        $feedback[] = [
          'Label' => $extension['label'],
          'Version' => $extension['version'],
          'Status' => $extension['status'],
        ];
      }
      $assoc_args['format'] = 'table';
      $assoc_args['fields'] = ['Label', 'Version', 'Status'];
      $formatter = $this->formatter_get($assoc_args);
      $formatter->display_items($feedback);

      // Let's give folks a chance to exit.
      WP_CLI::confirm(WP_CLI::colorize('%GDo you want to enable the Extension?%n'), $assoc_args);
    }

    // Let's take as much info as we can from the Extension data.
    foreach ($result as $extension) {
      if ($key_or_name !== $extension['key']) {
        $key_or_name = $extension['key'];
      }
    }

    // Build API vars.
    $vars = 'keys=' . $key_or_name;
    if (!empty($extpath)) {
      $vars .= ' path=' . $extpath;
    }

    // Use "wp civicrm api" to do the enabling.
    $command = 'civicrm api extension.enable ' . $vars . ' --format=json';
    $options = ['launch' => FALSE, 'return' => TRUE, 'parse' => 'json', 'exit_error' => FALSE, 'command_args' => ['--quiet']];
    $result = WP_CLI::runcommand($command, $options);

    // Show error if present.
    if (!empty($result['is_error']) && 1 === (int) $result['is_error']) {
      WP_CLI::error(sprintf(WP_CLI::colorize('Failed to enable CiviCRM Extension: %y%s%n'), $result['error_message']));
    }

    WP_CLI::success('CiviCRM Extension enabled.');

  }

  /**
   * Get information about a CiviCRM Extension.
   *
   * ## EXAMPLES
   *
   *     $ wp civicrm ext info org.example.foobar
   *     Success: CiviCRM Extension installed.
   *
   * ## OPTIONS
   *
   * <key-or-name>
   * : The extension full key ("org.example.foobar") or short name ("foobar").
   *
   * [--local]
   * : Get information only for a locally installed CiviCRM Extension.
   *
   * [--remote]
   * : Get information only for a remotely available CiviCRM Extension.
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
   * @since 6.14
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function info($args, $assoc_args) {

    // Grab Extension key.
    $key_or_name = $args[0];

    // Grab associative arguments.
    $local = (bool) \WP_CLI\Utils\get_flag_value($assoc_args, 'local', FALSE);
    $remote = (bool) \WP_CLI\Utils\get_flag_value($assoc_args, 'remote', FALSE);
    $format = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'format', 'table');

    // When both args are missing, show both local and remote.
    if (empty($local) && empty($remote)) {
      $local = TRUE;
      $remote = TRUE;
    }

    // When both args are missing, get all Extensions.
    $command = 'civicrm ext list --format=json';
    if (!empty($local) || !empty($remote)) {
      if (!empty($local)) {
        $command .= ' --local';
      }
      if (!empty($remote)) {
        $command .= ' --remote';
      }
    }

    // Get data for the requested Extensions.
    $options = ['launch' => FALSE, 'return' => TRUE, 'parse' => 'json', 'command_args' => ['--quiet']];
    $result = WP_CLI::runcommand($command, $options);

    // Find the Extension if we can.
    $rows = array();
    foreach ($result as $extension) {
      if ($key_or_name === $extension['key'] || $key_or_name === $extension['name']) {
        if (in_array($format, ['pretty', 'json'])) {
          $rows[] = $extension;
        }
        else {
          $rows[] = [
            'Location' => $extension['location'],
            'Key' => $extension['key'],
            'Name' => $extension['name'],
            'Version' => $extension['version'],
            'Label' => $extension['label'],
            'Status' => $extension['status'],
            'Type' => $extension['type'],
            'Path' => $extension['path'],
            'Download URL' => !empty($extension['downloadUrl']) ? $extension['downloadUrl'] : '',
          ];
          if (!empty($remote)) {
            $row['downloadUrl'] = !empty($info->downloadUrl) ? $info->downloadUrl : '';
          }
        }
      }
    }

    // Skip formatting if not found.
    if (empty($rows)) {
      WP_CLI::error(sprintf(WP_CLI::colorize('%gCould not find Extension:%n %y%s.%n'), $key_or_name));
    }

    switch ($format) {

      // Pretty-print output.
      case 'pretty':
        WP_CLI::log(print_r($rows, TRUE));
        break;

      // Display output as json.
      case 'json':
        $json = json_encode($rows);
        if (JSON_ERROR_NONE !== json_last_error()) {
          WP_CLI::error(sprintf(WP_CLI::colorize('Failed to encode JSON: %Y%s.%n'), json_last_error_msg()));
        }
        echo $json . "\n";
        break;

      // Display output as table.
      case 'table':
      default:
        $fields = ['Location', 'Key', 'Name', 'Version', 'Label', 'Status', 'Type', 'Path'];
        if (!empty($remote)) {
          $fields[] = 'Download URL';
        }
        $args = ['format' => $format];
        $formatter = new \WP_CLI\Formatter($args, $fields);
        $formatter->display_items($rows);
        break;

    }

  }

  /**
   * Install a CiviCRM Extension.
   *
   * This command does not output parseable data. For parseable output,
   * consider using `wp civicrm api extension.install`.
   *
   * ## EXAMPLES
   *
   *     $ wp civicrm ext install org.example.foobar
   *     Success: CiviCRM Extension installed.
   *
   * ## OPTIONS
   *
   * <key-or-name>
   * : The extension full key ("org.example.foobar") or short name ("foobar").
   *
   * [--extpath=<extpath>]
   * : Path to the Extension. May use a wildcard (\"*\").
   *
   * [--yes]
   * : Answer yes to the confirmation message. Not needed when called with --quiet.
   *
   * @since 6.14
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function install($args, $assoc_args) {

    // Grab associative arguments.
    $extpath = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'extpath', '');

    // Grab Extension key.
    $key_or_name = $args[0];

    // Use "wp civicrm ext info" to get info for the Extension.
    $command = 'civicrm ext info ' . $key_or_name . ' --local --format=json';
    $options = ['launch' => FALSE, 'return' => TRUE, 'parse' => 'json', 'exit_error' => FALSE, 'command_args' => ['--quiet']];
    $result = WP_CLI::runcommand($command, $options);

    // Skip if not found.
    if (empty($result)) {
      WP_CLI::error(sprintf(WP_CLI::colorize('%gCould not find Extension:%n %y%s.%n'), $key_or_name));
    }

    // Bail if already installed.
    foreach ($result as $extension) {
      // There should only be one item in the array.
      if ('installed' === $extension['status']) {
        WP_CLI::log(sprintf(WP_CLI::colorize('%gExtension already installed:%n %y%s.%n'), $key_or_name));
        WP_CLI::halt(1);
      }
    }

    // Show existing information.
    if (empty(WP_CLI::get_config('quiet'))) {
      WP_CLI::log(WP_CLI::colorize('%GGathering Extension information:%n'));
      $feedback = [];
      foreach ($result as $extension) {
        $feedback[] = [
          'Label' => $extension['label'],
          'Version' => $extension['version'],
          'Status' => $extension['status'],
        ];
      }
      $assoc_args['format'] = 'table';
      $assoc_args['fields'] = ['Label', 'Version', 'Status'];
      $formatter = $this->formatter_get($assoc_args);
      $formatter->display_items($feedback);

      // Let's give folks a chance to exit.
      WP_CLI::confirm(WP_CLI::colorize('%GDo you want to install the Extension?%n'), $assoc_args);
    }

    // Let's take as much info as we can from the Extension data.
    foreach ($result as $extension) {
      if ($key_or_name !== $extension['key']) {
        $key_or_name = $extension['key'];
      }
    }

    // Build API vars.
    $vars = 'keys=' . $key_or_name;
    if (!empty($extpath)) {
      $vars .= ' path=' . $extpath;
    }

    // Use "wp civicrm api" to do the install.
    $command = 'civicrm api extension.install ' . $vars . ' --format=json';
    $options = ['launch' => FALSE, 'return' => TRUE, 'parse' => 'json', 'exit_error' => FALSE, 'command_args' => ['--quiet']];
    $result = WP_CLI::runcommand($command, $options);

    // Show error if present.
    if (!empty($result['is_error']) && 1 === (int) $result['is_error']) {
      WP_CLI::error(sprintf(WP_CLI::colorize('Failed to install CiviCRM Extension: %y%s%n'), $result['error_message']));
    }

    WP_CLI::success('Extension installed.');

  }

  /**
   * List the set of CiviCRM Extensions.
   *
   * ## EXAMPLES
   *
   *     # Show all local extensions.
   *     $ wp civicrm ext list --local
   *     +----------+---------------+---------------+---------+--------------+-------------+--------+----------------------------+
   *     | Location | Key           | Name          | Version | Label        | Status      | Type   | Path                       |
   *     +----------+---------------+---------------+---------+--------------+-------------+--------+----------------------------+
   *     | local    | authx         | authx         | 5.71.0  | AuthX        | installed   | module | /path/to/ext/authx         |
   *     | local    | civi_campaign | civi_campaign | 5.71.0  | CiviCampaign | disabled    | module | /path/to/ext/civi_campaign |
   *     |                                              ... more rows ...                                                        |
   *     +----------+---------------+---------------+---------+--------------+-------------+--------+----------------------------+
   *
   *     # Show only certain fields for local extensions using cv syntax. Note that options must be lowercase.
   *     $  wp cv ext list --l --columns=key,name,status,version
   *     +---------------------------+---------------------------+-------------+-------------+
   *     | Key                       | Name                      | Status      | Version     |
   *     +---------------------------+---------------------------+-------------+-------------+
   *     | authx                     | authx                     | installed   | 6.3.1       |
   *     | chart_kit                 | chart_kit                 | uninstalled | 6.3.1       |
   *     | civi_campaign             | civi_campaign             | installed   | 6.3.1       |
   *     |                                ... more rows ...                                  |
   *     +---------------------------+---------------------------+-------------+-------------+
   *
   *     $ wp civicrm ext list --refresh
   *     Success: CiviCRM Extensions refreshed.
   *
   * ## OPTIONS
   *
   * [--local]
   * : List only locally installed CiviCRM Extensions.
   *
   * [--l]
   * : An alias of --local for old timers more used to cv syntax.
   *
   * [--remote]
   * : List only remotely available CiviCRM Extensions.
   *
   * [--r]
   * : An alias of --remote for old timers more used to cv syntax.
   *
   * [--refresh]
   * : Refresh the list of CiviCRM Extensions.
   *
   * [--fields=<fields>]
   * : Limit the output to specific fields.
   *
   * [--columns=<columns>]
   * : An alias of --fields for old timers more used to cv syntax.
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
   * @since 6.14
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function list($args, $assoc_args) {

    // Grab associative arguments.
    $format = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'format', 'table');
    $local = (bool) \WP_CLI\Utils\get_flag_value($assoc_args, 'local', FALSE);
    $remote = (bool) \WP_CLI\Utils\get_flag_value($assoc_args, 'remote', FALSE);
    $refresh = (bool) \WP_CLI\Utils\get_flag_value($assoc_args, 'refresh', FALSE);
    $fields = \WP_CLI\Utils\get_flag_value($assoc_args, 'fields', []);

    // Support cv syntax.
    $local_old_skool = (bool) \WP_CLI\Utils\get_flag_value($assoc_args, 'l', FALSE);
    if (!empty($local_old_skool)) {
      $local = TRUE;
    }
    $remote_old_skool = (bool) \WP_CLI\Utils\get_flag_value($assoc_args, 'r', FALSE);
    if (!empty($remote_old_skool)) {
      $remote = TRUE;
    }
    $fields_old_skool = \WP_CLI\Utils\get_flag_value($assoc_args, 'columns', []);
    if (!empty($fields_old_skool)) {
      $fields = $fields_old_skool;
    }

    // Bootstrap CiviCRM.
    $this->bootstrap_civicrm();

    // Was a refresh requested?
    if (!empty($refresh)) {

      // Pass on to "wp civicrm api".
      $options = ['launch' => FALSE, 'return' => TRUE, 'parse' => 'json', 'exit_error' => FALSE, 'command_args' => ['--quiet']];
      $result = WP_CLI::runcommand('civicrm api extension.refresh --format=json', $options);

      // How did we do?
      if (!empty($result['is_error']) && 1 === (int) $result['is_error']) {
        WP_CLI::error(sprintf(WP_CLI::colorize('Failed to refresh CiviCRM Extensions: %y%s%n'), $result['error_message']));
      }

      WP_CLI::success('CiviCRM Extensions refreshed.');
      return;

    }

    // When both args are missing, show all.
    if (empty($local) && empty($remote)) {
      $local = TRUE;
      $remote = TRUE;
    }

    $rows = [];

    if (!empty($local)) {
      $keys = \CRM_Extension_System::singleton()->getFullContainer()->getKeys();
      $statuses = \CRM_Extension_System::singleton()->getManager()->getStatuses();
      $mapper = \CRM_Extension_System::singleton()->getMapper();
      foreach ($keys as $key) {
        try {
          $info = $mapper->keyToInfo($key);
        }
        catch (\Exception $e) {
          WP_CLI::warning(sprintf(WP_CLI::colorize('%gCiviCRM reported a problem:%n %y%s%n'), $e->getMessage()));
          WP_CLI::log(WP_CLI::colorize('%gTry refreshing the list of Extensions with `wp civicrm ext list --refresh`.%n'));
          continue;
        }
        if (in_array($format, ['pretty', 'json'])) {
          $row = [
            'location' => 'local',
            'key' => $key,
            'name' => $info->file,
            'version' => $info->version,
            'label' => $info->label,
            'status' => isset($statuses[$key]) ? $statuses[$key] : '',
            'type' => $info->type,
            'path' => $mapper->keyToBasePath($key),
          ];
          if (!empty($remote)) {
            $row['downloadUrl'] = !empty($info->downloadUrl) ? $info->downloadUrl : '';
          }
          $rows[] = $row;
        }
        else {
          $row = [
            'Location' => 'local',
            'Key' => trim($key),
            'Name' => $info->file,
            'Version' => $info->version,
            'Label' => $info->label,
            'Status' => isset($statuses[$key]) ? $statuses[$key] : '',
            'Type' => $info->type,
            'Path' => $mapper->keyToBasePath($key),
          ];
          if (!empty($remote)) {
            $row['Download URL'] = !empty($info->downloadUrl) ? $info->downloadUrl : '';
          }
          $rows[] = $row;
        }
      }
    }

    if (!empty($remote)) {
      try {
        $extensions_remote = $this->extensions_remote_get();
      }
      catch (\Exception $e) {
        WP_CLI::warning(sprintf(WP_CLI::colorize('%gCiviCRM reported a problem:%n %y%s%n'), $e->getMessage()));
        WP_CLI::log(WP_CLI::colorize('%gTry refreshing the list of Extensions with `wp civicrm ext list --refresh`.%n'));
      }
      if (!empty($extensions_remote)) {
        foreach ($this->extensions_remote_get() as $info) {
          if (in_array($format, ['pretty', 'json'])) {
            $rows[] = [
              'location' => 'remote',
              'key' => $info->key,
              'name' => $info->file,
              'version' => $info->version,
              'label' => $info->label,
              'status' => '',
              'type' => $info->type,
              'path' => '',
              'downloadUrl' => $info->downloadUrl,
            ];
          }
          else {
            $rows[] = [
              'Location' => 'remote',
              'Key' => $info->key,
              'Name' => $info->file,
              'Version' => $info->version,
              'Label' => $info->label,
              'Status' => '',
              'Type' => $info->type,
              'Path' => '',
              'Download URL' => $info->downloadUrl,
            ];
          }
        }
      }
    }

    // Build field map.
    $field_map = [
      'location' => 'Location',
      'key' => 'Key',
      'name' => 'Name',
      'version' => 'Version',
      'label' => 'Label',
      'status' => 'Status',
      'type' => 'Type',
      'path' => 'Path',
    ];
    if (!empty($remote)) {
      $field_map['downloadUrl'] = 'Download URL';
    }

    if (!empty($fields)) {

      // Parse fields into sanitised array.
      if (!is_array($fields)) {
        $fields_array = explode(',', $fields);
      }
      $fields_array = array_map('trim', $fields_array);
      $fields_array = array_map('strtolower', $fields_array);

      // Only use those which are present in both.
      $field_keys = array_intersect($fields_array, array_keys($field_map));

      // Warn about missing fields.
      foreach ($fields_array as $field_in_array) {
        if (!in_array($field_in_array, $field_keys, true)) {
          WP_CLI::warning(sprintf(WP_CLI::colorize('Could not find field: %Y%s.%n'), $field_in_array));
        }
      }

      // Rebuild fields array.
      $fields = [];
      foreach ($field_keys as $field_key) {
        if (in_array($format, ['pretty', 'json'])) {
          $fields[] = $field_key;
        }
        else {
          $fields[] = $field_map[$field_key];
        }
      }

    }
    else {

      // Use entire map array.
      $fields = array_values($field_map);
      if (in_array($format, ['pretty', 'json'])) {
        $fields = array_keys($field_map);
      }

    }

    // Manually filter pretty and json output.
    if (in_array($format, ['pretty', 'json'])) {
      $filtered = [];
      foreach ($rows as $key => $row) {
        foreach ($fields as $field) {
          if (array_key_exists($field, $row)) {
            $filtered[$key][$field] = $row[$field];
          }
        }
      }
      $rows = $filtered;
    }

    switch ($format) {

      // Pretty-print output.
      case 'pretty':
        WP_CLI::log(print_r($rows, TRUE));
        break;

      // Display output as json.
      case 'json':
        $json = json_encode($rows);
        if (JSON_ERROR_NONE !== json_last_error()) {
          WP_CLI::error(sprintf(WP_CLI::colorize('Failed to encode JSON: %Y%s.%n'), json_last_error_msg()));
        }
        echo $json . "\n";
        break;

      // Display output as table.
      case 'table':
      default:
        $args = ['format' => $format];
        $formatter = new \WP_CLI\Formatter($args, $fields);
        $formatter->display_items($rows);
        break;

    }

  }

  /**
   * Uninstall a CiviCRM Extension.
   *
   * This command does not output parseable data. For parseable output,
   * consider using `wp civicrm api extension.uninstall`.
   *
   * ## EXAMPLES
   *
   *     $ wp civicrm ext uninstall org.example.foobar
   *     Success: CiviCRM Extension uninstalled.
   *
   * ## OPTIONS
   *
   * <key-or-name>
   * : The extension full key ("org.example.foobar") or short name ("foobar").
   *
   * [--extpath=<extpath>]
   * : Path to the Extension. May use a wildcard (\"*\").
   *
   * [--yes]
   * : Answer yes to the confirmation message. Not needed when called with --quiet.
   *
   * @since 6.14
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function uninstall($args, $assoc_args) {

    // Grab associative arguments.
    $extpath = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'extpath', '');

    // Grab Extension key.
    $key_or_name = $args[0];

    // Use "wp civicrm ext info" to get info for the Extension.
    $command = 'civicrm ext info ' . $key_or_name . ' --local --format=json';
    $options = ['launch' => FALSE, 'return' => TRUE, 'parse' => 'json', 'exit_error' => FALSE, 'command_args' => ['--quiet']];
    $result = WP_CLI::runcommand($command, $options);

    // Skip if not found.
    if (empty($result)) {
      WP_CLI::error(sprintf(WP_CLI::colorize('%gCould not find Extension:%n %y%s.%n'), $key_or_name));
    }

    // Bail if already uninstalled.
    foreach ($result as $extension) {
      // There should only be one item in the array.
      if ('uninstalled' === $extension['status']) {
        WP_CLI::log(sprintf(WP_CLI::colorize('%gExtension already uninstalled:%n %y%s.%n'), $key_or_name));
        WP_CLI::halt(1);
      }
    }

    // Show existing information.
    if (empty(WP_CLI::get_config('quiet'))) {
      WP_CLI::log(WP_CLI::colorize('%GGathering Extension information:%n'));
      $feedback = [];
      foreach ($result as $extension) {
        $feedback[] = [
          'Label' => $extension['label'],
          'Version' => $extension['version'],
          'Status' => $extension['status'],
        ];
      }
      $assoc_args['format'] = 'table';
      $assoc_args['fields'] = ['Label', 'Version', 'Status'];
      $formatter = $this->formatter_get($assoc_args);
      $formatter->display_items($feedback);

      // Let's give folks a chance to exit.
      WP_CLI::confirm(WP_CLI::colorize('%GDo you want to uninstall the Extension?%n'), $assoc_args);
    }

    // Let's take as much info as we can from the Extension data.
    foreach ($result as $extension) {
      if ($key_or_name !== $extension['key']) {
        $key_or_name = $extension['key'];
      }
    }

    // Build API vars.
    $vars = 'keys=' . $key_or_name;
    if (!empty($extpath)) {
      $vars .= ' path=' . $extpath;
    }

    // Use "wp civicrm api" to do the uninstall.
    $command = 'civicrm api extension.uninstall ' . $vars . ' --format=json';
    $options = ['launch' => FALSE, 'return' => TRUE, 'parse' => 'json', 'exit_error' => FALSE, 'command_args' => ['--quiet']];
    $result = WP_CLI::runcommand($command, $options);

    // Show error if present.
    if (!empty($result['is_error']) && 1 === (int) $result['is_error']) {
      WP_CLI::error(sprintf(WP_CLI::colorize('Failed to uninstall CiviCRM Extension: %y%s%n'), $result['error_message']));
    }

    WP_CLI::success('Extension uninstalled.');

  }

  /**
   * Apply DB upgrades for any Extensions.
   *
   * This command does not output parseable data. For parseable output,
   * consider using `wp civicrm api extension.upgrade`.
   *
   * In order to avoid an "API Request Authorization failed" backtrace
   * in your logs from the CiviImport Extension, specify a WordPress
   * User with the relevant permissions in CiviCRM.
   *
   * ## OPTIONS
   *
   * [--vvv]
   * : Run the upgrade with verbose output.
   *
   * ## EXAMPLES
   *
   *     # Basic example.
   *     $ wp civicrm ext update-db
   *     Applying available database upgrades for Extensions.
   *     Success: Database upgrades for Extensions completed.
   *
   *     # Avoid "Authorization failed" backtrace by specifying a permissioned User.
   *     $ wp civicrm ext update-db --user=1
   *     Applying available database upgrades for Extensions.
   *     Success: Database upgrades for Extensions completed.
   *
   * @subcommand update-db
   *
   * @alias upgrade-db
   *
   * @since 6.14
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function update_db($args, $assoc_args) {

    // Get verbosity.
    $verbose = (bool) \WP_CLI\Utils\get_flag_value($assoc_args, 'vvv', FALSE);

    WP_CLI::log(WP_CLI::colorize('%gApplying available database upgrades for Extensions.%n'));

    // Maybe show feedback.
    if (!empty($verbose)) {
      WP_CLI::log('Calling Extension upgrade API');
    }

    // Use "wp civicrm api" to do the upgrade.
    $command = 'civicrm api extension.upgrade --format=json --quiet';
    if (!empty($verbose)) {
      $command .= ' --xdebug';
    }
    $options = ['launch' => FALSE, 'return' => TRUE, 'parse' => 'json', 'exit_error' => FALSE, 'command_args' => ['--quiet']];
    $result = WP_CLI::runcommand($command, $options);

    // Show error if present.
    if (!empty($result['is_error']) && 1 === (int) $result['is_error']) {
      WP_CLI::error(sprintf(WP_CLI::colorize('Failed to upgrade CiviCRM Extensions: %y%s%n'), $result['error_message']));
    }

    // Maybe show feedback.
    if (!empty($verbose)) {
      WP_CLI::log('API success.');
      WP_CLI::log(print_r($result, TRUE));
    }

    WP_CLI::success('Database upgrades for Extensions completed.');

  }

  /**
   * Get a list of all available Extensions.
   *
   * @since 6.14
   *
   * @return array $cache The array of CiviCRM Extension info objects.
   */
  private function extensions_remote_get() {
    static $cache = NULL;
    if ($cache === NULL) {
      $cache = \CRM_Extension_System::singleton()->getBrowser()->getExtensions();
    }
    return $cache;
  }

}
