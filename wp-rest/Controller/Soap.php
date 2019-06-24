<?php
/**
 * Soap controller class.
 *
 * Soap endpoint, replacement for CiviCRM's 'extern/soap.php'.
 *
 * @since 0.1
 */

namespace CiviCRM_WP_REST\Controller;

class Soap extends Base {

	/**
	 * The base route.
	 *
	 * @since 0.1
	 * @var string
	 */
	protected $rest_base = 'soap';

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
		$params = apply_filters( 'civi_wp_rest/controller/soap/params', $request->get_params(), $request );

		// init soap server
		$soap_server = new \SoapServer(
			NULL,
			[
				'uri' => 'urn:civicrm',
				'soap_version' => SOAP_1_2,
			]
		);

		$crm_soap_server = new \CRM_Utils_SoapServer();

		$soap_server->setClass( 'CRM_Utils_SoapServer', \CRM_Core_Config::singleton()->userFrameworkClass );
		$soap_server->setPersistence( SOAP_PERSISTENCE_SESSION );

		/**
		 * Bypass WP and send request from Soap server.
		 */
		add_filter( 'rest_pre_serve_request', function( $served, $response, $request, $server ) use ( $soap_server ) {

			$soap_server->handle();

			return true;

		}, 10, 4 );

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
