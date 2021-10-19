<?php
/**
 * Cxn controller class.
 *
 * CiviConnect endpoint, replacement for CiviCRM's 'extern/cxn.php'.
 *
 * @since 5.25
 */

namespace CiviCRM_WP_REST\Controller;

class Cxn extends Base {

  /**
   * @var string
   * The base route.
   * @since 5.25
   */
  protected $rest_base = 'cxn';

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
    $params = apply_filters('civi_wp_rest/controller/cxn/params', $request->get_params(), $request);

    // init connection server
    $cxn = \CRM_Cxn_BAO_Cxn::createApiServer();

    /**
     * Filter connection server object.
     *
     * @since 5.25
     *
     * @param Civi\Cxn\Rpc\ApiServer $cxn
     * @param array $params
     * @param WP_REST_Request $request
     */
    $cxn = apply_filters('civi_wp_rest/controller/cxn/instance', $cxn, $params, $request);

    try {
      $result = $cxn->handle($request->get_body());
    }
    catch (Civi\Cxn\Rpc\Exception\CxnException $e) {
      return $this->civi_rest_error($e->getMessage());
    }
    catch (Civi\Cxn\Rpc\Exception\ExpiredCertException $e) {
      return $this->civi_rest_error($e->getMessage());
    }
    catch (Civi\Cxn\Rpc\Exception\InvalidCertException $e) {
      return $this->civi_rest_error($e->getMessage());
    }
    catch (Civi\Cxn\Rpc\Exception\InvalidMessageException $e) {
      return $this->civi_rest_error($e->getMessage());
    }
    catch (Civi\Cxn\Rpc\Exception\GarbledMessageException $e) {
      return $this->civi_rest_error($e->getMessage());
    }

    // Bypass WordPress and send request from Cxn.
    add_filter('rest_pre_serve_request', function($served, $response, $request, $server) use ($result) {

      // Civi\Cxn\Rpc\Message->send()
      $result->send();

      return TRUE;

    }, 10, 4);

    return rest_ensure_response($result);

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
