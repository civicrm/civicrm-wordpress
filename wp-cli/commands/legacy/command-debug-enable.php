<?php
/**
 * Enable debugging in CiviCRM.
 *
 * ## EXAMPLES
 *
 *     $ wp civicrm enable-debug
 *
 * @since 5.69
 */
class CLI_Tools_CiviCRM_Command_Debug_Enable extends CLI_Tools_CiviCRM_Command {

  /**
   * Enable debugging in CiviCRM. Deprecated: use `wp civicrm debug enable` instead.
   *
   * ## EXAMPLES
   *
   *     $ wp civicrm enable-debug
   *     Success: Debug setting enabled.
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function __invoke($args, $assoc_args) {

    WP_CLI::log(WP_CLI::colorize('%CDeprecated command:%n %cuse `wp civicrm debug enable` instead.%n'));

    // Pass on to "wp civicrm debug enable".
    $options = ['launch' => FALSE, 'return' => FALSE];
    WP_CLI::runcommand('civicrm debug enable', $options);

  }

}
