<?php
/**
 * Open controller class.
 *
 * @since 5.25
 */

namespace CiviCRM_WP_REST\Controller;

class Open extends Base {

  /**
   * @var string
   * The base route.
   * @since 5.25
   */
  protected $rest_base = 'open';

  /**
   * Registers routes.
   *
   * @since 5.25
   */
  public function register_routes() {

    register_rest_route($this->get_namespace(), $this->get_rest_base(), [
      [
        'methods' => \WP_REST_Server::READABLE,
        'callback' => [$this, 'get_item'],
        'permission_callback' => '__return_true',
        'args' => $this->get_item_args(),
      ],
      'schema' => [$this, 'get_item_schema'],
    ]);

  }

  /**
   * Get item.
   *
   * @since 5.25
   *
   * @param WP_REST_Request $request
   */
  public function get_item($request) {

    $queue_id = $request->get_param('q');

    // Track open.
    \CRM_Mailing_Event_BAO_MailingEventOpened::open($queue_id);

    // Serve tracker file.
    add_filter('rest_pre_serve_request', [$this, 'serve_tracker_file'], 10, 4);

  }

  /**
   * Serves the tracker gif file.
   *
   * @since 5.25
   *
   * @param bool $served Whether the request has been served.
   * @param WP_REST_Response $result
   * @param WP_REST_Request $request
   * @param WP_REST_Server $server
   * @return bool $served Whether the request has been served.
   */
  public function serve_tracker_file($served, $result, $request, $server) {

    // Tracker file path.
    $file = CIVICRM_PLUGIN_DIR . 'civicrm/i/tracker.gif';

    // Set headers.
    $server->send_header('Content-type', 'image/gif');
    $server->send_header('Cache-Control', 'must-revalidate, post-check=0, pre-check=0');
    $server->send_header('Content-Description', 'File Transfer');
    $server->send_header('Content-Disposition', 'inline; filename=tracker.gif');
    $server->send_header('Content-Length', filesize($file));

    $buffer = readfile($file);

    return TRUE;

  }

  /**
   * Item schema.
   *
   * @since 5.25
   *
   * @return array $schema
   */
  public function get_item_schema() {

    return [
      '$schema' => 'http://json-schema.org/draft-04/schema#',
      'title' => 'civicrm/v3/open',
      'description' => __('CiviCRM Open endpoint', 'civicrm'),
      'type' => 'object',
      'required' => ['q'],
      'properties' => [
        'q' => [
          'type' => 'integer',
        ],
      ],
    ];

  }

  /**
   * Item arguments.
   *
   * @since 5.25
   *
   * @return array $arguments
   */
  public function get_item_args() {

    return [
      'q' => [
        'type' => 'integer',
        'required' => TRUE,
        'validate_callback' => function($value, $request, $key) {
          return is_numeric($value);
        },
      ],
    ];

  }

}
