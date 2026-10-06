<?php

namespace CiviWP;

use Civi\Test\EndToEndInterface;

/**
 * Core resources must be built from the CiviCRM route, front end and back end.
 *
 * @group e2e
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CoreResourcesTest extends \PHPUnit\Framework\TestCase implements EndToEndInterface {

  const DT_JS = 'bower_components/datatables/media/js/jquery.dataTables.min.js';
  const DT_CSS = 'bower_components/datatables/media/css/jquery.dataTables.min.css';

  /**
   * One entry per coreResourceList build: items + request state at build time.
   *
   * @var array
   */
  private static $builds = [];

  protected function setUp(): void {
    parent::setUp();
    self::$builds = [];
    $_GET = $_POST = $_COOKIE = $_REQUEST = [];

    // Record state at the moment core builds the resource list.
    add_action('civicrm_coreResourceList', function(&$items, $region) {
      self::$builds[] = [
        'items' => $items,
        'path' => \CRM_Utils_System::currentPath(),
        'frontend' => \CRM_Utils_System::isFrontEndPage(),
      ];
    }, 10, 2);
  }

  /**
   * Check boot.
   */
  public function testHarness(): void {
    $this->assertInstanceOf(\WP_Query::class, $GLOBALS['wp_query'] ?? NULL, 'No global WP_Query under cv boot.');
    set_query_var('q', 'civicrm/user');
    $this->assertSame('civicrm/user', get_query_var('q'));
    $this->assertFalse(is_admin(), 'CLI boot unexpectedly reports is_admin().');
    $this->assertTrue(civi_wp()->initialize(), 'Plugin did not initialize CiviCRM.');
  }

  /**
   * clean URL /civicrm/user/ - using the issue https://lab.civicrm.org/dev/wordpress/-/work_items/167 as an example
   *
   * Route only exists as a WP query var. Expect DataTables.
   */
  public function testFrontEndCleanUrlUserDashboard(): void {
    set_query_var('q', 'civicrm/user');
    civi_wp()->add_core_resources(TRUE);

    $build = $this->assertSingleBuild();
    $this->assertSame('civicrm/user', $build['path']);
    // civicrm/user is_public, so this is correctly TRUE.
    $this->assertTrue($build['frontend']);
    $this->assertContains(self::DT_JS, $build['items']);
    $this->assertContains(self::DT_CSS, $build['items']);
  }

  /**
   * clean URL, other public route. Path populated, no DataTables.
   */
  public function testFrontEndCleanUrlPublicRoute(): void {
    set_query_var('q', 'civicrm/event/info');
    civi_wp()->add_core_resources(TRUE);

    $build = $this->assertSingleBuild();
    $this->assertSame('civicrm/event/info', $build['path']);
    $this->assertTrue($build['frontend']);
    $this->assertNotContains(self::DT_JS, $build['items']);
    $this->assertNotContains(self::DT_CSS, $build['items']);
  }

  /**
   * control - non-clean URL, route already in $_GET.
   */
  public function testFrontEndQueryStringUserDashboard(): void {
    $_GET['q'] = $_REQUEST['q'] = 'civicrm/user';
    civi_wp()->add_core_resources(TRUE);

    $build = $this->assertSingleBuild();
    $this->assertSame('civicrm/user', $build['path']);
    $this->assertContains(self::DT_JS, $build['items']);
  }

  /**
   * control - wp-admin/admin.php?page=CiviCRM&q=civicrm/user
   */
  public function testBackEndUserDashboard(): void {
    // is_admin() falls back to WP_ADMIN when no current_screen is set.
    define('WP_ADMIN', TRUE);
    $this->assertTrue(is_admin());

    $_GET['page'] = $_REQUEST['page'] = 'CiviCRM';
    $_GET['q'] = $_REQUEST['q'] = 'civicrm/user';
    civi_wp()->add_core_resources(FALSE);

    $build = $this->assertSingleBuild();
    $this->assertSame('civicrm/user', $build['path']);
    $this->assertFalse($build['frontend']);
    $this->assertContains(self::DT_JS, $build['items']);
  }

  /**
   * clean URL ajax route - core must skip page resources entirely.
   */
  public function testFrontEndCleanUrlAjaxRoute(): void {
    set_query_var('q', 'civicrm/ajax/rest');
    civi_wp()->add_core_resources(TRUE);

    $this->assertCount(0, self::$builds, 'Core resources were built for an ajax route.');
  }

  /**
   * superglobals restored exactly, slashes intact, no route leakage.
   */
  public function testFrontEndSuperglobalsRestored(): void {
    $_GET['x'] = $_REQUEST['x'] = "O\\'Reilly";
    $_POST['y'] = "a\\\"b";
    $before = [$_GET, $_POST, $_COOKIE, $_REQUEST];

    set_query_var('q', 'civicrm/user');
    civi_wp()->add_core_resources(TRUE);

    $this->assertSame($before, [$_GET, $_POST, $_COOKIE, $_REQUEST]);
    $this->assertArrayNotHasKey('q', $_GET);
    $this->assertArrayNotHasKey('civiwp', $_GET);
  }

  /**
   * back end: same guarantee for the admin call.
   */
  public function testBackEndSuperglobalsRestored(): void {
    define('WP_ADMIN', TRUE);
    $_GET['page'] = $_REQUEST['page'] = 'CiviCRM';
    $_GET['q'] = $_REQUEST['q'] = 'civicrm/user';
    $_GET['x'] = $_REQUEST['x'] = "O\\'Reilly";
    $before = [$_GET, $_POST, $_COOKIE, $_REQUEST];

    civi_wp()->add_core_resources(FALSE);

    $this->assertSame($before, [$_GET, $_POST, $_COOKIE, $_REQUEST]);
  }

  /**
   * front end: WP request pipeline.
   *
   * wp() -> parse_request -> parse_query -> 'wp'
   * (basepage invoke() @10, front_end_page_load @100) - https://lab.civicrm.org/dev/wordpress/-/work_items/167
   */
  public function testFrontEndRequestPipeline(): void {
    // Denied users never get front_end_page_load registered.
    wp_set_current_user($this->adminId());

    // Register the plugin's clean-URL rule and rebuild stored rules (soft flush).
    // A CLI boot may not have it, e.g. CI rules flushed by wp-cli.
    civi_wp()->rewrite_rules(FALSE);
    $GLOBALS['wp_rewrite']->flush_rules(FALSE);
    $this->assertNotEmpty(preg_grep('/civiwp=CiviCRM/', (array) get_option('rewrite_rules')), 'CiviCRM rewrite rule not registered.');

    $url = $this->cleanUrl('civicrm/user');
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = parse_url($url, PHP_URL_PATH) . '?reset=1';
    $_GET = $_REQUEST = ['reset' => '1'];

    ob_start();
    wp();
    ob_end_clean();

    $this->assertSame('civicrm/user', get_query_var('q'), 'Rewrite rules did not resolve the route.');
    $build = $this->assertSingleBuild();
    $this->assertSame('civicrm/user', $build['path']);
    $this->assertContains(self::DT_JS, $build['items']);
  }

  /**
   * back end:  load-{menu_page} callback.
   *
   * Admin hooks are not registered under CLI (is_admin() false at init),
   * so call the callback directly.
   */
  public function testBackEndAdminPageLoad(): void {
    define('WP_ADMIN', TRUE);
    wp_set_current_user($this->adminId());
    $_GET['page'] = $_REQUEST['page'] = 'CiviCRM';
    $_GET['q'] = $_REQUEST['q'] = 'civicrm/user';

    civi_wp()->admin->admin_page_load();

    $build = $this->assertSingleBuild();
    $this->assertFalse($build['frontend']);
    $this->assertContains(self::DT_JS, $build['items']);
  }

  /**
   * real HTTP requests through the web server, front and back.
   *
   * Needs the site reachable from the test container and the patch
   * applied to the served code.
   *
   * @group e2e-http
   */
  public function testHttpFrontAndBack(): void {
    $id = $this->adminId();
    $ssl = parse_url(home_url(), PHP_URL_SCHEME) === 'https';
    $cookies = [
      new \WP_Http_Cookie(['name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie($id, time() + 300, 'logged_in')]),
      new \WP_Http_Cookie(['name' => $ssl ? SECURE_AUTH_COOKIE : AUTH_COOKIE, 'value' => wp_generate_auth_cookie($id, time() + 300, $ssl ? 'secure_auth' : 'auth')]),
    ];
    foreach ([
      $this->cleanUrl('civicrm/user') . '?reset=1',
      admin_url('admin.php?page=CiviCRM&q=civicrm%2Fuser'),
    ] as $url) {
      $response = wp_remote_get($url, ['cookies' => $cookies, 'sslverify' => FALSE]);
      $this->assertFalse(is_wp_error($response), $url);
      // Boolean assert so a failure doesn't dump the whole page.
      $this->assertTrue(strpos(wp_remote_retrieve_body($response), self::DT_JS) !== FALSE, "DataTables not loaded: $url");
    }
  }

  /**
   * Front-end clean URL, built the way the plugin's rewrite rule matches it.
   *
   * Rule: ^{wpBasePage}/(...) -> q=civicrm/$1 (CiviCRM_For_WordPress::rewrite_rules()).
   * Built by hand because CRM_Utils_System::url() follows CIVICRM_CLEANURL,
   * which may differ under CLI.
   */
  private function cleanUrl(string $path): string {
    $base = \CRM_Core_Config::singleton()->wpBasePage;
    $this->assertNotEmpty($base, 'No wpBasePage configured.');
    return home_url('/' . $base . '/' . preg_replace('#^civicrm/#', '', $path) . '/');
  }

  /**
   * First WP administrator - must also have 'access Contact Dashboard'.
   */
  private function adminId(): int {
    $ids = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    $this->assertNotEmpty($ids, 'No administrator on the test site.');
    return (int) $ids[0];
  }

  /**
   * The bundle must be built exactly once, or the test proves nothing.
   */
  private function assertSingleBuild(): array {
    $this->assertCount(1, self::$builds, 'coreResourceList did not fire exactly once (cached bundle?).');
    return self::$builds[0];
  }

}
