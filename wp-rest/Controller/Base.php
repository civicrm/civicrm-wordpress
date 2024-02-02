<?php
/**
 * Base controller class.
 *
 * @since 5.25
 */

namespace CiviCRM_WP_REST\Controller;

use CiviCRM_WP_REST\Endpoint\Endpoint_Interface;

abstract class Base extends \WP_REST_Controller implements Endpoint_Interface {

  /**
   * @var string
   * Route namespace.
   * @since 5.25
   */
  protected $namespace = 'civicrm/v3';

  /**
   * Gets the endpoint namespace.
   *
   * @since 5.25
   *
   * @return string $namespace
   */
  public function get_namespace() {

    return $this->namespace;

  }

  /**
   * Gets the rest base route.
   *
   * @since 5.25
   *
   * @return string $rest_base
   */
  public function get_rest_base() {

    return '/' . $this->rest_base;

  }

  /**
   * Retrieves the endpoint ie. '/civicrm/v3/rest'.
   *
   * @since 5.25
   *
   * @return string $rest_base
   */
  public function get_endpoint() {

    return '/' . $this->get_namespace() . $this->get_rest_base();

  }

  /**
   * Checks whether the requested route is equal to this endpoint.
   *
   * @since 5.25
   *
   * @param WP_REST_Request $request
   * @return bool $is_current_endpoint True if it's equal, false otherwise
   */
  public function is_current_endpoint($request) {

    return $this->get_endpoint() == $request->get_route();

  }

  /**
   * Authorization status code.
   *
   * @since 5.25
   *
   * @return int $status
   */
  protected function authorization_status_code() {

    $status = 401;

    if (is_user_logged_in()) {
      $status = 403;
    }

    return $status;

  }

  /**
   * Wrapper for WP_Error.
   *
   * @since 5.25
   *
   * @param string|\CRM_Core_Exception|\WP_Error $error
   * @param mixed $data Error data
   * @return WP_Error $error
   */
  protected function civi_rest_error($error, $data = []) {

    if ($error instanceof \CRM_Core_Exception) {

      return $error->getExtraParams();

    }

    elseif ($error instanceof \WP_Error) {

      return $error;

    }

    return new \WP_Error('civicrm_rest_api_error', $error, empty($data) ? ['status' => $this->authorization_status_code()] : $data);

  }

}
