<?php
/**
 * Disable debugging in CiviCRM.
 *
 * ## EXAMPLES
 *
 *     $ wp civicrm disable-debug
 *
 * @since 5.69
 */
class CLI_Tools_CiviCRM_Command_Debug_Disable extends CLI_Tools_CiviCRM_Command {

  /**
   * Disable debugging in CiviCRM. Deprecated: use `wp civicrm debug disable` instead.
   *
   * ## EXAMPLES
   *
   *     $ wp civicrm disable-debug
   *     Success: Debug setting disabled.
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function __invoke($args, $assoc_args) {

    WP_CLI::log(WP_CLI::colorize('%CDeprecated command:%n %cuse `wp civicrm debug disable` instead.%n'));

    // Pass on to "wp civicrm debug disable".
    $options = ['launch' => FALSE, 'return' => FALSE];
    WP_CLI::runcommand('civicrm debug disable', $options);

  }

}
