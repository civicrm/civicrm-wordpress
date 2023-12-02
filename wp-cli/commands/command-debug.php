<?php
/**
 * Enable or disable debugging in CiviCRM.
 *
 * ## EXAMPLES
 *
 *     $ wp civicrm debug enable
 *     Success: Debug setting enabled.
 *
 *     $ wp civicrm debug disable
 *     Success: Debug setting disabled.
 *
 * @since 5.69
 */
class CLI_Tools_CiviCRM_Command_Debug extends CLI_Tools_CiviCRM_Command {

  /**
   * Enable debugging in CiviCRM.
   *
   * ## EXAMPLES
   *
   *     $ wp civicrm debug enable
   *     Success: Debug setting enabled.
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function enable($args, $assoc_args) {

    // Bootstrap CiviCRM.
    $this->bootstrap_civicrm();

    Civi::settings()->add([
      'debug_enabled' => 1,
      'backtrace' => 1,
    ]);

    WP_CLI::success('Debug setting enabled.');

  }

  /**
   * Disable debugging in CiviCRM.
   *
   * ## EXAMPLES
   *
   *     $ wp civicrm debug disable
   *     Success: Debug setting disabled.
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function disable($args, $assoc_args) {

    // Bootstrap CiviCRM.
    $this->bootstrap_civicrm();

    Civi::settings()->add([
      'debug_enabled' => 0,
      'backtrace' => 0,
    ]);

    WP_CLI::success('Debug setting disabled.');

  }

}
