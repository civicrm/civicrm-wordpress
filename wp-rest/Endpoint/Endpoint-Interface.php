<?php
/**
 * Endpoint Interface class.
 *
 * @since 0.1
 */

namespace CiviCRM_WP_REST\Endpoint;

interface Endpoint_Interface {

  /**
   * Registers routes.
   *
   * @since 0.1
   */
  public function register_routes();

  /**
   * Item schema.
   *
   * @since 0.1
   * @return array $schema
   */
  public function get_item_schema();

  /**
   * Item arguments.
   *
   * @since 0.1
   * @return array $arguments
   */
  public function get_item_args();

}
