<?php
/**
 * Process pending CiviMember membership record update jobs.
 *
 * ## EXAMPLES
 *
 *     $ wp civicrm member-records
 *
 * @since 5.69
 */
class CLI_Tools_CiviCRM_Command_Member_Records extends CLI_Tools_CiviCRM_Command {

  /**
   * Process pending CiviMember membership record update jobs. Deprecated: use `wp civicrm job membership` instead.
   *
   * ## EXAMPLES
   *
   *     $ wp civicrm member-records
   *     Success: Executed 'process_membership' job.
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function __invoke($args, $assoc_args) {

    WP_CLI::log(WP_CLI::colorize('%CDeprecated command:%n %cuse `wp civicrm job membership` instead.%n'));

    // Pass on to "wp civicrm job membership".
    $options = ['launch' => FALSE, 'return' => FALSE];
    WP_CLI::runcommand('civicrm job membership', $options);

  }

}
