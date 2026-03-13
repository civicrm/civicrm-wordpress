<?php
/**
 * Get an overview of CiviCRM and its environment.
 *
 * ## EXAMPLES
 *
 *     $ wp civicrm status
 *     Output here.
 *
 * @since 6.14
 */
class CLI_Tools_CiviCRM_Command_Status extends CLI_Tools_CiviCRM_Command {

  /**
   * Get an overview of CiviCRM and its environment.
   *
   * ## OPTIONS
   *
   * [--source=<source>]
   * : Specify the source variable to get.
   * ---
   * default: all
   * options:
   *   - all
   *   - civicrm
   *   - db
   *   - mysql
   *   - wp
   *   - smarty
   *
   * [--format=<format>]
   * : Render output in a particular format.
   * ---
   * default: table
   * options:
   *   - table
   *   - json
   *   - number
   * ---
   *
   * ## EXAMPLES
   *
   *     $ wp civicrm status
   *     +-----------+--------------------------------+
   *     | Name      | Value                          |
   *     +-----------+--------------------------------+
   *     | CiviCRM   | 6.12.0                         |
   *     | Database  | 6.12.0                         |
   *     | Smarty    | 5                              |
   *     | WordPress | 6.9.1                          |
   *     | PHP       | 8.3.30 (cli, usr-bin)          |
   *     | MySQL     | 10.11.14-MariaDB-0+deb12u2-log |
   *     +-----------+--------------------------------+
   *
   *     $ wp civicrm status --format=json
   *     {"civicrm":"6.12.0","db":"6.12.0","smarty":5,"wp":"6.9.1","php":"8.3.30 (cli, usr-bin)","mysql":"10.11.14-MariaDB-0+deb12u2-log"}
   *
   *     # Get just the Smarty version number.
   *     $ wp civicrm status --source=smarty --format=number
   *     5
   *
   * @since 6.14
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function __invoke($args, $assoc_args) {

    // Grab associative arguments.
    $source = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'source', 'all');
    $format = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'format', 'table');

    // Bootstrap CiviCRM.
    $this->bootstrap_civicrm();

    // Compile basic information.
    $civicrm_version = CRM_Utils_System::version();
    $db_version = CRM_Core_BAO_Domain::version();
    $mysql_version = CRM_Utils_SQL::getDatabaseVersion();
    $wp_version = \CRM_Core_Config::singleton()->userSystem->getVersion();

    // Get Smarty if we can.
    $smarty_version = 'Unknown';
    if (method_exists(CRM_Core_Smarty::singleton(), 'getVersion')) {
      $smarty_version = CRM_Core_Smarty::singleton()->getVersion();
    }

    // Get verbose PHP version.
    $php_version = $this->php_long();

    switch ($format) {

      // Number-only output.
      case 'number':
        if (!in_array($source, ['civicrm', 'db', 'mysql', 'wp', 'smarty'])) {
          WP_CLI::error(WP_CLI::colorize('You must specify %Y--source=<source>%n%n to use this output format.'));
        }
        if ('civicrm' === $source) {
          echo $civicrm_version . "\n";
        }
        if ('db' === $source) {
          echo $db_version . "\n";
        }
        if ('smarty' === $source) {
          echo $smarty_version . "\n";
        }
        if ('wp' === $source) {
          echo $wp_version . "\n";
        }
        if ('php' === $source) {
          echo $php_version . "\n";
        }
        if ('mysql' === $source) {
          echo $mysql_version . "\n";
        }
        break;

      // Display output as json.
      case 'json':
        $info = [];
        if (in_array($source, ['all', 'civicrm'])) {
          $info['civicrm'] = $civicrm_version;
        }
        if (in_array($source, ['all', 'db'])) {
          $info['db'] = $db_version;
        }
        if (in_array($source, ['all', 'smarty'])) {
          $info['smarty'] = $smarty_version;
        }
        if (in_array($source, ['all', 'wp'])) {
          $info['wp'] = $wp_version;
        }
        if (in_array($source, ['all', 'php'])) {
          $info['php'] = $php_version;
        }
        if (in_array($source, ['all', 'mysql'])) {
          $info['mysql'] = $mysql_version;
        }
        $json = json_encode($info);
        if (JSON_ERROR_NONE !== json_last_error()) {
          WP_CLI::error(sprintf(WP_CLI::colorize('Failed to encode JSON: %Y%s.%n'), json_last_error_msg()));
        }
        echo $json . "\n";
        break;

      // Display output as table (default).
      case 'table':
      default:
        // Build the rows.
        $rows = [];
        $fields = ['Name', 'Value'];
        if (in_array($source, ['all', 'civicrm'])) {
          $rows[] = [
            'Name' => 'CiviCRM',
            'Value' => $civicrm_version,
          ];
        }
        if (in_array($source, ['all', 'db'])) {
          $rows[] = [
            'Name' => 'Database',
            'Value' => $db_version,
          ];
        }
        if (in_array($source, ['all', 'smarty'])) {
          $rows[] = [
            'Name' => 'Smarty',
            'Value' => $smarty_version,
          ];
        }
        if (in_array($source, ['all', 'wp'])) {
          $rows[] = [
            'Name' => 'WordPress',
            'Value' => $wp_version,
          ];
        }
        if (in_array($source, ['all', 'php'])) {
          $rows[] = [
            'Name' => 'PHP',
            'Value' => $php_version,
          ];
        }
        if (in_array($source, ['all', 'mysql'])) {
          $rows[] = [
            'Name' => 'MySQL',
            'Value' => $mysql_version,
          ];
        }

        // Display the rows.
        $args = ['format' => $format];
        $formatter = new \WP_CLI\Formatter($args, $fields);
        $formatter->display_items($rows);

    }

  }

  // ----------------------------------------------------------------------------
  // Private methods.
  // ----------------------------------------------------------------------------

  /**
   * Gets the PHP version and tries to establish the environment.
   *
   * @see Civi\Cv\Command\StatusCommand
   *
   * @since 1.0.0
   *
   * @return string $string The PHP version with environment in parentheses.
   */
  private function php_long() {

    // Init info array.
    $info = [PHP_SAPI => 1];

    // Check for Docker.
    if (file_exists('/.dockerenv')) {
      $info['docker'] = 1;
    }

    // Check for other environments.
    $info['other'] = 1;
    foreach ([PHP_BINARY, realpath(PHP_BINARY)] as $binary) {
      if (preg_match(';^/nix/;', $binary)) {
        $info['nix'] = 1;
        unset($info['other']);
      }
      if (preg_match(';/homebrew/;', $binary)) {
        // Newer deployments use /opt/homebrew. Dunno how to check older deployments in /usr/local.
        $info['homebrew'] = 1;
        unset($info['other']);
      }
      if (preg_match(';MAMP;', $binary)) {
        $info['mamp'] = 1;
        unset($info['other']);
      }
      if (preg_match(';^/usr/bin/;', $binary)) {
        $info['usr-bin'] = 1;
        unset($info['other']);
      }
      if (preg_match(';^/opt/plesk/;', $binary)) {
        $info['plesk'] = 1;
        unset($info['other']);
      }
    }

    // Build info string.
    $string = sprintf(
      '%s (%s)',
      PHP_VERSION,
      implode(', ', array_keys($info))
    );

    return $string;

  }

}
