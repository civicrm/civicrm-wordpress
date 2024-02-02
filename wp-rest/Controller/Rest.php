<?php
/**
 * Rest controller class.
 *
 * @since 5.25
 */

namespace CiviCRM_WP_REST\Controller;

class Rest extends Base {

  /**
   * @var string
   * The base route.
   * @since 5.25
   */
  protected $rest_base = 'rest';

  /**
   * Registers routes.
   *
   * @since 5.25
   */
  public function register_routes() {

    register_rest_route($this->get_namespace(), $this->get_rest_base(), [
      [
        'methods' => \WP_REST_Server::ALLMETHODS,
        'callback' => [$this, 'get_items'],
        'permission_callback' => [$this, 'permissions_check'],
        'args' => $this->get_item_args(),
      ],
      'schema' => [$this, 'get_item_schema'],
    ]);

  }

  /**
   * Check get permission.
   *
   * @since 5.25
   *
   * @param WP_REST_Request $request
   * @return bool
   */
  public function permissions_check($request) {

    /**
     * Opportunity to bypass CiviCRM's authentication ('api_key' and 'site_key').
     *
     * Return 'true' or 'false' to grant or deny access to this endpoint.
     *
     * To deny and throw an error, return either a string, an array, or a \WP_Error.
     *
     * NOTE: if you use your own authentication, you still must log in the user
     * in order to respect/apply CiviCRM ACLs.
     *
     * @since 5.25
     *
     * @param null|bool|string|array|\WP_Error $grant_auth Grant, deny, or error
     * @param \WP_REST_Request $request The request
     */
    $grant_auth = apply_filters('civi_wp_rest/controller/rest/permissions_check', NULL, $request);

    if (is_bool($grant_auth)) {

      return $grant_auth;

    }

    elseif (is_string($grant_auth)) {

      return $this->civi_rest_error($grant_auth);

    }

    elseif (is_array($grant_auth)) {

      return $this->civi_rest_error(__('CiviCRM WP REST permission check error.', 'civicrm'), $grant_auth);

    }

    elseif ($grant_auth instanceof \WP_Error) {

      return $grant_auth;

    }

    else {

      if (!$this->is_valid_api_key($request)) {
        return $this->civi_rest_error(__('Param api_key is not valid.', 'civicrm'));
      }

      if (!$this->is_valid_site_key()) {
        return $this->civi_rest_error(__('Param key is not valid.', 'civicrm'));
      }

      return TRUE;

    }

  }

  /**
   * Get items.
   *
   * @since 5.25
   *
   * @param WP_REST_Request $request
   */
  public function get_items($request) {

    /**
     * Filter formatted API params.
     *
     * @since 5.25
     *
     * @param array $params
     * @param WP_REST_Request $request
     */
    $params = apply_filters('civi_wp_rest/controller/rest/api_params', $this->get_formatted_api_params($request), $request);

    try {
      $items = civicrm_api3(...$params);
    }
    catch (\CRM_Core_Exception $e) {
      $items = $this->civi_rest_error($e);
    }

    if (!isset($items) || empty($items)) {
      return rest_ensure_response([]);
    }

    /**
     * Filter CiviCRM API result.
     *
     * @since 5.25
     *
     * @param array $items
     * @param WP_REST_Request $request
     */
    $data = apply_filters('civi_wp_rest/controller/rest/api_result', $items, $params, $request);

    // Only collections of items, ie any action but 'getsingle'.
    if (isset($data['values'])) {

      $data['values'] = array_reduce($items['values'] ?? $items, function($items, $item) use ($request) {

        $response = $this->prepare_item_for_response($item, $request);

        $items[] = $this->prepare_response_for_collection($response);

        return $items;

      }, []);

    }

    $response = rest_ensure_response($data);

    // Check whether we need to serve xml or json.
    if (!in_array('json', array_keys($request->get_params()))) {

      /**
       * Adds our response holding CiviCRM data before dispatching.
       *
       * @since 5.25
       *
       * @param WP_HTTP_Response $result Result to send to client
       * @param WP_REST_Server $server The REST server
       * @param WP_REST_Request $request The request
       * @return WP_HTTP_Response $result Result to send to client
       */
      add_filter('rest_post_dispatch', function($result, $server, $request) use ($response) {

        return $response;

      }, 10, 3);

      // serve xml
      add_filter('rest_pre_serve_request', [$this, 'serve_xml_response'], 10, 4);

    }

    else {

      // return json
      return $response;

    }

  }

  /**
   * Get formatted API params.
   *
   * @since 5.25
   *
   * @param WP_REST_Resquest $request
   * @return array $params
   */
  public function get_formatted_api_params($request) {

    $args = $request->get_params();

    $entity = $args['entity'];
    $action = $args['action'];

    // unset unnecessary args
    unset($args['entity'], $args['action'], $args['key'], $args['api_key']);

    if (!isset($args['json']) || is_numeric($args['json'])) {

      $params = $args;

    }

    else {

      $params = is_string($args['json']) ? json_decode($args['json'], TRUE) : [];

    }

    // Ensure check permissions is enabled.
    $params['check_permissions'] = TRUE;

    return [$entity, $action, $params];

  }

  /**
   * Matches the item data to the schema.
   *
   * @since 5.25
   *
   * @param object $item
   * @param WP_REST_Request $request
   */
  public function prepare_item_for_response($item, $request) {

    return rest_ensure_response($item);

  }

  /**
   * Serves XML response.
   *
   * @since 5.25
   *
   * @param bool $served Whether the request has already been served
   * @param WP_REST_Response $result
   * @param WP_REST_Request $request
   * @param WP_REST_Server $server
   */
  public function serve_xml_response($served, $result, $request, $server) {

    // Get XML from response.
    $xml = $this->get_xml_formatted_data($result->get_data());

    // Set content type header.
    $server->send_header('Content-Type', 'text/xml');

    echo $xml;

    return TRUE;

  }

  /**
   * Formats CiviCRM API result to XML.
   *
   * @since 5.25
   *
   * @param array $data The CiviCRM API result.
   * @return string $xml The formatted XML.
   */
  protected function get_xml_formatted_data(array $data) {

    // XML document.
    $xml = new \DOMDocument();

    // Result set element <ResultSet>.
    $result_set = $xml->createElement('ResultSet');

    // The xmlns:xsi attribute.
    $result_set->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');

    // Count attributes.
    if (isset($data['count'])) {
      $result_set->setAttribute('count', $data['count']);
    }

    // Build result from result => values.
    if (isset($data['values'])) {

      array_map(function($item) use ($result_set, $xml) {

        // Result element <Result>.
        $result = $xml->createElement('Result');

        // Format item.
        $result = $this->get_xml_formatted_item($item, $result, $xml);

        // Append result to result set.
        $result_set->appendChild($result);

      }, $data['values']);

    }
    else {

      // Result element <Result>.
      $result = $xml->createElement('Result');

      // Format item.
      $result = $this->get_xml_formatted_item($data, $result, $xml);

      // Append result to result set.
      $result_set->appendChild($result);

    }

    // Append result set.
    $xml->appendChild($result_set);

    return $xml->saveXML();

  }

  /**
   * Formats a single API result to XML.
   *
   * @since 5.25
   *
   * @param array $item The single API result.
   * @param \DOMElement $parent The parent element to append to.
   * @param \DOMDocument $doc The document.
   * @return \DOMElement $parent The parent element.
   */
  public function get_xml_formatted_item(array $item, \DOMElement $parent, \DOMDocument $doc) {

    // Build field => values.
    array_map(function($field, $value) use ($parent, $doc) {

      // Entity field element.
      $element = $doc->createElement($field);

      // Handle array values.
      if (is_array($value)) {

        array_map(function($key, $val) use ($element, $doc) {

          /*
           * Child element - append underscore '_' otherwise createElement will
           * throw an Invalid character exception as elements cannot start with
           * a number.
           */
          $child = $doc->createElement('_' . $key, $val);

          // Append child.
          $element->appendChild($child);

        }, array_keys($value), $value);

      }
      else {

        // Assign value.
        $element->nodeValue = $value;

      }

      // Append element.
      $parent->appendChild($element);

    }, array_keys($item), $item);

    return $parent;

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
      'title' => 'civicrm/v3/rest',
      'description' => __('CiviCRM API3 WP rest endpoint wrapper', 'civicrm'),
      'type' => 'object',
      'required' => ['entity', 'action', 'params'],
      'properties' => [
        'is_error' => [
          'type' => 'integer',
        ],
        'version' => [
          'type' => 'integer',
        ],
        'count' => [
          'type' => 'integer',
        ],
        'values' => [
          'type' => 'array',
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
      'key' => [
        'type' => 'string',
        'required' => FALSE,
        'validate_callback' => function($value, $request, $key) {
          return $this->is_valid_site_key();
        },
      ],
      'api_key' => [
        'type' => 'string',
        'required' => FALSE,
        'validate_callback' => function($value, $request, $key) {
          return $this->is_valid_api_key($request);
        },
      ],
      'entity' => [
        'type' => 'string',
        'required' => TRUE,
        'validate_callback' => function($value, $request, $key) {
          return is_string($value);
        },
      ],
      'action' => [
        'type' => 'string',
        'required' => TRUE,
        'validate_callback' => function($value, $request, $key) {
          return is_string($value);
        },
      ],
      'json' => [
        'type' => ['integer', 'string', 'array'],
        'required' => FALSE,
        'validate_callback' => function($value, $request, $key) {
          return is_numeric($value) || is_array($value) || $this->is_valid_json($value);
        },
      ],
    ];

  }

  /**
   * Checks if string is a valid json.
   *
   * @since 5.25
   *
   * @param string $param
   * @return bool
   */
  public function is_valid_json($param) {

    $param = json_decode($param, TRUE);

    if (!is_array($param)) {
      return FALSE;
    }

    return (json_last_error() == JSON_ERROR_NONE);

  }

  /**
   * Validates the site key.
   *
   * @since 5.25
   *
   * @return bool $is_valid_site_key
   */
  public function is_valid_site_key() {

    return \CRM_Utils_System::authenticateKey(FALSE);

  }

  /**
   * Validates the api key.
   *
   * @since 5.25
   *
   * @param WP_REST_Resquest $request
   * @return bool $is_valid_api_key
   */
  public function is_valid_api_key($request) {

    $api_key = $request->get_param('api_key');

    if (!$api_key) {
      return FALSE;
    }

    $contact_id = \CRM_Core_DAO::getFieldValue('CRM_Contact_DAO_Contact', $api_key, 'id', 'api_key');

    if (!$contact_id) {
      return FALSE;
    }

    return TRUE;

  }

}
