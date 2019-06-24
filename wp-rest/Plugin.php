<?php
/**
 * Main plugin class.
 *
 * @since 0.1
 */

namespace CiviCRM_WP_REST;

use CiviCRM_WP_REST\Civi\Mailing_Hooks;

class Plugin {

	/**
	 * Constructor.
	 *
	 * @since 0.1
	 */
	public function __construct() {

		$this->register_hooks();

		$this->setup_objects();

	}

	/**
	 * Register hooks.
	 *
	 * @since 1.0
	 */
	protected function register_hooks() {

		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		add_filter( 'rest_pre_dispatch', [ $this, 'bootstrap_civi' ], 10, 3 );

	}

	/**
	 * Bootstrap CiviCRM when hitting a the 'civicrm' namespace.
	 *
	 * @since 0.1
	 * @param mixed $result
	 * @param WP_REST_Server $server REST server instance
	 * @param WP_REST_Request $request The request
	 * @return mixed $result
	 */
	public function bootstrap_civi( $result, $server, $request ) {

		if ( false !== strpos( $request->get_route(), 'civicrm' ) ) civi_wp()->initialize();

		return $result;

	}

	/**
	 * Setup objects.
	 *
	 * @since 0.1
	 */
	private function setup_objects() {

		if ( CIVICRM_WP_REST_REPLACE_MAILING_TRACKING ) {

			// register mailing hooks
			$mailing_hooks = ( new Mailing_Hooks )->register_hooks();

		}

	}

	/**
	 * Registers Rest API routes.
	 *
	 * @since 0.1
	 */
	public function register_rest_routes() {

		// rest endpoint
		$rest_controller = new Controller\Rest;
		$rest_controller->register_routes();

		// url controller
		$url_controller = new Controller\Url;
		$url_controller->register_routes();

		// open controller
		$open_controller = new Controller\Open;
		$open_controller->register_routes();

		// authorizenet controller
		$authorizeIPN_controller = new Controller\AuthorizeIPN;
		$authorizeIPN_controller->register_routes();

		// paypal controller
		$paypalIPN_controller = new Controller\PayPalIPN;
		$paypalIPN_controller->register_routes();

		// pxpay controller
		$paypalIPN_controller = new Controller\PxIPN;
		$paypalIPN_controller->register_routes();

		// civiconnect controller
		$cxn_controller = new Controller\Cxn;
		$cxn_controller->register_routes();

		// widget controller
		$widget_controller = new Controller\Widget;
		$widget_controller->register_routes();

		// soap controller
		$soap_controller = new Controller\Soap;
		$soap_controller->register_routes();

		/**
		 * Opportunity to add more rest routes.
		 *
		 * @since 0.1
		 */
		do_action( 'civi_wp_rest/plugin/rest_routes_registered' );

	}

}
