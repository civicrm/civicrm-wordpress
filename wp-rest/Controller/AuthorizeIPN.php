<?php
/**
 * AuthorizeIPN controller class.
 *
 * Replacement for CiviCRM's 'extern/authorizeIPN.php'.
 *
 * @see https://docs.civicrm.org/sysadmin/en/latest/setup/payment-processors/authorize-net/#shell-script-testing-method
 *
 * @since 5.25
 */

namespace CiviCRM_WP_REST\Controller;

class AuthorizeIPN extends Base {

  /**
   * @var string
   * The base route.
   * @since 5.25
   */
  protected $rest_base = 'authorizeIPN';

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
    $params = apply_filters('civi_wp_rest/controller/authorizeIPN/params', $request->get_params(), $request);

    $authorize_IPN = new \CRM_Core_Payment_AuthorizeNetIPN($params);

    // log notification
    \Civi::log()->alert('payment_notification processor_name=AuthNet', $params);

    /**
     * Filter AuthorizeIPN object.
     *
     * @since 5.25
     *
     * @param CRM_Core_Payment_AuthorizeNetIPN $authorize_IPN
     * @param array $params
     * @param WP_REST_Request $request
     */
    $authorize_IPN = apply_filters('civi_wp_rest/controller/authorizeIPN/instance', $authorize_IPN, $params, $request);

    try {

      if (!method_exists($authorize_IPN, 'main') || !$this->instance_of_crm_base_ipn($authorize_IPN)) {
        return $this->civi_rest_error(sprintf(__('%s must implement a "main" method.', 'civicrm'), get_class($authorize_IPN)));
      }

      $result = $authorize_IPN->main();

    }

    catch (\CRM_Core_Exception $e) {

      \Civi::log()->error($e->getMessage());
      \Civi::log()->error('error data ', ['data' => $e->getErrorData()]);
      \Civi::log()->error('REQUEST ', ['params' => $params]);

      return $this->civi_rest_error($e->getMessage());

    }

    return rest_ensure_response($result);

  }

  /**
   * Checks whether object is an instance of CRM_Core_Payment_AuthorizeNetIPN or CRM_Core_Payment_BaseIPN.
   *
   * Needed because the instance is being filtered through 'civi_wp_rest/controller/authorizeIPN/instance'.
   *
   * @since 5.25
   *
   * @param CRM_Core_Payment_AuthorizeNetIPN|CRM_Core_Payment_BaseIPN $object
   * @return bool
   */
  public function instance_of_crm_base_ipn($object) {

    return $object instanceof \CRM_Core_Payment_BaseIPN || $object instanceof \CRM_Core_Payment_AuthorizeNetIPN;

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
