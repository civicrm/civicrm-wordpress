<?php
/**
 * PxIPN controller class.
 *
 * PxPay IPN endpoint, replacement for CiviCRM's 'extern/pxIPN.php'.
 *
 * @since 5.25
 */

namespace CiviCRM_WP_REST\Controller;

class PxIPN extends Base {

  /**
   * @var string
   * The base route.
   * @since 5.25
   */
  protected $rest_base = 'pxIPN';

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
     * Filter payment processor params.
     *
     * @since 5.25
     *
     * @param array $params
     * @param WP_REST_Request $request
     */
    $params = apply_filters(
      'civi_wp_rest/controller/pxIPN/params',
      $this->get_payment_processor_args($request),
      $request
    );

    // Log notification.
    \Civi::log()->alert('payment_notification processor_name=Payment_Express', $params);

    try {

      $result = \CRM_Core_Payment_PaymentExpressIPN::main(...$params);

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
   * Get payment processor necessary params.
   *
   * @since 5.25
   *
   * @param WP_REST_Resquest $request
   * @return array $args
   */
  public function get_payment_processor_args($request) {

    // Get payment processor types.
    $payment_processor_types = civicrm_api3('PaymentProcessor', 'getoptions', [
      'field' => 'payment_processor_type_id',
    ]);

    // Payment processor params.
    $params = apply_filters('civi_wp_rest/controller/pxIPN/payment_processor_params', [
      'user_name' => $request->get_param('userid'),
      'payment_processor_type_id' => array_search(
        'DPS Payment Express',
        $payment_processor_types['values']
      ),
      'is_active' => 1,
      'is_test' => 0,
    ]);

    // Get payment processor.
    $payment_processor = civicrm_api3('PaymentProcessor', 'get', $params);

    $args = $payment_processor['values'][$payment_processor['id']];

    $method = empty($args['signature']) ? 'pxpay' : 'pxaccess';

    return [
      $method,
      $request->get_param('result'),
      $args['url_site'],
      $args['user_name'],
      $args['password'],
      $args['signature'],
    ];

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
