<?php
/**
 * PayPalIPN controller class.
 *
 * PayPal IPN endpoint, replacement for CiviCRM's 'extern/ipn.php'.
 *
 * @since 0.1
 */

namespace CiviCRM_WP_REST\Controller;

class PayPalIPN extends Base {

	/**
	 * The base route.
	 *
	 * @since 0.1
	 * @var string
	 */
	protected $rest_base = 'ipn';

	/**
	 * Registers routes.
	 *
	 * @since 0.1
	 */
	public function register_routes() {

		register_rest_route( $this->get_namespace(), $this->get_rest_base(), [
			[
				'methods' => \WP_REST_Server::ALLMETHODS,
				'callback' => [ $this, 'get_item' ]
			]
		] );

	}

	/**
	 * Get items.
	 *
	 * @since 0.1
	 * @param WP_REST_Request $request
	 */
	public function get_item( $request ) {

		/**
		 * Filter request params.
		 *
		 * @since 0.1
		 * @param array $params
		 * @param WP_REST_Request $request
		 */
		$params = apply_filters( 'civi_wp_rest/controller/ipn/params', $request->get_params(), $request );

		if ( $request->get_method() == 'GET' ) {

			// paypal standard
			$paypal_IPN = new \CRM_Core_Payment_PayPalIPN( $params );

			// log notification
			\Civi::log()->alert( 'payment_notification processor_name=PayPal_Standard', $params );

		} else {

			// paypal pro
			$paypal_IPN = new \CRM_Core_Payment_PayPalProIPN( $params );

			// log notification
			\Civi::log()->alert( 'payment_notification processor_name=PayPal', $params );

		}

		/**
		 * Filter PayPalIPN object.
		 *
		 * @param CRM_Core_Payment_PayPalIPN|CRM_Core_Payment_PayPalProIPN $paypal_IPN
		 * @param array $params
		 * @param WP_REST_Request $request
		 */
		$paypal_IPN = apply_filters( 'civi_wp_rest/controller/ipn/instance', $paypal_IPN, $params, $request );

		try {

			if ( ! method_exists( $paypal_IPN, 'main' ) || ! $this->instance_of_crm_base_ipn( $paypal_IPN ) )
				return $this->civi_rest_error( get_class( $paypal_IPN ) . ' must implement a "main" method.' );

			$result = $paypal_IPN->main();

		} catch ( \CRM_Core_Exception $e ) {

			\Civi::log()->error( $e->getMessage() );
			\Civi::log()->error( 'error data ', [ 'data' => $e->getErrorData() ] );
			\Civi::log()->error( 'REQUEST ', [ 'params' => $params ] );

			return $this->civi_rest_error( $e->getMessage() );

		}

		return rest_ensure_response( $result );

	}

	/**
	 * Checks whether object is an instance of CRM_Core_Payment_BaseIPN|CRM_Core_Payment_PayPalProIPN|CRM_Core_Payment_PayPalIPN.
	 *
	 * Needed because the instance is being filtered through 'civi_wp_rest/controller/ipn/instance'.
	 *
	 * @since 0.1
	 * @param CRM_Core_Payment_BaseIPN|CRM_Core_Payment_PayPalProIPN|CRM_Core_Payment_PayPalIPN $object
	 * @return bool
	 */
	public function instance_of_crm_base_ipn( $object ) {

		return $object instanceof \CRM_Core_Payment_BaseIPN || $object instanceof \CRM_Core_Payment_PayPalProIPN || $object instanceof \CRM_Core_Payment_PayPalIPN;

	}

	/**
	 * Item schema.
	 *
	 * @since 0.1
	 * @return array $schema
	 */
	public function get_item_schema() {}

	/**
	 * Item arguments.
	 *
	 * @since 0.1
	 * @return array $arguments
	 */
	public function get_item_args() {}

}
