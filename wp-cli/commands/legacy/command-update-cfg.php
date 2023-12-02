<?php
/**
 * Upgrade the CiviCRM plugin files and database.
 *
 * ## EXAMPLES
 *
 *     $ wp civicrm update-cfg
 *
 * @since 5.69
 */
class CLI_Tools_CiviCRM_Command_Update_Config extends CLI_Tools_CiviCRM_Command {

  /**
   * Reset paths to correct config settings. Deprecated: use `wp civicrm core update-cfg` instead.
   *
   * This command can be useful when the CiviCRM site has been cloned or migrated.
   *
   * The old version of this command tried to preserve webserver ownership of "templates_c"
   * and "civicrm/upload" because (when running this command as something other than the
   * web-user) `doSiteMove` clears and recreates these directories. The check took place
   * *after* `doSiteMove` had run, however, so would only report back the current user and
   * group.
   *
   * If you run `wp-cli` as something other than the web-user, it's up to you to assign
   * correct ownership of these directories.
   *
   * ## EXAMPLES
   *
   *     $ wp civicrm update-cfg
   *     Beginning site move process...
   *     Template cache and upload directory have been cleared.
   *     Database cache tables cleared.
   *     Session has been reset.
   *     Please make sure the following directories have the correct permissions:
   *     /example.com/httpdocs/wp-content/uploads/civicrm/templates_c/
   *     /example.com/httpdocs/wp-content/uploads/civicrm/upload/
   *     Success: Config successfully updated.
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function __invoke($args, $assoc_args) {

    WP_CLI::log(WP_CLI::colorize('%CDeprecated command:%n %cuse `wp civicrm core update-cfg` instead.%n'));

    // Pass on to "wp civicrm core update-cfg".
    $options = ['launch' => FALSE, 'return' => FALSE];
    WP_CLI::runcommand('civicrm core update-cfg', $options);

  }

}
