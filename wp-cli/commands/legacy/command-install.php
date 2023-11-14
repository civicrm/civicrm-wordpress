<?php
/**
 * Install the CiviCRM plugin.
 *
 * ## EXAMPLES
 *
 *     $ wp civicrm install --zipfile=~/civicrm-5.57.1-wordpress.zip
 *
 * @since 5.69
 */
class CLI_Tools_CiviCRM_Command_Install extends CLI_Tools_CiviCRM_Command {

  /**
   * Install the CiviCRM plugin. Deprecated: use `wp civicrm core install` instead.
   *
   * ## OPTIONS
   *
   * [--dbname=<dbname>]
   * : MySQL database name of your CiviCRM database. Defaults to the WordPress database name.
   *
   * [--dbpass=<dbpass>]
   * : MySQL password for your CiviCRM database. Defaults to the WordPress MySQL database password.
   *
   * [--dbuser=<dbuser>]
   * : MySQL username for your CiviCRM database. Defaults to the WordPress MySQL database username.
   *
   * [--dbhost=<dbhost>]
   * : MySQL host for your CiviCRM database. Defaults to the WordPress MySQL host.
   *
   * [--zipfile=<zipfile>]
   * : Path to your CiviCRM zip file. If specified --version is ignored.
   *
   * [--tarfile=<tarfile>]
   * : Path to your CiviCRM .tar.gz file. Not currently available.
   *
   * [--lang=<lang>]
   * : Locale to use for installation. Defaults to "en_US".
   *
   * [--langtarfile=<langtarfile>]
   * : Path to your CiviCRM localization .tar.gz file.
   *
   * [--ssl=<ssl>]
   * : The SSL setting for your website, e.g. '--ssl=on'. Defaults to "on".
   *
   * [--site_url=<site_url>]
   * : Domain for your website, e.g. 'mysite.com'.
   *
   * [--yes]
   * : Answer yes to the confirmation message.
   *
   * ## EXAMPLES
   *
   *     $ wp civicrm install --zipfile=~/civicrm-5.57.1-wordpress.zip
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function __invoke($args, $assoc_args) {

    WP_CLI::log(WP_CLI::colorize('%CDeprecated command:%n %cuse `wp civicrm core install` instead.%n'));

    // Grab associative arguments.
    $dbuser = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'dbuser', (defined('DB_USER') ? DB_USER : ''));
    $dbpass = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'dbpass', (defined('DB_PASSWORD') ? DB_PASSWORD : ''));
    $dbhost = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'dbhost', (defined('DB_HOST') ? DB_HOST : ''));
    $dbname = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'dbname', (defined('DB_NAME') ? DB_NAME : ''));
    $locale = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'locale', 'en_US');
    $zipfile = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'zipfile', '');
    $tarfile = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'tarfile', '');
    $lang = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'lang', '');
    $langtarfile = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'langtarfile', '');
    $ssl = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'ssl', '');
    $site_url = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'site_url', '');
    $yes = (bool) \WP_CLI\Utils\get_flag_value($assoc_args, 'yes', FALSE);

    // Bail when .tar.gz archive is specified.
    if (!empty($tarfile)) {
      WP_CLI::error('CiviCRM .tar.gz archives are not supported.');
    }

    // Bail when no .zip archive is specified.
    if (empty($zipfile)) {
      WP_CLI::error('You must supply a CiviCRM zip archive.');
    }

    // Build install command.
    $command = 'civicrm core install --zipfile=' . $zipfile .
      (empty($langtarfile) ? '' : ' --l10n-tarfile=' . $langtarfile);

    // Run "wp civicrm core install".
    $options = ['launch' => FALSE, 'return' => FALSE];
    WP_CLI::runcommand($command, $options);

    // Build activate command.
    $command = 'civicrm core activate' .
      (empty($dbuser) ? '' : ' --dbuser=' . $dbuser) .
      (empty($dbpass) ? '' : ' --dbpass=' . $dbpass) .
      (empty($dbhost) ? '' : ' --dbhost=' . $dbhost) .
      (empty($dbname) ? '' : ' --dbname=' . $dbname) .
      (empty($lang) ? '' : ' --locale=' . $lang) .
      (empty($ssl) ? '' : ' --ssl=' . $ssl) .
      (empty($site_url) ? '' : ' --site-url=' . $site_url) .
      (empty($yes) ? '' : ' --yes');

    // Run "wp civicrm core activate".
    $options = ['launch' => FALSE, 'return' => FALSE];
    WP_CLI::runcommand($command, $options);

  }

}
