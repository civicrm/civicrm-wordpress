<?php
/**
 * Widget controller class.
 *
 * Widget endpoint, replacement for CiviCRM's 'extern/widget.php'
 *
 * @since 5.25
 */

namespace CiviCRM_WP_REST\Controller;

class Widget extends Base {

  /**
   * @var string
   * The base route.
   * @since 5.25
   */
  protected $rest_base = 'widget';

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

    /**
     * Filter mandatory params.
     *
     * @since 5.25
     *
     * @param array $params
     * @param WP_REST_Request $request
     */
    $params = apply_filters(
      'civi_wp_rest/controller/widget/params',
      $this->get_mandatory_params($request),
      $request
    );

    $jsonvar = 'jsondata';

    if (!empty($request->get_param('format'))) {
      $jsonvar .= $request->get_param('cpageId');
    }

    $data = \CRM_Contribute_BAO_Widget::getContributionPageData(...$params);

    $response = 'var ' . $jsonvar . ' = ' . json_encode($data) . ';';

    /**
     * Adds our response data before dispatching.
     *
     * @since 5.25
     *
     * @param WP_HTTP_Response $result Result to send to client
     * @param WP_REST_Server $server The REST server
     * @param WP_REST_Request $request The request
     * @return WP_HTTP_Response $result Result to send to client
     */
    add_filter('rest_post_dispatch', function($result, $server, $request) use ($response) {

      return rest_ensure_response($response);

    }, 10, 3);

    // serve javascript
    add_filter('rest_pre_serve_request', [$this, 'serve_javascript'], 10, 4);

  }

  /**
   * Get mandatory params from request.
   *
   * @since 5.25
   *
   * @param WP_REST_Resquest $request
   * @return array $params The widget params
   */
  protected function get_mandatory_params($request) {

    $args = $request->get_params();

    return [
      $args['cpageId'],
      $args['widgetId'],
      $args['includePending'] ?? FALSE,
    ];

  }

  /**
   * Serve jsondata response.
   *
   * @since 5.25
   *
   * @param bool $served Whether the request has already been served
   * @param WP_REST_Response $result
   * @param WP_REST_Request $request
   * @param WP_REST_Server $server
   * @return bool $served
   */
  public function serve_javascript($served, $result, $request, $server) {

    // set content type header
    $server->send_header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + 60));
    $server->send_header('Content-Type', 'application/javascript');
    $server->send_header('Cache-Control', 'max-age=60, public');

    echo $result->get_data();

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
      'title' => 'civicrm_api3/v3/widget',
      'description' => __('CiviCRM API3 wrapper', 'civicrm'),
      'type' => 'object',
      'required' => ['cpageId', 'widgetId'],
      'properties' => [
        'cpageId' => [
          'type' => 'integer',
          'minimum' => 1,
        ],
        'widgetId' => [
          'type' => 'integer',
          'minimum' => 1,
        ],
        'format' => [
          'type' => 'integer',
        ],
        'includePending' => [
          'type' => 'boolean',
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
      'cpageId' => [
        'type' => 'integer',
        'required' => TRUE,
        'validate_callback' => function($value, $request, $key) {
          return is_numeric($value);
        },
      ],
      'widgetId' => [
        'type' => 'integer',
        'required' => TRUE,
        'validate_callback' => function($value, $request, $key) {
          return is_numeric($value);
        },
      ],
      'format' => [
        'type' => 'integer',
        'required' => FALSE,
        'validate_callback' => function($value, $request, $key) {
          return is_numeric($value);
        },
      ],
      'includePending' => [
        'type' => 'boolean',
        'required' => FALSE,
        'validate_callback' => function($value, $request, $key) {
          return is_string($value);
        },
      ],
    ];

  }

}
