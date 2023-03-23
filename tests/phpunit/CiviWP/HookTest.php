<?php

namespace CiviWP {

  use Civi\Test\EndToEndInterface;

  /**
   * Class HookTest
   * @package CiviWP
   * @group e2e
   */
  class HookTest extends \PHPUnit\Framework\TestCase implements EndToEndInterface {

    public function testFoo(): void {
      add_action('civicrm_fakeAlterableHook', 'onFakeAlterableHook', 10, 2);

      $arg1 = 'hello';
      $arg2 = array(
        'foo' => 123,
      );
      $this->assertNotEquals($arg2['foo'], 456);
      $this->assertFalse(isset($arg2['hook_was_called']));
      $null = NULL;
      \CRM_Utils_Hook::singleton()
        ->invoke(
          2,
          $arg1,
          $arg2,
          $null,
          $null,
          $null,
          $null,
          'civicrm_fakeAlterableHook'
        );

      $this->assertEquals($arg2['foo'], 456);
      $this->assertEquals($arg2['hook_was_called'], 1);
    }

  }

}

namespace {

  function onFakeAlterableHook($arg1, &$arg2) {
    if ($arg1 != 'hello') {
      throw new \Exception("Failed to receive arg1");
    }
    if ($arg2['foo'] != 123) {
      throw new \Exception("Failed to receive arg2[foo]");
    }
    $arg2['foo'] = 456;
    $arg2['hook_was_called'] = 1;
  }

}
