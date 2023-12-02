<?php
/**
 * Downloads, installs, updates, and manages a CiviCRM installation.
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
 * @since 5.69
 */
class CLI_Tools_CiviCRM_Command_Core extends CLI_Tools_CiviCRM_Command {

  /**
   * @var string
   * The URL to check for CiviCRM upgrades.
   * @since 5.69
   * @access private
   */
  private $upgrade_url = 'https://download.civicrm.org/check';

  /**
   * @var string
   * The Google API URL to check for all top-level CiviCRM prefixes.
   * @since 5.69
   * @access private
   */
  private $google_url = 'https://storage.googleapis.com/storage/v1/b/civicrm/o/?delimiter=/';

  /**
   * @var string
   * The Google API query param to append for checking CiviCRM stable versions.
   * @since 5.69
   * @access private
   */
  private $google_prefix_stable = 'prefix=civicrm-stable/';

  /**
   * @var string
   * The common part of the Google API URL for CiviCRM release archive downloads.
   * @since 5.69
   * @access private
   */
  private $google_download_url = 'https://storage.googleapis.com/civicrm/';

  /**
   * Activates the CiviCRM plugin and loads the database.
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
   * [--locale=<locale>]
   * : Locale to use for installation. Defaults to "en_US".
   *
   * [--ssl=<ssl>]
   * : The SSL setting for your website, e.g. '--ssl=on'. Defaults to "on".
   *
   * [--site-url=<site-url>]
   * : Domain for your website, e.g. 'mysite.com'.
   *
   * [--yes]
   * : Answer yes to the confirmation message.
   *
   * ## EXAMPLES
   *
   *     # Activate the CiviCRM plugin.
   *     $ wp civicrm core activate
   *     CiviCRM database credentials:
   *     +----------+-----------------------+
   *     | Field    | Value                 |
   *     +----------+-----------------------+
   *     | Database | civicrm_database_name |
   *     | Username | foo                   |
   *     | Password | dbpassword            |
   *     | Host     | localhost             |
   *     | Locale   | en_US                 |
   *     | SSL      | on                    |
   *     +----------+-----------------------+
   *     Do you want to continue? [y/n] y
   *     Creating file /httpdocs/wp-content/uploads/civicrm/civicrm.settings.php
   *     Success: CiviCRM data files initialized.
   *     Creating civicrm_* database tables in civicrm_database_name
   *     Success: CiviCRM database loaded.
   *     Plugin 'civicrm' activated.
   *     Success: Activated 1 of 1 plugins.
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function activate($args, $assoc_args) {

    // Only install plugin if not already installed.
    $fetcher = new \WP_CLI\Fetchers\Plugin();
    $plugin_installed = $fetcher->get('civicrm');
    if (!$plugin_installed) {
      WP_CLI::error('You need to install CiviCRM first.');
    }

    // Get the path to the CiviCRM plugin directory.
    $plugin_path = $this->plugin_path_get();

    /*
     * Check for the presence of the CiviCRM core codebase.
     *
     * NOTE: This is *not* the CiviCRM plugin - it is the directory where the common
     * CiviCRM code lives. It always lives in a sub-directory of the plugin directory
     * called "civicrm".
     */
    global $crmPath;
    $crmPath = trailingslashit($plugin_path) . 'civicrm';
    if (!is_dir($crmPath)) {
      WP_CLI::error('CiviCRM core files are missing.');
    }

    // We need the CiviCRM classloader so that we can run `Civi\Setup`.
    $classLoaderPath = "$crmPath/CRM/Core/ClassLoader.php";
    if (!file_exists($classLoaderPath)) {
      WP_CLI::error('CiviCRM installer helper file is missing.');
    }

    // Grab associative arguments.
    $dbuser = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'dbuser', (defined('DB_USER') ? DB_USER : ''));
    $dbpass = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'dbpass', (defined('DB_PASSWORD') ? DB_PASSWORD : ''));
    $dbhost = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'dbhost', (defined('DB_HOST') ? DB_HOST : ''));
    $dbname = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'dbname', (defined('DB_NAME') ? DB_NAME : ''));
    $locale = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'locale', 'en_US');
    $ssl = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'ssl', 'on');
    $base_url = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'site-url', '');

    // Show database parameters.
    WP_CLI::log(WP_CLI::colorize('%GCiviCRM database credentials:%n'));
    $assoc_args['format'] = 'table';
    $feedback = [
      'Database' => $dbname,
      'Username' => $dbuser,
      'Password' => $dbpass,
      'Host' => $dbhost,
      'Locale' => $locale,
      'SSL' => $ssl,
    ];
    $assoc_args['fields'] = array_keys($feedback);
    $formatter = $this->formatter_get($assoc_args);
    $formatter->display_item($feedback);

    // Let's give folks a chance to exit now.
    WP_CLI::confirm(WP_CLI::colorize('%GDo you want to continue?%n'), $assoc_args);

    // ----------------------------------------------------------------------------
    // Activation and installation.
    // ----------------------------------------------------------------------------

    // Set some constants that CiviCRM requires.
    if (!defined('CIVICRM_PLUGIN_DIR')) {
      define('CIVICRM_PLUGIN_DIR', \WP_CLI\Utils\trailingslashit($plugin_path));
    }
    if (!defined('CIVICRM_PLUGIN_URL')) {
      define('CIVICRM_PLUGIN_URL', plugin_dir_url(CIVICRM_PLUGIN_DIR));
    }

    // Maybe set SSL.
    if ('on' === $ssl) {
      $_SERVER['HTTPS'] = 'on';
    }

    // Initialize civicrm-setup.
    require_once $classLoaderPath;
    CRM_Core_ClassLoader::singleton()->register();
    \Civi\Setup::assertProtocolCompatibility(1.0);
    \Civi\Setup::init(['cms' => 'WordPress', 'srcPath' => $crmPath]);
    $setup = \Civi\Setup::instance();

    // Apply essential arguments.
    $setup->getModel()->db = ['server' => $dbhost, 'username' => $dbuser, 'password' => $dbpass, 'database' => $dbname];
    $setup->getModel()->lang = $locale;

    /*
     * The "base URL" should already be known, either by:
     *
     * * The "site_url()" setting in WordPress standalone
     * * The URL flag in WordPress Multisite: --url=https://my-domain.com
     *
     * TODO: This means that the `--site_url` flag is basically redundant.
     */
    if (!empty($base_url)) {
      $protocol = ('on' === $ssl ? 'https' : 'http');
      $base_url = $protocol . '://' . $base_url;
      $setup->getModel()->cmsBaseUrl = trailingslashit($base_url);
    }

    // Validate system requirements.
    $reqs = $setup->checkRequirements();
    foreach ($reqs->getWarnings() as $msg) {
      WP_CLI::log(sprintf(WP_CLI::colorize('%YWARNING:%n %y(%s) %s:%n %s'), $msg['section'], $msg['name'], $msg['message']));
    }
    $errors = $reqs->getErrors();
    if ($errors) {
      foreach ($errors as $msg) {
        WP_CLI::log(sprintf(WP_CLI::colorize('%RERROR:%n %r(%s) %s:%n %s'), $msg['section'], $msg['name'], $msg['message']));
      }
      WP_CLI::error('Requirements check failed.');
    }

    // Install data files.
    $installed = $setup->checkInstalled();
    if (!$installed->isSettingInstalled()) {
      WP_CLI::log(sprintf(WP_CLI::colorize('%GCreating file%n %Y%s%n'), $setup->getModel()->settingsPath));
      $setup->installFiles();
    }
    else {
      WP_CLI::log(sprintf(WP_CLI::colorize('%gFound existing%n %Y%s%n %Gin%n %Y%s%n'), basename($setup->getModel()->settingsPath), dirname($setup->getModel()->settingsPath)));
      switch ($this->conflict_action_pick('civicrm.settings.php')) {
        case 'abort':
          WP_CLI::log(WP_CLI::colorize('%CAborted%n'));
          WP_CLI::halt(0);

        case 'overwrite':
          WP_CLI::log(sprintf(WP_CLI::colorize('%GRemoving%n %Y%s%n %Gfrom%n %Y%s%n'), basename($setup->getModel()->settingsPath), dirname($setup->getModel()->settingsPath)));
          $setup->uninstallFiles();
          WP_CLI::log(sprintf(WP_CLI::colorize('%GCreating%n %Y%s%n %Gin%n %Y%s%n'), basename($setup->getModel()->settingsPath), dirname($setup->getModel()->settingsPath)));
          $setup->installFiles();
          break;

        case 'keep':
          break;

        default:
          WP_CLI::error('Unrecognized action');
      }
    }

    WP_CLI::success('CiviCRM data files initialized.');

    // Clean the "templates_c" directory to avoid fatal error when overwriting the database.
    if (function_exists('civicrm_initialize')) {
      $this->bootstrap_civicrm();
      $config = CRM_Core_Config::singleton();
      $config->cleanup(1, FALSE);
    }

    // Install database.
    if (!$installed->isDatabaseInstalled()) {
      WP_CLI::log(sprintf(WP_CLI::colorize('%GCreating%n %Ycivicrm_*%n %Gdatabase tables in%n %Y%s%n'), $setup->getModel()->db['database']));
      $setup->installDatabase();
    }
    else {
      WP_CLI::log(sprintf(WP_CLI::colorize('%GFound existing%n %Ycivicrm_*%n database tables in%n %Y%s%n'), $setup->getModel()->db['database']));
      switch ($this->conflict_action_pick('database tables')) {
        case 'abort':
          WP_CLI::log(WP_CLI::colorize('%CAborted%n'));
          WP_CLI::halt(0);

        case 'overwrite':
          WP_CLI::log(sprintf(WP_CLI::colorize('%GRemoving%n %Ycivicrm_*%n database tables in%n %Y%s%n'), $setup->getModel()->db['database']));
          $setup->uninstallDatabase();
          WP_CLI::log(sprintf(WP_CLI::colorize('%GCreating%n %Ycivicrm_*%n database tables in%n %Y%s%n'), $setup->getModel()->db['database']));
          $setup->installDatabase();
          break;

        case 'keep':
          break;

        default:
          WP_CLI::error('Unrecognized action');
      }
    }

    WP_CLI::success('CiviCRM database loaded.');

    // Looking good, let's activate the CiviCRM plugin.
    WP_CLI::run_command(['plugin', 'activate', 'civicrm'], []);

  }

  /**
   * Back up the CiviCRM plugin files and database.
   *
   * ## OPTIONS
   *
   * [--backup-dir=<backup-dir>]
   * : Path to your CiviCRM backup directory. Default is one level above ABSPATH.
   *
   * [--also-include=<also-include>]
   * : Comma separated list of additional tables to back up based on wildcard search.
   *
   * [--yes]
   * : Answer yes to the confirmation message.
   *
   * ## EXAMPLES
   *
   *     # Standard backup.
   *     $ wp civicrm core backup
   *     Gathering system information.
   *     +------------------------+-----------------------------------------------------------------------+
   *     | Field                  | Value                                                                 |
   *     +------------------------+-----------------------------------------------------------------------+
   *     | Backup directory       | /example.com/civicrm-backup                                           |
   *     | Plugin path            | /example.com/httpdocs/wp-content/plugins/civicrm/                     |
   *     | Database name          | civicrm_db                                                            |
   *     | Database username      | dbuser                                                                |
   *     | Database password      | dbpassword                                                            |
   *     | Database host          | localhost                                                             |
   *     | Settings file          | /example.com/httpdocs/wp-content/uploads/civicrm/civicrm.settings.php |
   *     | Config and Log         | /example.com/httpdocs/wp-content/uploads/civicrm/ConfigAndLog/        |
   *     | Custom PHP             | Not found                                                             |
   *     | Custom templates       | Not found                                                             |
   *     | Compiled templates     | /example.com/httpdocs/wp-content/uploads/civicrm/templates_c/         |
   *     | Extensions directory   | /example.com/httpdocs/wp-content/uploads/civicrm/ext/                 |
   *     | Uploads directory      | /example.com/httpdocs/wp-content/uploads/civicrm/upload/              |
   *     | Image upload directory | /example.com/httpdocs/wp-content/uploads/civicrm/persist/contribute/  |
   *     | File upload directory  | /example.com/httpdocs/wp-content/uploads/civicrm/custom/              |
   *     +------------------------+-----------------------------------------------------------------------+
   *     Do you want to continue? [y/n] y
   *
   *     # Also back up tables not registered with CiviCRM.
   *     # In this case, also exports tables for the "Canadian Tax Receipts" extension.
   *     $ wp civicrm core backup --also-include='cdntaxreceipts_*'
   *     ...
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function backup($args, $assoc_args) {

    // Grab associative arguments.
    $backup_dir = \WP_CLI\Utils\get_flag_value($assoc_args, 'backup-dir', trailingslashit(dirname(ABSPATH)) . 'civicrm-backup');
    $also_include = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'also-include', '');

    // ----------------------------------------------------------------------------
    // Build feedback table.
    // ----------------------------------------------------------------------------
    WP_CLI::log(WP_CLI::colorize('%GGathering system information.%n'));

    // Bootstrap CiviCRM.
    $this->bootstrap_civicrm();

    // Let's have a look for some CiviCRM variables.
    $config = CRM_Core_Config::singleton();

    // Build feedback.
    $feedback = [];
    if (!empty($backup_dir)) {
      $feedback['Backup directory'] = $backup_dir;
    }
    if (defined('CIVICRM_PLUGIN_DIR')) {
      $feedback['Plugin path'] = CIVICRM_PLUGIN_DIR;
    }
    else {
      $feedback['Plugin path'] = 'Not found';
    }
    if (defined('CIVICRM_DSN')) {
      $dsn = DB::parseDSN(CIVICRM_DSN);
      $feedback['Database name'] = $dsn['database'];
      $feedback['Database username'] = $dsn['username'];
      $feedback['Database password'] = $dsn['password'];
      $feedback['Database host'] = $dsn['hostspec'];
    }
    else {
      $feedback['Database Settings'] = 'Not found';
    }
    if (defined('CIVICRM_SETTINGS_PATH')) {
      $feedback['Settings file'] = CIVICRM_SETTINGS_PATH;
    }
    else {
      $feedback['Settings file'] = 'Not found';
    }
    if (!empty($config->configAndLogDir)) {
      $feedback['Config and Log'] = $config->configAndLogDir;
    }
    else {
      $feedback['Config and Log'] = 'Not found';
    }
    if (!empty($config->customPHPPathDir)) {
      $feedback['Custom PHP'] = $config->customPHPPathDir;
    }
    else {
      $feedback['Custom PHP'] = 'Not found';
    }
    if (!empty($config->customTemplateDir)) {
      $feedback['Custom templates'] = $config->customTemplateDir;
    }
    else {
      $feedback['Custom templates'] = 'Not found';
    }
    if (!empty($config->templateCompileDir)) {
      $feedback['Compiled templates'] = $config->templateCompileDir;
    }
    else {
      $feedback['Compiled templates'] = 'Not found';
    }
    if (!empty($config->extensionsDir)) {
      $feedback['Extensions directory'] = $config->extensionsDir;
    }
    else {
      $feedback['Extensions directory'] = 'Not found';
    }
    if (!empty($config->uploadDir)) {
      $feedback['Uploads directory'] = $config->uploadDir;
    }
    else {
      $feedback['Uploads directory'] = 'Not found';
    }
    if (!empty($config->imageUploadDir)) {
      $feedback['Image upload directory'] = $config->imageUploadDir;
    }
    else {
      $feedback['Image upload directory'] = 'Not found';
    }
    if (!empty($config->customFileUploadDir)) {
      $feedback['File upload directory'] = $config->customFileUploadDir;
    }
    else {
      $feedback['File upload directory'] = 'Not found';
    }

    // Render feedback.
    $assoc_args['fields'] = array_keys($feedback);
    $formatter = $this->formatter_get($assoc_args);
    $formatter->display_item($feedback);

    // Let's give folks a chance to exit now.
    WP_CLI::confirm(WP_CLI::colorize('%GDo you want to continue?%n'), $assoc_args);

    // ----------------------------------------------------------------------------
    // Validate backup directory.
    // ----------------------------------------------------------------------------
    $backup_dir = untrailingslashit($backup_dir);

    // Maybe create destination directory.
    if (!is_dir($backup_dir)) {
      if (!is_writable(dirname($backup_dir))) {
        WP_CLI::error("Insufficient permission to create directory '{$backup_dir}'.");
      }
      WP_CLI::log("Creating directory '{$backup_dir}'.");
      // Recursively create directory.
      if (!@mkdir($backup_dir, 0777, TRUE)) {
        $error = error_get_last();
        WP_CLI::error("Failed to create directory '{$backup_dir}': {$error['message']}.");
      }
    }

    // Sanity check.
    if (!is_writable($backup_dir)) {
      WP_CLI::error("'{$backup_dir}' is not writable by current user.");
    }

    // ----------------------------------------------------------------------------
    // Backup procedure.
    // ----------------------------------------------------------------------------

    // Maybe add extra filters.
    $also_include_args = '';
    if (!empty($also_include)) {
      $also_include_args = " --also-include={$also_include}";
    }

    // Use "wp civicrm db export" to export the CiviCRM database tables.
    WP_CLI::log('');
    WP_CLI::log(WP_CLI::colorize('%GExporting database...%n'));
    $command = 'civicrm db export' . $also_include_args . ' --result-file=' . $backup_dir . '/civicrm-db.sql';
    $options = ['launch' => FALSE, 'return' => FALSE];
    WP_CLI::runcommand($command, $options);
    WP_CLI::success('Database exported.');

    // Back up plugin directory.
    WP_CLI::log('');
    WP_CLI::log(WP_CLI::colorize('%GBacking up plugin directory...%n'));
    $plugin_path = $this->plugin_path_get();
    if (!$this->zip_compress($plugin_path, $backup_dir . '/civicrm.zip')) {
      WP_CLI::error('Could not compress plugin archive.');
    }
    WP_CLI::success('Plugin directory backed up.');

    // Back up "civicrm.settings.php" file.
    if (defined('CIVICRM_SETTINGS_PATH')) {
      WP_CLI::log('');
      WP_CLI::log(WP_CLI::colorize('%GBacking up Settings File...%n'));
      $dest_path = $backup_dir . '/civicrm.settings.php';
      if (file_exists($dest_path) && is_writable($dest_path)) {
        copy(CIVICRM_SETTINGS_PATH, $dest_path);
      }
      elseif (!file_exists($dest_path)) {
        copy(CIVICRM_SETTINGS_PATH, $dest_path);
      }
      else {
        WP_CLI::error("Could not copy '" . CIVICRM_SETTINGS_PATH . "' to backup directory.");
      }
      WP_CLI::success('Settings File backed up.');
    }

    // Back up Config and Log directory.
    if (!empty($config->configAndLogDir)) {
      WP_CLI::log('');
      WP_CLI::log(WP_CLI::colorize('%GBacking up Config and Log directory...%n'));
      if (!$this->zip_compress(untrailingslashit($config->configAndLogDir), $backup_dir . '/civicrm-config-log.zip')) {
        WP_CLI::error('Could not compress Config and Log archive.');
      }
      WP_CLI::success('Config and Log directory backed up.');
    }

    // Back up Custom PHP directory.
    if (!empty($config->customPHPPathDir)) {
      WP_CLI::log('');
      WP_CLI::log(WP_CLI::colorize('%GBacking up Custom PHP directory...%n'));
      if (!$this->zip_compress(untrailingslashit($config->customPHPPathDir), $backup_dir . '/civicrm-custom-php.zip')) {
        WP_CLI::error('Could not compress Custom PHP archive.');
      }
      WP_CLI::success('Custom PHP directory backed up.');
    }

    // Back up Custom templates directory.
    if (!empty($config->customTemplateDir)) {
      WP_CLI::log('');
      WP_CLI::log(WP_CLI::colorize('%GBacking up Custom Templates directory...%n'));
      if (!$this->zip_compress(untrailingslashit($config->customTemplateDir), $backup_dir . '/civicrm-custom-templates.zip')) {
        WP_CLI::error('Could not compress Custom Templates archive.');
      }
      WP_CLI::success('Custom Templates directory backed up.');
    }

    // Back up Compiled templates directory.
    if (!empty($config->templateCompileDir)) {
      WP_CLI::log('');
      WP_CLI::log(WP_CLI::colorize('%GBacking up Compiled Templates directory...%n'));
      if (!$this->zip_compress(untrailingslashit($config->templateCompileDir), $backup_dir . '/civicrm-compiled-templates.zip')) {
        WP_CLI::error('Could not compress Compiled templates archive.');
      }
      WP_CLI::success('Compiled Templates directory backed up.');
    }

    // Back up Extensions directory.
    if (!empty($config->extensionsDir)) {
      WP_CLI::log('');
      WP_CLI::log(WP_CLI::colorize('%GBacking up Extensions directory...%n'));
      if (!$this->zip_compress(untrailingslashit($config->extensionsDir), $backup_dir . '/civicrm-extensions.zip')) {
        WP_CLI::error('Could not compress Extensions archive.');
      }
      WP_CLI::success('Extensions directory backed up.');
    }

    // Back up Uploads directory.
    if (!empty($config->uploadDir)) {
      WP_CLI::log('');
      WP_CLI::log(WP_CLI::colorize('%GBacking up Uploads directory...%n'));
      if (!$this->zip_compress(untrailingslashit($config->uploadDir), $backup_dir . '/civicrm-uploads.zip')) {
        WP_CLI::error('Could not compress Uploads archive.');
      }
      WP_CLI::success('Uploads directory backed up.');
    }

    // Back up Image upload directory.
    if (!empty($config->imageUploadDir)) {
      WP_CLI::log('');
      WP_CLI::log(WP_CLI::colorize('%GBacking up Image Uploads directory...%n'));
      if (!$this->zip_compress(untrailingslashit($config->imageUploadDir), $backup_dir . '/civicrm-image-uploads.zip')) {
        WP_CLI::error('Could not compress Image Uploads archive.');
      }
      WP_CLI::success('Image Uploads directory backed up.');
    }

    // Back up File Uploads directory.
    if (!empty($config->customFileUploadDir)) {
      WP_CLI::log('');
      WP_CLI::log(WP_CLI::colorize('%GBacking up File Uploads directory...%n'));
      if (!$this->zip_compress(untrailingslashit($config->customFileUploadDir), $backup_dir . '/civicrm-file-uploads.zip')) {
        WP_CLI::error('Could not compress File Uploads archive.');
      }
      WP_CLI::success('File Uploads directory backed up.');
    }

  }

  /**
   * Checks for a CiviCRM version or matching localization archive.
   *
   * ## OPTIONS
   *
   * [--version=<version>]
   * : Specify the version to check. Accepts a version number, 'stable', 'rc' or 'nightly'.
   *
   * [--l10n]
   * : Get the localization file data for the specified version.
   *
   * [--format=<format>]
   * : Render output in a particular format.
   * ---
   * default: table
   * options:
   *   - table
   *   - json
   *   - url
   *   - version
   * ---
   *
   * ## EXAMPLES
   *
   *     # Check for a stable version of CiviCRM
   *     $ wp civicrm core check-version --version=5.17.2
   *     +-----------+---------+-------------------------------------------------------------------------------------------+
   *     | Package   | Version | Package URL                                                                               |
   *     +-----------+---------+-------------------------------------------------------------------------------------------+
   *     | WordPress | 5.17.2  | https://storage.googleapis.com/civicrm/civicrm-stable/5.17.2/civicrm-5.17.2-wordpress.zip |
   *     | L10n      | 5.17.2  | https://storage.googleapis.com/civicrm/civicrm-stable/5.17.2/civicrm-5.17.2-l10n.tar.gz   |
   *     +-----------+---------+-------------------------------------------------------------------------------------------+
   *
   *     # Get the URL for a stable version of CiviCRM
   *     $ wp civicrm core check-version --version=5.17.2 --format=url
   *     https://storage.googleapis.com/civicrm/civicrm-stable/5.17.2/civicrm-5.17.2-wordpress.zip
   *
   *     # Get the URL for a stable version of the CiviCRM localisation archive
   *     $ wp civicrm core check-version --version=5.17.2 --format=url --l10n
   *     https://storage.googleapis.com/civicrm/civicrm-stable/5.17.2/civicrm-5.17.2-l10n.tar.gz
   *
   *     # Get the JSON-formatted data for a stable version of CiviCRM
   *     $ wp civicrm core check-version --version=5.17.2 --format=json
   *     {"version":"5.17.2","tar":{"L10n":"civicrm-stable\/5.17.2\/civicrm-5.17.2-l10n.tar.gz","WordPress":"civicrm-stable\/5.17.2\/civicrm-5.17.2-wordpress.zip"}}
   *
   *     # Get the latest nightly version of CiviCRM
   *     $ wp civicrm core check-version --version=nightly --format=version
   *     5.59.alpha1
   *
   * @subcommand check-version
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function check_version($args, $assoc_args) {

    // Grab associative arguments.
    $version = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'version', 'stable');
    $l10n = (bool) \WP_CLI\Utils\get_flag_value($assoc_args, 'l10n', FALSE);
    $format = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'format', 'table');

    // Pass to "check-update" for "stable", "rc" or "nightly".
    if (in_array($version, ['stable', 'rc', 'nightly'])) {
      $options = ['launch' => FALSE, 'return' => FALSE];
      $command = 'civicrm core check-update --version=' . $version . ' --format=' . $format . (empty($l10n) ? '' : ' --l10n');
      WP_CLI::runcommand($command, $options);
      return;
    }

    // Check for valid release.
    $versions = $this->releases_get();
    if (!in_array($version, $versions)) {
      WP_CLI::error(sprintf(WP_CLI::colorize('Version %Y%s%n is not a valid CiviCRM version.'), $version));
    }

    // Get the release data.
    $data = $this->release_data_get($version);

    switch ($format) {

      // URL-only output.
      case 'url':
        if ($l10n) {
          echo $this->google_download_url . $data['L10n'] . "\n";
        }
        else {
          echo $this->google_download_url . $data['WordPress'] . "\n";
        }
        break;

      // Version-only output.
      case 'version':
        echo $version . "\n";
        break;

      // Display output as json.
      case 'json':
        // Use a similar format to the Version Check API.
        $info = [
          'version' => $version,
          'tar' => $data,
        ];
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
        $fields = ['Package', 'Version', 'Package URL'];
        $rows[] = [
          'Package' => 'WordPress',
          'Version' => $version,
          'Package URL' => $this->google_download_url . $data['WordPress'],
        ];
        $rows[] = [
          'Package'  => 'L10n',
          'Version' => $version,
          'Package URL' => $this->google_download_url . $data['L10n'],
        ];

        // Display the rows.
        $args = ['format' => $format];
        $formatter = new \WP_CLI\Formatter($args, $fields);
        $formatter->display_items($rows);

    }

  }

  /**
   * Checks for CiviCRM updates via Version Check API.
   *
   * ## OPTIONS
   *
   * [--version=<version>]
   * : Specify the version to get.
   * ---
   * default: stable
   * options:
   *   - nightly
   *   - rc
   *   - stable
   * ---
   *
   * [--l10n]
   * : Get the localization file data for the specified version.
   *
   * [--format=<format>]
   * : Render output in a particular format.
   * ---
   * default: table
   * options:
   *   - table
   *   - json
   *   - url
   *   - version
   * ---
   *
   * ## EXAMPLES
   *
   *     # Check for the latest stable version of CiviCRM
   *     $ wp civicrm core check-update
   *     +-----------+---------+-------------------------------------------------------------------------------------------+
   *     | Package   | Version | Package URL                                                                               |
   *     +-----------+---------+-------------------------------------------------------------------------------------------+
   *     | WordPress | 5.67.0  | https://storage.googleapis.com/civicrm/civicrm-stable/5.67.0/civicrm-5.67.0-wordpress.zip |
   *     | L10n      | 5.67.0  | https://storage.googleapis.com/civicrm/civicrm-stable/5.67.0/civicrm-5.67.0-l10n.tar.gz   |
   *     +-----------+---------+-------------------------------------------------------------------------------------------+
   *
   *     # Get the URL for the latest stable version of CiviCRM core
   *     $ wp civicrm core check-update --format=url
   *     https://storage.googleapis.com/civicrm/civicrm-stable/5.67.0/civicrm-5.67.0-wordpress.zip
   *
   *     # Get the URL for the latest stable version of CiviCRM localisation archive
   *     $ wp civicrm core check-update --format=url --l10n
   *     https://storage.googleapis.com/civicrm/civicrm-stable/5.67.0/civicrm-5.67.0-l10n.tar.gz
   *
   *     # Get the complete JSON-formatted data for the latest RC version of CiviCRM core
   *     $ wp civicrm core check-update --version=rc --format=json
   *     {"version":"5.58.beta1","rev":"5.58.beta1-202301260741" [...] "pretty":"Thu, 26 Jan 2023 07:41:00 +0000"}}
   *
   * @subcommand check-update
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function check_update($args, $assoc_args) {

    // Grab associative arguments.
    $version = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'version', 'stable');
    $l10n = (bool) \WP_CLI\Utils\get_flag_value($assoc_args, 'l10n', FALSE);
    $format = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'format', 'table');

    // Look up the data.
    $url = $this->upgrade_url . '?stability=' . $version;
    $response = $this->http_get_response($url);

    // Try and decode response.
    $lookup = json_decode($response, TRUE);
    if (JSON_ERROR_NONE !== json_last_error()) {
      WP_CLI::error(sprintf(WP_CLI::colorize('Failed to decode JSON: %Y%s.%n'), json_last_error_msg()));
    }

    // Sanity checks.
    if (empty($lookup)) {
      WP_CLI::error(sprintf(WP_CLI::colorize('Version not found at: %Y%s%n'), $url));
    }
    if (empty($lookup['tar']['WordPress'])) {
      WP_CLI::error(sprintf(WP_CLI::colorize('No WordPress version found at: %Y%s%n'), $url));
    }

    switch ($format) {

      // URL-only output.
      case 'url':
        if ($l10n) {
          echo $lookup['tar']['L10n'] . "\n";
        }
        else {
          echo $lookup['tar']['WordPress'] . "\n";
        }
        break;

      // Version-only output.
      case 'version':
        echo $lookup['version'] . "\n";
        break;

      // Display output as json.
      case 'json':
        echo $response . "\n";
        break;

      // Display output as table (default).
      case 'table':
      default:
        // Build the rows.
        $rows = [];
        $fields = ['Package', 'Version', 'Package URL'];
        $rows[] = [
          'Package' => 'WordPress',
          'Version' => $lookup['version'],
          'Package URL' => $lookup['tar']['WordPress'],
        ];
        $rows[] = [
          'Package' => 'L10n',
          'Version' => $lookup['version'],
          'Package URL' => $lookup['tar']['L10n'],
        ];

        // Display the rows.
        $args = ['format' => $format];
        $formatter = new \WP_CLI\Formatter($args, $fields);
        $formatter->display_items($rows);

    }

  }

  /**
   * Downloads CiviCRM core files or localization files.
   *
   * Downloads and extracts CiviCRM core files or localization files to the
   * specified path. Uses the local temp directory when no path is specified.
   *
   * ## OPTIONS
   *
   * [--version=<version>]
   * : Specify the CiviCRM version to get. Accepts a version number, 'stable', 'rc' or 'nightly'. Defaults to latest stable version.
   *
   * [--l10n]
   * : Get the localization file for the specified version.
   *
   * [--destination=<destination>]
   * : Specify the absolute path to put the archive file. Defaults to local temp directory.
   *
   * [--insecure]
   * : Retry without certificate validation if TLS handshake fails. Note: This makes the request vulnerable to a MITM attack.
   *
   * [--extract]
   * : Whether to extract the downloaded file. Defaults to false.
   *
   * ## EXAMPLES
   *
   *     # Download the latest stable CiviCRM core archive.
   *     $ wp civicrm core download
   *     Checking file to download...
   *     Downloading file...
   *     Success: CiviCRM downloaded to /tmp/
   *
   *     # Download and extract a stable CiviCRM core archive to a directory.
   *     $ wp civicrm core download --version=5.17.2 --extract --destination=/some/path
   *     Checking file to download...
   *     Downloading file...
   *     Extracting zip archive...
   *     Success: CiviCRM downloaded and extracted to: /some/path/
   *
   *     # Quietly download a stable CiviCRM core archive.
   *     $ wp civicrm core download --version=5.17.2 --quiet
   *     /tmp/civicrm-5.17.2-wordpress.zip
   *
   *     # Download and extract a stable CiviCRM localization archive to a directory.
   *     $ wp civicrm core download --version=5.17.2 --l10n --extract --destination=/some/path
   *     Checking file to download...
   *     Downloading file...
   *     Extracting tar.gz archive...
   *     Success: CiviCRM localization downloaded and extracted to: /some/path/
   *
   *     # Quietly download a stable CiviCRM localization archive.
   *     $ wp civicrm core download --version=5.17.2 --l10n --quiet
   *     /tmp/civicrm-5.17.2-l10n.tar.gz
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function download($args, $assoc_args) {

    // Grab associative arguments.
    $version = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'version', 'stable');
    $l10n = (bool) \WP_CLI\Utils\get_flag_value($assoc_args, 'l10n', FALSE);
    $download_dir = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'destination', \WP_CLI\Utils\get_temp_dir());
    $insecure = (bool) \WP_CLI\Utils\get_flag_value($assoc_args, 'insecure', FALSE);
    $extract = (bool) \WP_CLI\Utils\get_flag_value($assoc_args, 'extract', FALSE);

    // Maybe create destination directory.
    if (!is_dir($download_dir)) {
      if (!is_writable(dirname($download_dir))) {
        WP_CLI::error("Insufficient permission to create directory '{$download_dir}'.");
      }
      WP_CLI::log("Creating directory '{$download_dir}'.");
      // Recursively create directory.
      if (!@mkdir($download_dir, 0777, TRUE)) {
        $error = error_get_last();
        WP_CLI::error("Failed to create directory '{$download_dir}': {$error['message']}.");
      }
    }

    // Sanity check.
    if (!is_writable($download_dir)) {
      WP_CLI::error("'{$download_dir}' is not writable by current user.");
    }

    // Use "wp civicrm core check-version" to find out which file to download.
    WP_CLI::log(WP_CLI::colorize('%GChecking' . (empty($l10n) ? '' : ' localization') . ' file to download...%n'));
    $options = ['launch' => FALSE, 'return' => TRUE];
    $command = 'civicrm core check-version --version=' . $version . ' --format=url' . (empty($l10n) ? '' : ' --l10n');
    $url = WP_CLI::runcommand($command, $options);

    // Configure the download.
    $headers = [];
    $options = [
      'insecure' => (bool) $insecure,
    ];

    // Do the download now.
    WP_CLI::log(WP_CLI::colorize('%GDownloading file...%n'));
    $archive = $this->file_download($url, $download_dir, $headers, $options);

    // Stop early if not extracting.
    if (empty($extract)) {
      if (empty($l10n)) {
        WP_CLI::success(sprintf(WP_CLI::colorize('CiviCRM downloaded to: %Y%s%n'), $download_dir));
      }
      else {
        WP_CLI::success(sprintf(WP_CLI::colorize('CiviCRM localization downloaded to: %Y%s%n'), $download_dir));
      }
      if (!empty(WP_CLI::get_config('quiet'))) {
        echo $archive . "\n";
      }
      WP_CLI::halt(0);
    }

    // Extract the download.
    if (empty($l10n)) {
      if (!$this->unzip($archive, $download_dir)) {
        WP_CLI::error('Could not extract zipfile.');
      }
      WP_CLI::success(sprintf(WP_CLI::colorize('CiviCRM downloaded and extracted to: %Y%s%n'), $download_dir));
    }
    else {
      if (!$this->untar($archive, $download_dir)) {
        WP_CLI::error('Could not extract tarfile.');
      }
      WP_CLI::success(sprintf(WP_CLI::colorize('CiviCRM localization downloaded and extracted to: %Y%s%n'), $download_dir));
    }

  }

  /**
   * Install the CiviCRM plugin.
   *
   * This command obviously can't be used until the CiviCRM plugin has been installed.
   * It is included here for completeness and in preparation for creating a package.
   * If you want to use this command, you can install the CLI Tools for CiviCRM plugin
   * so that no conflicts occur when you call it.
   *
   * ## OPTIONS
   *
   * [--version=<version>]
   * : Specify the CiviCRM version to get. Accepts a version number, 'stable', 'rc' or 'nightly'. Defaults to latest stable version.
   *
   * [--zipfile=<zipfile>]
   * : Path to your CiviCRM zip file. If specified --version is ignored.
   *
   * [--l10n]
   * : Additionally install the localization files for the specified version.
   *
   * [--l10n-tarfile=<l10n-tarfile>]
   * : Path to your l10n tar.gz file. If specified --l10n is ignored.
   *
   * [--force]
   * : If set, the command will overwrite any installed version of the plugin, without prompting for confirmation.
   *
   * ## EXAMPLES
   *
   *     # Install the current stable version of CiviCRM.
   *     $ wp civicrm core install
   *     Success: Installed 1 of 1 plugins.
   *
   *     # Install the current stable version of CiviCRM with localization files.
   *     $ wp civicrm core install --l10n
   *     Success: Installed 1 of 1 plugins.
   *     Success: CiviCRM localization downloaded and extracted to: /wp-content/plugins/civicrm
   *
   *     # Install a specific version of CiviCRM.
   *     $ wp civicrm core install --version=5.56.2
   *     Success: Installed 1 of 1 plugins.
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function install($args, $assoc_args) {

    // Grab associative arguments.
    $version = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'version', 'stable');
    $zipfile = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'zipfile', '');
    $l10n = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'l10n', '');
    $l10n_tarfile = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'l10n-tarfile', '');
    $force = (bool) \WP_CLI\Utils\get_flag_value($assoc_args, 'force', FALSE);

    // Get the path to the CiviCRM plugin directory.
    $plugin_path = $this->plugin_path_get();

    // Only install plugin if not already installed.
    $fetcher = new \WP_CLI\Fetchers\Plugin();
    $installed = $fetcher->get('civicrm');
    if (!$installed || !empty($force)) {

      // When no zipfile is specified.
      if (!empty($version) && empty($zipfile)) {

        // Use "wp civicrm core check-version" to find out which file to download.
        WP_CLI::log(WP_CLI::colorize('%GChecking plugin file to download...%n'));
        $options = ['launch' => FALSE, 'return' => TRUE];
        $command = 'civicrm core check-version --version=' . $version . ' --format=url';
        $url = WP_CLI::runcommand($command, $options);

        // Use "wp plugin install" to install CiviCRM core.
        $options = ['launch' => FALSE, 'return' => FALSE];
        $command = 'plugin install ' . $url . (empty($force) ? '' : ' --force');
        WP_CLI::runcommand($command, $options);

      }
      elseif (!empty($zipfile)) {

        // Default extraction options.
        $extract_options = [
          'clear_destination' => FALSE,
          'clear_working' => FALSE,
        ];

        // If forcing, overwrite existing CiviCRM plugin directory.
        if (!empty($force)) {
          $extract_options['clear_destination'] = TRUE;
        }

        // Let's do it.
        $this->zip_extract($zipfile, $plugin_path, $extract_options);

      }

    }
    elseif (empty($l10n) && empty($l10n_tarfile)) {

      // Bail when plugin is installed and no localization archive is specified.
      WP_CLI::error('CiviCRM is already installed.');

    }

    // When localization is wanted but no archive is specified.
    if (!empty($l10n) && empty($l10n_tarfile)) {

      // Use "wp civicrm core check-version" to find out which file to download.
      $options = ['launch' => FALSE, 'return' => TRUE];
      $command = 'civicrm core check-version --version=' . $version . ' --l10n --format=url';
      $url = WP_CLI::runcommand($command, $options);

      // Use "wp civicrm core download" to download and extract.
      $options = ['launch' => FALSE, 'return' => FALSE];
      $command = 'civicrm core download --version=' . $version . ' --l10n --extract --destination=' . $plugin_path;
      WP_CLI::runcommand($command, $options);

    }
    elseif (!empty($l10n_tarfile)) {

      // Extract localization archive to plugin directory.
      WP_CLI::log(sprintf(WP_CLI::colorize('Extracting localization archive to: %y%s%n'), $plugin_path));
      if (!$this->untar($l10n_tarfile, $plugin_path)) {
        WP_CLI::error('Could not extract localization archive.');
      }
      WP_CLI::success(sprintf(WP_CLI::colorize('CiviCRM localization files extracted to: %Y%s%n'), $plugin_path));

    }

  }

  /**
   * Restore the CiviCRM plugin files and database from a backup created with `wp civicrm backup`.
   *
   * ## OPTIONS
   *
   * [--backup-dir=<backup-dir>]
   * : Path to your CiviCRM backup directory. Default is one level above ABSPATH.
   *
   * [--also-include=<also-include>]
   * : Comma separated list of additional tables to restore based on wildcard search. Ensures existing tables are cleared.
   *
   * [--yes]
   * : Answer yes to the confirmation message.
   *
   * ## EXAMPLES
   *
   *     # Standard restore.
   *     $ wp civicrm core restore
   *     Gathering system information.
   *     +------------------------+-----------------------------------------------------------------------+
   *     | Field                  | Value                                                                 |
   *     +------------------------+-----------------------------------------------------------------------+
   *     | Backup directory       | /example.com/civicrm-backup                                           |
   *     | Plugin path            | /example.com/httpdocs/wp-content/plugins/civicrm/                     |
   *     | Database name          | civicrm_db                                                            |
   *     | Database username      | dbuser                                                                |
   *     | Database password      | dbpassword                                                            |
   *     | Database host          | localhost                                                             |
   *     | Settings file          | /example.com/httpdocs/wp-content/uploads/civicrm/civicrm.settings.php |
   *     | Config and Log         | /example.com/httpdocs/wp-content/uploads/civicrm/ConfigAndLog/        |
   *     | Custom PHP             | Not found                                                             |
   *     | Custom templates       | Not found                                                             |
   *     | Compiled templates     | /example.com/httpdocs/wp-content/uploads/civicrm/templates_c/         |
   *     | Extensions directory   | /example.com/httpdocs/wp-content/uploads/civicrm/ext/                 |
   *     | Uploads directory      | /example.com/httpdocs/wp-content/uploads/civicrm/upload/              |
   *     | Image upload directory | /example.com/httpdocs/wp-content/uploads/civicrm/persist/contribute/  |
   *     | File upload directory  | /example.com/httpdocs/wp-content/uploads/civicrm/custom/              |
   *     +------------------------+-----------------------------------------------------------------------+
   *     Do you want to continue? [y/n] y
   *
   *     # Also restore tables not registered with CiviCRM.
   *     # In this case, also correctly restores tables for the "Canadian Tax Receipts" extension.
   *     $ wp civicrm core restore --also-include='cdntaxreceipts_*'
   *     ...
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function restore($args, $assoc_args) {

    // Grab associative arguments.
    $backup_dir = \WP_CLI\Utils\get_flag_value($assoc_args, 'backup-dir', trailingslashit(dirname(ABSPATH)) . 'civicrm-backup');
    $also_include = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'also-include', '');

    // ----------------------------------------------------------------------------
    // Build feedback table.
    // ----------------------------------------------------------------------------
    WP_CLI::log(WP_CLI::colorize('%GGathering system information.%n'));

    // Bootstrap CiviCRM.
    $this->bootstrap_civicrm();

    // Let's have a look for some CiviCRM variables.
    $config = CRM_Core_Config::singleton();

    // Build feedback.
    $feedback = [];
    if (!empty($backup_dir)) {
      $feedback['Backup directory'] = $backup_dir;
    }
    if (defined('CIVICRM_PLUGIN_DIR')) {
      $feedback['Plugin path'] = CIVICRM_PLUGIN_DIR;
    }
    else {
      $feedback['Plugin path'] = 'Not found';
    }
    if (defined('CIVICRM_DSN')) {
      $dsn = DB::parseDSN(CIVICRM_DSN);
      $feedback['Database name'] = $dsn['database'];
      $feedback['Database username'] = $dsn['username'];
      $feedback['Database password'] = $dsn['password'];
      $feedback['Database host'] = $dsn['hostspec'];
    }
    else {
      $feedback['Database Settings'] = 'Not found';
    }
    if (defined('CIVICRM_SETTINGS_PATH')) {
      $feedback['Settings file'] = CIVICRM_SETTINGS_PATH;
    }
    else {
      $feedback['Settings file'] = 'Not found';
    }
    if (!empty($config->configAndLogDir)) {
      $feedback['Config and Log'] = $config->configAndLogDir;
    }
    else {
      $feedback['Config and Log'] = 'Not found';
    }
    if (!empty($config->customPHPPathDir)) {
      $feedback['Custom PHP'] = $config->customPHPPathDir;
    }
    else {
      $feedback['Custom PHP'] = 'Not found';
    }
    if (!empty($config->customTemplateDir)) {
      $feedback['Custom templates'] = $config->customTemplateDir;
    }
    else {
      $feedback['Custom templates'] = 'Not found';
    }
    if (!empty($config->templateCompileDir)) {
      $feedback['Compiled templates'] = $config->templateCompileDir;
    }
    else {
      $feedback['Compiled templates'] = 'Not found';
    }
    if (!empty($config->extensionsDir)) {
      $feedback['Extensions directory'] = $config->extensionsDir;
    }
    else {
      $feedback['Extensions directory'] = 'Not found';
    }
    if (!empty($config->uploadDir)) {
      $feedback['Uploads directory'] = $config->uploadDir;
    }
    else {
      $feedback['Uploads directory'] = 'Not found';
    }
    if (!empty($config->imageUploadDir)) {
      $feedback['Image upload directory'] = $config->imageUploadDir;
    }
    else {
      $feedback['Image upload directory'] = 'Not found';
    }
    if (!empty($config->customFileUploadDir)) {
      $feedback['File upload directory'] = $config->customFileUploadDir;
    }
    else {
      $feedback['File upload directory'] = 'Not found';
    }

    // Render feedback.
    $assoc_args['fields'] = array_keys($feedback);
    $formatter = $this->formatter_get($assoc_args);
    $formatter->display_item($feedback);

    // Let's give folks a chance to exit now.
    WP_CLI::confirm(WP_CLI::colorize('%GDo you want to continue?%n'), $assoc_args);

    // Ensure there's no trailing slash.
    $backup_dir = untrailingslashit($backup_dir);

    // ----------------------------------------------------------------------------
    // Restore procedure.
    // ----------------------------------------------------------------------------

    // Restore File Uploads directory.
    if (!empty($config->customFileUploadDir) && file_exists($backup_dir . '/civicrm-file-uploads.zip')) {
      WP_CLI::log('');
      WP_CLI::log(WP_CLI::colorize('%GRestoring File Uploads directory...%n'));
      $zipfile = $backup_dir . '/civicrm-file-uploads.zip';
      $destination = untrailingslashit($config->customFileUploadDir);
      $this->zip_overwrite($zipfile, $destination);
      WP_CLI::success('File Uploads directory restored.');
    }

    // Restore Image upload directory.
    if (!empty($config->imageUploadDir) && file_exists($backup_dir . '/civicrm-image-uploads.zip')) {
      WP_CLI::log('');
      WP_CLI::log(WP_CLI::colorize('%GRestoring Image Uploads directory...%n'));
      $zipfile = $backup_dir . '/civicrm-image-uploads.zip';
      $destination = untrailingslashit($config->imageUploadDir);
      $this->zip_overwrite($zipfile, $destination);
      WP_CLI::success('Image Uploads directory restored.');
    }

    // Restore Uploads directory.
    if (!empty($config->uploadDir) && file_exists($backup_dir . '/civicrm-uploads.zip')) {
      WP_CLI::log('');
      WP_CLI::log(WP_CLI::colorize('%GRestoring Uploads directory...%n'));
      $zipfile = $backup_dir . '/civicrm-uploads.zip';
      $destination = untrailingslashit($config->uploadDir);
      $this->zip_overwrite($zipfile, $destination);
      WP_CLI::success('Uploads directory restored.');
    }

    // Restore Extensions directory.
    if (!empty($config->extensionsDir) && file_exists($backup_dir . '/civicrm-extensions.zip')) {
      WP_CLI::log('');
      WP_CLI::log(WP_CLI::colorize('%GRestoring Extensions directory...%n'));
      $zipfile = $backup_dir . '/civicrm-extensions.zip';
      $destination = untrailingslashit($config->extensionsDir);
      $this->zip_overwrite($zipfile, $destination);
      WP_CLI::success('Extensions directory restored.');
    }

    // Restore Compiled templates directory.
    if (!empty($config->templateCompileDir) && file_exists($backup_dir . '/civicrm-compiled-templates.zip')) {
      WP_CLI::log('');
      WP_CLI::log(WP_CLI::colorize('%GRestoring Compiled Templates directory...%n'));
      $zipfile = $backup_dir . '/civicrm-compiled-templates.zip';
      $destination = untrailingslashit($config->templateCompileDir);
      $this->zip_overwrite($zipfile, $destination);
      WP_CLI::success('Compiled Templates directory restored.');
    }

    // Restore Custom templates directory.
    if (!empty($config->customTemplateDir) && file_exists($backup_dir . '/civicrm-custom-templates.zip')) {
      WP_CLI::log('');
      WP_CLI::log(WP_CLI::colorize('%GRestoring Custom Templates directory...%n'));
      $zipfile = $backup_dir . '/civicrm-custom-templates.zip';
      $destination = untrailingslashit($config->customTemplateDir);
      $this->zip_overwrite($zipfile, $destination);
      WP_CLI::success('Custom Templates directory restored.');
    }

    // Restore Custom PHP directory.
    if (!empty($config->customPHPPathDir) && file_exists($backup_dir . '/civicrm-custom-php.zip')) {
      WP_CLI::log('');
      WP_CLI::log(WP_CLI::colorize('%GRestoring Custom PHP directory...%n'));
      $zipfile = $backup_dir . '/civicrm-custom-php.zip';
      $destination = untrailingslashit($config->customPHPPathDir);
      $this->zip_overwrite($zipfile, $destination);
      WP_CLI::success('Custom PHP directory restored.');
    }

    // Restore Config and Log directory.
    if (!empty($config->configAndLogDir) && file_exists($backup_dir . '/civicrm-config-log.zip')) {
      WP_CLI::log('');
      WP_CLI::log(WP_CLI::colorize('%GRestoring Config and Log directory...%n'));
      $zipfile = $backup_dir . '/civicrm-config-log.zip';
      $destination = untrailingslashit($config->configAndLogDir);
      $this->zip_overwrite($zipfile, $destination);
      WP_CLI::success('Config and Log directory restored.');
    }

    // Restore "civicrm.settings.php" file.
    if (defined('CIVICRM_SETTINGS_PATH') && file_exists($backup_dir . '/civicrm.settings.php')) {
      WP_CLI::log('');
      WP_CLI::log(WP_CLI::colorize('%GRestoring Settings File...%n'));
      $source_path = $backup_dir . '/civicrm.settings.php';
      if (file_exists(CIVICRM_SETTINGS_PATH) && is_writable(CIVICRM_SETTINGS_PATH)) {
        copy(CIVICRM_SETTINGS_PATH, $source_path);
      }
      elseif (!file_exists(CIVICRM_SETTINGS_PATH)) {
        copy($source_path, CIVICRM_SETTINGS_PATH);
      }
      else {
        WP_CLI::error("Could not restore '" . CIVICRM_SETTINGS_PATH . "' from backup directory.");
      }
      WP_CLI::success('Settings File restored.');
    }

    // Use "wp plugin install" to restore plugin directory.
    if (file_exists($backup_dir . '/civicrm.zip')) {
      WP_CLI::log('');
      WP_CLI::log(WP_CLI::colorize('%GRestoring plugin directory...%n'));
      $options = ['launch' => FALSE, 'return' => FALSE];
      $command = 'plugin install ' . $backup_dir . '/civicrm.zip --force';
      WP_CLI::runcommand($command, $options);
      WP_CLI::success('Plugin directory restored.');
    }

    // Use "wp civicrm db clear" and "wp civicrm db import" to restore database.
    if (defined('CIVICRM_DSN') && file_exists($backup_dir . '/civicrm-db.sql')) {

      WP_CLI::log('');
      WP_CLI::log(WP_CLI::colorize('%GRestoring database...%n'));

      // Maybe add extra filters.
      $also_include_args = '';
      if (!empty($also_include)) {
        $also_include_args = " --also-include={$also_include}";
      }

      // Clear existing tables.
      $command = 'civicrm db clear' . $also_include_args;
      $options = ['launch' => FALSE, 'return' => FALSE];
      WP_CLI::runcommand($command, $options);

      // Load backup tables.
      $command = 'civicrm db import --load-file=' . $backup_dir . '/civicrm-db.sql';
      $options = ['launch' => FALSE, 'return' => FALSE];
      WP_CLI::runcommand($command, $options);

      WP_CLI::success('Database restored.');

    }

  }

  /**
   * Upgrade the CiviCRM plugin files and database.
   *
   * ## OPTIONS
   *
   * [--version=<version>]
   * : Specify the CiviCRM version to get. Accepts a version number, 'stable', 'rc' or 'nightly'. Defaults to latest stable version.
   *
   * [--zipfile=<zipfile>]
   * : Path to your CiviCRM zip file. If specified --version is ignored.
   *
   * [--l10n]
   * : Additionally install the localization files for the specified version.
   *
   * [--l10n-tarfile=<l10n-tarfile>]
   * : Path to your l10n tar.gz file. If specified --l10n is ignored.
   *
   * [--yes]
   * : Answer yes to the confirmation message.
   *
   * ## EXAMPLES
   *
   *     # Update to the current stable version of CiviCRM.
   *     $ wp civicrm core update
   *     Success: Installed 1 of 1 plugins.
   *
   * @alias upgrade
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function update($args, $assoc_args) {

    // Grab associative arguments.
    $version = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'version', 'stable');
    $zipfile = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'zipfile', '');
    $l10n = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'l10n', '');
    $l10n_tarfile = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'l10n-tarfile', '');

    WP_CLI::log(WP_CLI::colorize('%GGathering system information.%n'));

    // Bootstrap CiviCRM.
    $this->bootstrap_civicrm();

    // Let's have a look for some CiviCRM variables.
    global $civicrm_root;
    $config = CRM_Core_Config::singleton();

    // ----------------------------------------------------------------------------
    // Build feedback table.
    // ----------------------------------------------------------------------------

    // Build feedback.
    $feedback = [];
    if (!empty($zipfile)) {
      $feedback['Plugin zip archive'] = $zipfile;
    }
    if (!empty($version) && empty($zipfile)) {
      $feedback['Requested version'] = $version;
      $options = ['launch' => FALSE, 'return' => TRUE];
      $command = 'civicrm core check-version --version=' . $version . ' --format=url';
      $archive = WP_CLI::runcommand($command, $options);
      // Maybe strip all the Google authentication stuff if present.
      if (FALSE !== strpos($archive, '?')) {
        $arr = explode('?', $archive);
        $archive = $arr[0];
      }
      $feedback['Requested archive'] = $archive;
    }
    if (defined('CIVICRM_PLUGIN_DIR')) {
      $feedback['Plugin path'] = CIVICRM_PLUGIN_DIR;
    }
    if (!empty($civicrm_root)) {
      $feedback['CiviCRM root'] = $civicrm_root;
    }
    if (defined('CIVICRM_DSN')) {
      $dsn = DB::parseDSN(CIVICRM_DSN);
      $feedback['Database name'] = $dsn['database'];
      $feedback['Database username'] = $dsn['username'];
      $feedback['Database password'] = $dsn['password'];
      $feedback['Database host'] = $dsn['hostspec'];
    }

    // Render feedback.
    $assoc_args['fields'] = array_keys($feedback);
    $formatter = $this->formatter_get($assoc_args);
    $formatter->display_item($feedback);

    // Let's give folks a chance to exit now.
    WP_CLI::confirm(WP_CLI::colorize('%GDo you want to continue?%n'), $assoc_args);

    // ----------------------------------------------------------------------------
    // Start upgrade.
    // ----------------------------------------------------------------------------

    // Enable Maintenance Mode.
    //WP_CLI::runcommand('maintenance-mode activate', ['launch' => FALSE, 'return' => FALSE]);

    // Build install command.
    $command = 'civicrm core install' .
      (empty($version) ? '' : ' --version=' . $version) .
      (empty($zipfile) ? '' : ' --zipfile=' . $zipfile) .
      (empty($l10n) ? '' : ' --l10n') .
      (empty($langtarfile) ? '' : ' --l10n-tarfile=' . $langtarfile) .
      ' --force';

    // Run "wp civicrm core install".
    $options = ['launch' => FALSE, 'return' => FALSE];
    WP_CLI::runcommand($command, $options);

    // Disable Maintenance Mode.
    //WP_CLI::runcommand('maintenance-mode deactivate', ['launch' => FALSE, 'return' => FALSE]);

  }

  /**
   * Upgrade the CiviCRM database schema.
   *
   * ## OPTIONS
   *
   * [--dry-run]
   * : Preview the list of upgrade tasks.
   *
   * [--retry]
   * : Resume a failed upgrade, retrying the last step.
   *
   * [--skip]
   * : Resume a failed upgrade, skipping the last step.
   *
   * [--step]
   * : Run the upgrade queue in steps, pausing before each step.
   *
   * [--v]
   * : Run the upgrade queue with verbose output.
   *
   * [--vv]
   * : Run the upgrade queue with extra verbose output.
   *
   * [--vvv]
   * : An alias of --vv for old timers more used to cv syntax.
   *
   * [--yes]
   * : Answer yes to the confirmation messages. Does not apply to step messages.
   *
   * ## EXAMPLES
   *
   *     $ wp civicrm core update-db --dry-run --v
   *     Found CiviCRM code version: 5.57.1
   *     Found CiviCRM database version: 5.57.0
   *     Checking pre-upgrade messages.
   *     (No messages)
   *     Dropping SQL triggers.
   *     Preparing upgrade.
   *     Executing upgrade.
   *     Cleanup old files
   *     Cleanup old upgrade snapshots
   *     Checking extensions
   *     Finish Upgrade DB to 5.57.1
   *     Update all reserved message templates
   *     Finish core DB updates 5.57.1
   *     Assess extension upgrades
   *     Generate final messages
   *     Finishing upgrade.
   *     Upgrade to 5.57.1 completed.
   *     Checking post-upgrade messages.
   *     (No messages)
   *     Have a nice day.
   *
   * @subcommand update-db
   *
   * @alias upgrade-db
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function update_db($args, $assoc_args) {

    // Bootstrap CiviCRM.
    $this->bootstrap_civicrm();

    if (!defined('CIVICRM_UPGRADE_ACTIVE')) {
      define('CIVICRM_UPGRADE_ACTIVE', 1);
    }

    // Check whether an upgrade is necessary.
    $code_version = CRM_Utils_System::version();
    WP_CLI::log(sprintf(WP_CLI::colorize('%GFound CiviCRM code version:%n %Y%s%n'), $code_version));
    $db_version = CRM_Core_BAO_Domain::version();
    WP_CLI::log(sprintf(WP_CLI::colorize('%GFound CiviCRM database version:%n %Y%s%n'), $db_version));
    if (version_compare($code_version, $db_version) === 0) {
      WP_CLI::success(sprintf('You are already upgraded to CiviCRM %s', $code_version));
      WP_CLI::halt(0);
    }

    // Get flags.
    $dry_run = \WP_CLI\Utils\get_flag_value($assoc_args, 'dry-run', FALSE);
    $retry = \WP_CLI\Utils\get_flag_value($assoc_args, 'retry', FALSE);
    $skip = \WP_CLI\Utils\get_flag_value($assoc_args, 'skip', FALSE);
    $step = \WP_CLI\Utils\get_flag_value($assoc_args, 'step', FALSE);
    $first_try = (empty($retry) && empty($skip)) ? TRUE : FALSE;

    // Get verbosity.
    $verbose = \WP_CLI\Utils\get_flag_value($assoc_args, 'v', FALSE);
    $verbose_extra = \WP_CLI\Utils\get_flag_value($assoc_args, 'vv', FALSE);
    $verbose_old_skool = \WP_CLI\Utils\get_flag_value($assoc_args, 'vvv', FALSE);
    if (!empty($verbose_old_skool)) {
      $verbose_extra = TRUE;
    }

    // When stepping, we need at least "verbose".
    if (!empty($step)) {
      if (empty($verbose_extra) && empty($verbose)) {
        $verbose = TRUE;
      }
    }

    // Bail if incomplete upgrade.
    if ($first_try && FALSE !== stripos($db_version, 'upgrade')) {
      WP_CLI::error('Cannot begin upgrade: The database indicates that an incomplete upgrade is pending. If you would like to resume, use --retry or --skip.');
    }

    // Bootstrap upgrader.
    $upgrade = new CRM_Upgrade_Form();
    $error = $upgrade->checkUpgradeableVersion($db_version, $code_version);
    if (!empty($error)) {
      WP_CLI::error($error);
    }

    // Check pre-upgrade messages.
    if ($first_try) {
      WP_CLI::log(WP_CLI::colorize('%gChecking pre-upgrade messages.%n'));
      $preUpgradeMessage = NULL;
      $upgrade->setPreUpgradeMessage($preUpgradeMessage, $db_version, $code_version);
      if ($preUpgradeMessage) {
        WP_CLI::log(CRM_Utils_String::htmlToText($preUpgradeMessage));
        WP_CLI::confirm(WP_CLI::colorize('%GDo you want to continue?%n'), $assoc_args);
      }
      else {
        WP_CLI::log('(No messages)');
      }
    }

    // Why is dropTriggers() hard-coded? Can't we just enqueue this as part of buildQueue()?
    if ($first_try) {
      WP_CLI::log(WP_CLI::colorize('%gDropping SQL triggers.%n'));
      if (empty($dry_run)) {
        CRM_Core_DAO::dropTriggers();
      }
    }

    // Let's create a file for storing upgrade messages.
    $post_upgrade_message_file = CRM_Utils_File::tempnam('civicrm-post-upgrade');

    // Build the queue.
    if ($first_try) {
      WP_CLI::log(WP_CLI::colorize('%gPreparing upgrade.%n'));
      $queue = CRM_Upgrade_Form::buildQueue($db_version, $code_version, $post_upgrade_message_file);
      // Sanity check - only SQL queues can be resumed.
      if (!($queue instanceof CRM_Queue_Queue_Sql)) {
        WP_CLI::error('The "upgrade-db" command only supports SQL-based queues.');
      }
    }
    else {
      WP_CLI::log(WP_CLI::colorize('%Resuming upgrade.%n'));
      $queue = CRM_Queue_Service::singleton()->load([
        'name' => CRM_Upgrade_Form::QUEUE_NAME,
        'type' => 'Sql',
      ]);
      if ($skip) {
        $item = $queue->stealItem();
        if (!empty($item->data->title)) {
          WP_CLI::log(sprintf('Skip task: %s', $item->data->title));
          $queue->deleteItem($item);
        }
      }
    }

    // Start the upgrade.
    WP_CLI::log(WP_CLI::colorize('%gExecuting upgrade.%n'));
    set_time_limit(0);

    // Mimic what "Console Queue Runner" does.
    $task_context = new CRM_Queue_TaskContext();
    $task_context->queue = $queue;

    // Maybe suppress Task Context logger output.
    if (empty($verbose_extra) && empty($verbose)) {
      if (!class_exists('CLI_Tools_CiviCRM_Logger_Dummy')) {
        require_once __DIR__ . '/utilities/class-logger-dummy.php';
      }
      $task_context->log = new CLI_Tools_CiviCRM_Logger_Dummy();
    }
    else {
      $task_context->log = \Log::singleton('display');
    }

    while ($queue->numberOfItems()) {

      // In case we're retrying a failed job.
      $item = $queue->stealItem();
      $task = $item->data;

      // Feedback.
      if (!empty($verbose_extra)) {
        $feedback = self::task_callback_format($task);
        WP_CLI::log(WP_CLI::colorize('%g' . $task->title . '%n') . ' ' . WP_CLI::colorize($feedback));
      }
      elseif (!empty($verbose)) {
        WP_CLI::log(WP_CLI::colorize('%g' . $task->title . '%n'));
      }
      else {
        echo '.';
      }

      // Get action.
      $action = 'y';
      if (!empty($step)) {
        fwrite(STDOUT, 'Execute this step? [ y=yes / s=skip / a=abort ] ');
        $action = strtolower(trim(fgets(STDIN)));
      }

      // Bail if skip action is "abort".
      if ($action === 'a') {
        WP_CLI::halt(1);
      }

      // Run the task when action is "yes".
      if ($action === 'y' && empty($dry_run)) {
        try {
          $success = $task->run($task_context);
          if (!$success) {
            WP_CLI::error('Task returned false');
          }
        }
        catch (\Exception $e) {
          // WISHLIST: For interactive mode, perhaps allow retry/skip?
          WP_CLI::log(sprintf(WP_CLI::colorize('%RError executing task%n %Y"%s"%n'), $task->title));
          WP_CLI::log('');
          WP_CLI::log(WP_CLI::colorize('%RError message:%n'));
          WP_CLI::log(sprintf(WP_CLI::colorize('%r%s%n'), $e->getMessage()));
          WP_CLI::log('');
          WP_CLI::log(WP_CLI::colorize('%RStack trace:%n'));
          WP_CLI::log(sprintf(WP_CLI::colorize('%r%s%n'), $e->getTraceAsString()));
          WP_CLI::log('');
          WP_CLI::error('Could not complete database update.');
        }
      }

      $queue->deleteItem($item);

    }

    // End feedback.
    if (empty($verbose_extra) && empty($verbose)) {
      echo "\n";
    }

    WP_CLI::log(WP_CLI::colorize('%gFinishing upgrade.%n'));
    if (empty($dry_run)) {
      CRM_Upgrade_Form::doFinish();
    }

    WP_CLI::log(sprintf(WP_CLI::colorize('%GUpgrade to%n %Y%s%n %Gcompleted.%n'), $code_version));

    if (version_compare($code_version, '5.26.alpha', '<')) {
      // Work-around for bugs like dev/core#1713.
      WP_CLI::log(WP_CLI::colorize('%GDetected CiviCRM 5.25 or earlier. Force flush.%n'));
      if (empty($dry_run)) {
        \Civi\Cv\Util\Cv::passthru('flush');
      }
    }

    WP_CLI::log(WP_CLI::colorize('%GChecking post-upgrade messages.%n'));
    $message = file_get_contents($post_upgrade_message_file);
    if ($message) {
      WP_CLI::log(CRM_Utils_String::htmlToText($message));
    }
    else {
      WP_CLI::log('(No messages)');
    }

    // Remove file for storing upgrade messages.
    unlink($post_upgrade_message_file);

    WP_CLI::log(WP_CLI::colorize('%GHave a nice day.%n'));

  }

  /**
   * Reset paths to correct config settings.
   *
   * This command can be useful when the CiviCRM site has been cloned or migrated.
   *
   * The old version of this command tried to preserve webserver ownership of "templates_c"
   * and "civicrm/upload" because (when running this command as something other than the
   * web-user) `doSiteMove` clears and recreates these directories. The check took place
   * *after* `doSiteMove` had run, however, so would only report back the current user and
   * group ownership.
   *
   * If you run this command as something other than the web-user, it's up to you to assign
   * correct user and group permissions for these directories.
   *
   * ## EXAMPLES
   *
   *     $ wp civicrm core update-cfg
   *     Beginning site move process...
   *     Template cache and upload directory have been cleared.
   *     Database cache tables cleared.
   *     Session has been reset.
   *     Please make sure the following directories have the correct permissions:
   *     /example.com/httpdocs/wp-content/uploads/civicrm/templates_c/
   *     /example.com/httpdocs/wp-content/uploads/civicrm/upload/
   *     Success: Config successfully updated.
   *
   * @subcommand update-cfg
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function update_cfg($args, $assoc_args) {

    // Bootstrap CiviCRM.
    $this->bootstrap_civicrm();

    // Do site move.
    $result = CRM_Core_BAO_ConfigSetting::doSiteMove();

    // Bail on error.
    if (empty($result)) {
      WP_CLI::error('Config update failed.');
    }

    // Result is HTML, so format and show.
    $results = explode('<br />', $result);
    foreach ($results as $result) {
      if (!empty($result)) {
        WP_CLI::log($result);
      }
    }

    // Show permissions reminder.
    $config = CRM_Core_Config::singleton();
    WP_CLI::log('Please make sure the following directories have the correct permissions:');
    if (!empty($config->templateCompileDir)) {
      WP_CLI::log(sprintf(WP_CLI::colorize('%y%s%n'), $config->templateCompileDir));
    }
    if (!empty($config->uploadDir)) {
      WP_CLI::log(sprintf(WP_CLI::colorize('%y%s%n'), $config->uploadDir));
    }

    WP_CLI::success('Config successfully updated.');

  }

  /**
   * Get the current version of the CiviCRM plugin and database.
   *
   * ## OPTIONS
   *
   * [--source=<source>]
   * : Specify the version to get.
   * ---
   * default: all
   * options:
   *   - all
   *   - plugin
   *   - db
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
   *     # Get all CiviCRM version information.
   *     $ wp civicrm core version
   *     +----------+---------+
   *     | Source   | Version |
   *     +----------+---------+
   *     | Plugin   | 5.57.1  |
   *     | Database | 5.46.3  |
   *     +----------+---------+
   *
   *     # Get just the CiviCRM database version number.
   *     $ wp civicrm core version --source=db --format=number
   *     5.46.3
   *
   *     # Get just the CiviCRM plugin version number.
   *     $ wp civicrm core version --source=plugin --format=number
   *     5.57.1
   *
   *     # Get all CiviCRM version information as JSON-formatted data.
   *     $ wp civicrm core version --format=json
   *     {"plugin":"5.57.1","db":"5.46.3"}
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function version($args, $assoc_args) {

    // Grab associative arguments.
    $source = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'source', 'all');
    $format = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'format', 'table');

    // Bootstrap CiviCRM.
    $this->bootstrap_civicrm();

    // Get the data we want.
    $plugin_version = CRM_Utils_System::version();
    $db_version = CRM_Core_BAO_Domain::version();

    switch ($format) {

      // Version number-only output.
      case 'number':
        if (!in_array($source, ['db', 'plugin'])) {
          WP_CLI::error(WP_CLI::colorize('You must specify %Y--source=plugin%n or %Y--source=db%n to use this output format.'));
        }
        if ('plugin' === $source) {
          echo $plugin_version . "\n";
        }
        if ('db' === $source) {
          echo $db_version . "\n";
        }
        break;

      // Display output as json.
      case 'json':
        $info = [];
        if (in_array($source, ['all', 'plugin'])) {
          $info['plugin'] = $plugin_version;
        }
        if (in_array($source, ['all', 'db'])) {
          $info['db'] = $db_version;
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
        $fields = ['Source', 'Version'];
        if (in_array($source, ['all', 'plugin'])) {
          $rows[] = [
            'Source' => 'Plugin',
            'Version' => $plugin_version,
          ];
        }
        if (in_array($source, ['all', 'db'])) {
          $rows[] = [
            'Source' => 'Database',
            'Version' => $db_version,
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
   * Determine what action to take to resolve a conflict.
   *
   * @since 5.69
   *
   * @param string $title The thing which had a conflict.
   * @return string One of 'abort', 'keep' or 'overwrite'.
   */
  private function conflict_action_pick($title) {

    WP_CLI::log(sprintf(WP_CLI::colorize('%GThe%n %Y%s%n %Galready exists.%n'), $title));
    WP_CLI::log(WP_CLI::colorize('%G[a]%n %gAbort. (Default.)%n'));
    WP_CLI::log(sprintf(WP_CLI::colorize('%G[k]%n %gKeep existing%n %y%s%n%g.%n %r(%n%RWARNING:%n %rThis may fail if the existing version is out-of-date.)%n'), $title));
    WP_CLI::log(sprintf(WP_CLI::colorize('%G[o]%n %gOverwrite with new%n %y%s%g.%n %r(%n%RWARNING:%n %rThis may destroy data.)%n'), $title));

    fwrite(STDOUT, WP_CLI::colorize('%GWhat you like to do?%n '));
    $action = strtolower(trim(fgets(STDIN)));
    switch ($action) {
      case 'k':
        return 'keep';

      case 'o':
        return 'overwrite';

      case 'a':
      default:
        return 'abort';
    }

  }

  /**
   * Recursively implode an array.
   *
   * @since 5.69
   *
   * @param array $value The array to implode.
   * @param integer $level The current level.
   * @return string
   */
  private static function implode_recursive($value, $level = 0) {

    // Maybe recurse.
    $array = [];
    if (is_array($value)) {
      foreach ($value as $val) {
        if (is_array($val)) {
          $array[] = self::implode_recursive($val, $level + 1);
        }
        else {
          $array[] = $val;
        }
      }
    }
    else {
      $array[] = $value;
    }

    // Wrap sub-arrays but leave top level alone.
    if ($level > 0) {
      $string = '[' . implode(',', $array) . ']';
    }
    else {
      $string = implode(',', $array);
    }

    return $string;

  }

  /**
   * Gets the array of CiviCRM stable release versions.
   *
   * @since 5.69
   *
   * @return array The array of CiviCRM stable release versions.
   */
  private function releases_get() {

    // Get all release versions.
    $url = $this->google_url . '&' . $this->google_prefix_stable . '&maxResults=1000';
    $result = $this->json_get_request($url);
    if (empty($result['prefixes'])) {
      return [];
    }

    // Strip out all but the version.
    array_walk($result['prefixes'], function(&$item) {
      $item = trim(str_replace('civicrm-stable/', '', $item));
      $item = trim(str_replace('/', '', $item));
    });

    // Sort by version.
    usort($result['prefixes'], 'version_compare');

    return $result['prefixes'];

  }

  /**
   * Gets the array of CiviCRM release data.
   *
   * @since 5.69
   *
   * @param string $release The CiviCRM release.
   * @return array $data The array of CiviCRM release data.
   */
  private function release_data_get($release) {

    // Get the release data.
    $url = $this->google_url . '&' . $this->google_prefix_stable . $release . '/';
    $result = $this->json_get_request($url);
    if (empty($result['items'])) {
      return [];
    }

    // Strip out all but the WordPress and l10n data.
    $data = [];
    foreach ($result['items'] as $item) {
      if (!empty($item['name'])) {
        if (FALSE !== strpos($item['name'], 'wordpress.zip')) {
          $data['WordPress'] = $item['name'];
        }
        if (FALSE !== strpos($item['name'], 'l10n.tar.gz')) {
          $data['L10n'] = $item['name'];
        }
      }
    }

    return $data;

  }

  /**
   * Format the task for when run with extra verbosity.
   *
   * This method re-builds the task arguments because some of them may themselves be arrays.
   *
   * @since 5.69
   *
   * @param CRM_Queue_Task $task The CiviCRM task object.
   * @return string $task The CiviCRM task object.
   */
  private static function task_callback_format($task) {

    $callback_info = implode('::', (array) $task->callback);
    $args_info = self::implode_recursive((array) $task->arguments);

    // Build string with colorization tokens.
    $feedback = '%y' . $callback_info . '(' . $args_info . '%n)';

    return $feedback;

  }

}
