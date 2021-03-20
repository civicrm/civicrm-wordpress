<?php

namespace CiviWP;

use Civi\Test\EndToEndInterface;

class PhpVersionTest extends \PHPUnit\Framework\TestCase implements EndToEndInterface {

  /**
   * CIVICRM_WP_PHP_MINIMUM (civicrm.module) should match
   * CRM_Upgrade_Incremental_General::MIN_INSTALL_PHP_VER.
   *
   * The literal value should be duplicated in the define() to prevent dependency issues.
   */
  public function testConstantMatch(): void {
    $constantFile = $this->getModulePath() . '/civicrm.php';
    $this->assertFileExists($constantFile);
    $content = file_get_contents($constantFile);
    if (preg_match(";define\\(\\s*'CIVICRM_WP_PHP_MINIMUM'\\s*,\\s*'(.*)'\\s*\\);", $content, $m)) {
      $a = preg_replace(';^(\d+\.\d+(?:\.[1-9]\d*)?).*$;', '\1', \CRM_Upgrade_Incremental_General::MIN_INSTALL_PHP_VER);
      $b = preg_replace(';^(\d+\.\d+(?:\.[1-9]\d*)?).*$;', '\1', $m[1]);
      $this->assertEquals($a, $b);
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
