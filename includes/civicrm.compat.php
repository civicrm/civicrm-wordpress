<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 *
 */

// This file must not accessed directly.
if (!defined('ABSPATH')) {
  exit;
}

/**
 * Compatibility class.
 *
 * @since 5.24
 */
class CiviCRM_For_WordPress_Compat {

  /**
   * @var object
   * Plugin object reference.
   * @since 5.24
   * @access public
   */
  public $civi;

  /**
   * @var object
   * Miscellaneous plugin compatibility object.
   * @since 5.66
   * @access public
   */
  public $misc;

  /**
   * @var object
   * Polylang compatibility object.
   * @since 5.66
   * @access public
   */
  public $polylang;

  /**
   * @var object
   * WPML compatibility object.
   * @since 5.66
   * @access public
   */
  public $wpml;

  /**
   * Instance constructor.
   *
   * @since 5.24
   */
  public function __construct() {

    // Store reference to CiviCRM plugin object.
    $this->civi = civi_wp();

    // Includes and setup.
    $this->include_files();
    $this->setup_objects();

  }

  /**
   * Include files.
   *
   * @since 5.66
   */
  public function include_files() {

    // Include plugin compatibility files.
    include_once CIVICRM_PLUGIN_DIR . 'includes/compatibility/civicrm.misc.php';
    include_once CIVICRM_PLUGIN_DIR . 'includes/compatibility/civicrm.polylang.php';
    include_once CIVICRM_PLUGIN_DIR . 'includes/compatibility/civicrm.wpml.php';

  }

  /**
   * Instantiate objects.
   *
   * @since 5.66
   */
  public function setup_objects() {

    // Instantiate plugin compatibility objects.
    $this->misc = new CiviCRM_For_WordPress_Compat_Misc();
    $this->polylang = new CiviCRM_For_WordPress_Compat_Polylang();
    $this->wpml = new CiviCRM_For_WordPress_Compat_WPML();

  }

}
