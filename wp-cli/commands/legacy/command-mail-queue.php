<?php
/**
 * Process pending CiviMail mailing jobs.
 *
 * ## EXAMPLES
 *
 *     $ wp civicrm process-mail-queue
 *
 * @since 5.69
 */
class CLI_Tools_CiviCRM_Command_Mail_Queue extends CLI_Tools_CiviCRM_Command {

  /**
   * Process pending CiviMail mailing jobs. Deprecated: use `wp civicrm job mailing` instead.
   *
   * ## EXAMPLES
   *
   *     $ wp civicrm process-mail-queue
   *     Success: Executed 'process_mailing' job.
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function __invoke($args, $assoc_args) {

    WP_CLI::log(WP_CLI::colorize('%CDeprecated command:%n %cuse `wp civicrm job mailing` instead.%n'));

    // Pass on to "wp civicrm job mailing".
    $options = ['launch' => FALSE, 'return' => FALSE];
    WP_CLI::runcommand('civicrm job mailing', $options);

  }

}
