<?php

namespace CiviWP;

use Civi\Test\EndToEndInterface;

class PhpVersionTest extends \PHPUnit\Framework\TestCase implements EndToEndInterface {

  /**
   * CIVICRM_WP_PHP_MINIMUM (civicrm.module) should match MINIMUM_PHP_VERSION (CRM/Upgrade/Form.php).
   *
   * The literal value should be duplicated in the define() to prevent dependency issues.
   */
  public function testConstantMatch() {
    $constantFile = $this->getModulePath() . '/civicrm.php';
    $this->assertFileExists($constantFile);
    $content = file_get_contents($constantFile);
    if (preg_match(";define\\(\\s*'CIVICRM_WP_PHP_MINIMUM'\\s*,\\s*'(.*)'\\s*\\);", $content, $m)) {
      $this->assertEquals(\CRM_Upgrade_Form::MINIMUM_PHP_VERSION, $m[1]);
    }
    else {
      $this->fail('Failed to find CIVICRM_WP_PHP_MINIMUM in ' . $constantFile);
    }
  }

  /**
   * @return string
   *   Ex: '/var/www/wp-content/plugins/civicrm'
   */
  protected function getModulePath() {
    return dirname(dirname(dirname(__DIR__)));
  }

}
