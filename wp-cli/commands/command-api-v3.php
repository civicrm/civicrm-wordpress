<?php
/**
 * Access the CiviCRM API v3.
 *
 * ## EXAMPLES
 *
 *     $ wp civicrm api contact.get id=10
 *     Output here.
 *
 * @since 5.69
 */
class CLI_Tools_CiviCRM_Command_API_V3 extends CLI_Tools_CiviCRM_Command {

  /**
   * Object fields.
   *
   * @var array
   */
  protected $obj_fields = [
    'id',
  ];

  /**
   * Access the CiviCRM API v3.
   *
   * ## OPTIONS
   *
   * <args>...
   * : The API query passed as arguments.
   *
   * [--input=<input>]
   * : Specify the input in a particular format.
   * ---
   * default: args
   * options:
   *   - args
   *   - json
   * ---
   *
   * [--timezone=<timezone>]
   * : The CiviCRM timezone string. Defaults to the WordPress `timezone_string` setting.
   *
   * [--format=<format>]
   * : Render output in a particular format. The "table" format can only be used when retrieving a single item.
   * ---
   * default: pretty
   * options:
   *   - pretty
   *   - json
   *   - table
   * ---
   *
   * ## EXAMPLES
   *
   *     $ wp civicrm api contact.get id=10
   *     $ wp civicrm api contact.get id=10 --format=json
   *     $ wp civicrm api group.get id=1 --format=table
   *     $ echo '{"id":10, "api.Email.get": 1}' | wp cv api contact.get --input=json
   *
   * @since 5.69
   *
   * @param array $args The WP-CLI positional arguments.
   * @param array $assoc_args The WP-CLI associative arguments.
   */
  public function __invoke($args, $assoc_args) {

    // Grab associative arguments.
    $input_format = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'input', 'args');
    $site_timezone = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'timezone', $this->site_timezone_get());
    $format = (string) \WP_CLI\Utils\get_flag_value($assoc_args, 'format', 'pretty');

    // Get the Entity and Action from the first positional argument.
    list($entity, $action) = explode('.', $args[0]);
    array_shift($args);

    // Parse params.
    $defaults = ['version' => 3];
    switch ($input_format) {

      // Input params supplied via args.
      case 'args':
        $params = $defaults;
        foreach ($args as $arg) {
          preg_match('/^([^=]+)=(.*)$/', $arg, $matches);
          $params[$matches[1]] = $matches[2];
        }
        break;

      // Input params supplied via json.
      case 'json':
        $json = stream_get_contents(STDIN);
        if (empty($json)) {
          $params = $defaults;
        }
        else {
          $params = array_merge($defaults, json_decode($json, TRUE));
        }
        break;

      default:
        WP_CLI::error(sprintf('Unknown format: %s', $input_format));
        break;

    }

    // Bootstrap CiviCRM.
    $this->bootstrap_civicrm();

    // CRM-18062: Configure timezone for CiviCRM.
    $current_timezone = date_default_timezone_get();
    if ($site_timezone) {
      date_default_timezone_set($site_timezone);
      CRM_Core_Config::singleton()->userSystem->setMySQLTimeZone();
    }

    // Now call the CiviCRM API.
    $result = civicrm_api($entity, $action, $params);

    // Restore timezone.
    if ($current_timezone) {
      date_default_timezone_set($current_timezone);
    }

    switch ($format) {

      // Pretty-print output (default).
      case 'pretty':
        WP_CLI::log(print_r($result, TRUE));
        break;

      // Display output as json.
      case 'json':
        WP_CLI::log(json_encode($result));
        break;

      // Display output as table.
      case 'table':
        $assoc_args['format'] = $format;
        if (count($result['values']) === 1) {
          $item = array_pop($result['values']);
          $assoc_args['fields'] = array_keys($item);
          $formatter = $this->formatter_get($assoc_args);
          $formatter->display_item($item);
        }
        else {

          // Give up and log usual output.
          WP_CLI::log(print_r($result, TRUE));

          // phpcs:disable

          /*
          // Testing whether we can do this. It's hard, but kinda works.
          $fields_query = civicrm_api3($entity, 'getfields', [
            'api_action' => $action,
          ]);;
          $fields = array_keys($fields_query['values']);
          //WP_CLI::log(print_r($fields, TRUE));

          //WP_CLI::log(print_r($result['values'], TRUE));
          $assoc_args['fields'] = $fields;
          //WP_CLI::log(print_r($assoc_args['fields'], TRUE));

          // Cast items as objects.
          array_walk($result['values'], function( &$item ) use ( $fields ) {
            foreach ($fields as $field) {
              // Make sure the array has all keys.
              if (!array_key_exists($field, $item)) {
                $item[$field] = '';
              }
            }
            $item = (object) $item;
          });
          //WP_CLI::log(print_r($result['values'], TRUE));

          $formatter = $this->formatter_get($assoc_args);
          //WP_CLI::log(print_r($formatter, TRUE));

          $formatter->display_items($result['values']);
          */

          // phpcs:enable

        }
        break;

      default:
        WP_CLI::error(sprintf('Unknown format: %s', $format));

    }

  }

}
