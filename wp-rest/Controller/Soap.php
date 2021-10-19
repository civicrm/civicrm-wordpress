<?php
/**
 * Soap controller class.
 *
 * Soap endpoint, replacement for CiviCRM's 'extern/soap.php'.
 *
 * @since 5.25
 */

namespace CiviCRM_WP_REST\Controller;

class Soap extends Base {

  /**
   * @var string
   * The base route.
   * @since 5.25
   */
  protected $rest_base = 'soap';

  /**
   * Registers routes.
   *
   * @since 5.25
   */
  public function register_routes() {

    register_rest_route($this->get_namespace(), $this->get_rest_base(), [
      [
        'methods' => \WP_REST_Server::ALLMETHODS,
        'permission_callback' => '__return_true',
        'callback' => [$this, 'get_item'],
      ],
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
     * Filter request params.
     *
     * @since 5.25
     *
     * @param array $params
     * @param WP_REST_Request $request
     */
    $params = apply_filters('civi_wp_rest/controller/soap/params', $request->get_params(), $request);

    // Init soap server.
    $soap_server = new \SoapServer(
      NULL,
      [
        'uri' => 'urn:civicrm',
        'soap_version' => SOAP_1_2,
      ]
    );

    $crm_soap_server = new \CRM_Utils_SoapServer();

    $soap_server->setClass('CRM_Utils_SoapServer', \CRM_Core_Config::singleton()->userFrameworkClass);
    $soap_server->setPersistence(SOAP_PERSISTENCE_SESSION);

    // Bypass WordPress and send request from Soap server.
    add_filter('rest_pre_serve_request', function($served, $response, $request, $server) use ($soap_server) {

      $soap_server->handle();

      return TRUE;

    }, 10, 4);

  }

  /**
   * Item schema.
   *
   * @since 5.25
   *
   * @return array $schema
   */
  public function get_item_schema() {}

  /**
   * Item arguments.
   *
   * @since 5.25
   *
   * @return array $arguments
   */
  public function get_item_args() {}

}
