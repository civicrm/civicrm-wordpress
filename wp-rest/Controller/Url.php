<?php
/**
 * Url controller class.
 *
 * @since 5.25
 */

namespace CiviCRM_WP_REST\Controller;

class Url extends Base {

  /**
   * @var string
   * The base route.
   * @since 5.25
   */
  protected $rest_base = 'url';

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
   * Get items.
   *
   * @since 5.25
   *
   * @param WP_REST_Request $request
   */
  public function get_item($request) {

    /**
     * Filter formatted API params.
     *
     * @since 5.25
     *
     * @param array $params
     * @param WP_REST_Request $request
     */
    $params = apply_filters('civi_wp_rest/controller/url/params', $this->get_formatted_params($request), $request);

    // Track URL.
    $url = \CRM_Mailing_Event_BAO_MailingEventTrackableURLOpen::track($params['queue_id'], $params['url_id']);

    /**
     * Filter URL.
     *
     * @since 5.25
     *
     * @param string $url
     * @param array $params
     * @param WP_REST_Request $request
     */
    $url = apply_filters('civi_wp_rest/controller/url/before_parse_url', $url, $params, $request);

    // parse url
    $url = $this->parse_url($url, $params);

    $this->do_redirect($url);

  }

  /**
   * Get formatted API params.
   *
   * @since 5.25
   *
   * @param WP_REST_Resquest $request
   * @return array $params
   */
  protected function get_formatted_params($request) {

    $args = $request->get_params();

    $params = [
      'queue_id' => isset($args['qid']) ? $args['qid'] ?? '' : $args['q'] ?? '',
      'url_id' => $args['u'],
    ];

    // unset unnecessary args
    unset($args['qid'], $args['u'], $args['q']);

    if (!empty($args)) {

      $params['query'] = http_build_query($args);

    }

    return $params;

  }

  /**
   * Parses the URL.
   *
   * @since 5.25
   *
   * @param string $url
   * @param array $params
   * @return string $url
   */
  protected function parse_url($url, $params) {

    // CRM-18320 - Fix encoded ampersands.
    $url = str_replace('&amp;', '&', $url);

    // CRM-7103 - Look for additional query variables and append them.
    if (isset($params['query']) && strpos($url, '?')) {
      $url .= '&' . $params['query'];
    }
    elseif (isset($params['query'])) {
      $url .= '?' . $params['query'];
    }

    if (strpos($url, 'mailto')) {
      $url = strstr($url, 'mailto');
    }

    return apply_filters('civi_wp_rest/controller/url/parsed_url', $url, $params);

  }

  /**
   * Do redirect.
   *
   * @since 5.25
   *
   * @param string $url
   */
  protected function do_redirect($url) {

    wp_redirect($url);

    exit;

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
      'title' => 'civicrm_api3/v3/url',
      'description' => __('CiviCRM API3 wrapper', 'civicrm'),
      'type' => 'object',
      'required' => ['qid', 'u'],
      'properties' => [
        'qid' => [
          'type' => 'integer',
        ],
        'q' => [
          'type' => 'integer',
        ],
        'u' => [
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
      'qid' => [
        'type' => ['integer', 'string'],
        'required' => FALSE,
        'validate_callback' => function($value, $request, $key) {
          return is_numeric($value) || empty($value);
        },
      ],
      'q' => [
        'type' => ['integer', 'string'],
        'required' => FALSE,
        'validate_callback' => function($value, $request, $key) {
          return is_numeric($value) || empty($value);
        },
      ],
      'u' => [
        'type' => 'integer',
        'required' => TRUE,
        'validate_callback' => function($value, $request, $key) {
          return is_numeric($value);
        },
      ],
    ];

  }

}
