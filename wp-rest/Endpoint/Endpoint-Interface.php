<?php
/**
 * Endpoint Interface class.
 *
 * @since 5.25
 */

namespace CiviCRM_WP_REST\Endpoint;

interface Endpoint_Interface {

  /**
   * Registers routes.
   *
   * @since 5.25
   */
  public function register_routes();

  /**
   * Item schema.
   *
   * @since 5.25
   *
   * @return array $schema
   */
  public function get_item_schema();

  /**
   * Item arguments.
   *
   * @since 5.25
   *
   * @return array $arguments
   */
  public function get_item_args();

}
