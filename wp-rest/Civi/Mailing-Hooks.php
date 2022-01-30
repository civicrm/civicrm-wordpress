<?php
/**
 * CiviCRM Mailing_Hooks class.
 *
 * @since 5.25
 */

namespace CiviCRM_WP_REST\Civi;

class Mailing_Hooks {

  /**
   * @var string
   * Mailing Url endpoint.
   * @since 5.25
   */
  public $url_endpoint;

  /**
   * @var string
   * Mailing Open endpoint.
   * @since 5.25
   */
  public $open_endpoint;

  /**
   * @var array
   * The parsed WordPress REST url.
   * @since 5.25
   */
  public $parsed_rest_url;

  /**
   * Constructor.
   *
   * @since 5.25
   */
  public function __construct() {

    $this->url_endpoint = rest_url('civicrm/v3/url');

    $this->open_endpoint = rest_url('civicrm/v3/open');

    $this->parsed_rest_url = parse_url(rest_url());

  }

  /**
   * Register hooks.
   *
   * @since 5.25
   */
  public function register_hooks() {

    add_filter('civicrm_alterMailParams', [$this, 'do_mailing_urls'], 10, 2);

    add_filter('civicrm_alterExternUrl', [$this, 'alter_mailing_extern_urls'], 10, 6);

  }

  /**
   * Replaces the "open" and "click tracking" URLs for a CiviMail Mailing with
   * their REST counterparts.
   *
   * @uses 'civicrm_alterExternUrl' filter.
   *
   * @param \GuzzleHttp\Psr7\Uri $url
   * @param string|NULL $path
   * @param string|NULL $query
   * @param string|NULL $fragment
   * @param bool|NULL $absolute
   * @param bool|NULL $isSSL
   */
  public function alter_mailing_extern_urls(&$url, $path, $query, $fragment, $absolute, $isSSL) {

    if ($path == 'extern/url') {
      $url = $url
        ->withHost($this->parsed_rest_url['host'])
        ->withQuery($query)
        ->withPath("{$this->parsed_rest_url['path']}civicrm/v3/url");
    }

    if ($path == 'extern/open') {
      $url = $url
        ->withHost($this->parsed_rest_url['host'])
        ->withQuery($query)
        ->withPath("{$this->parsed_rest_url['path']}civicrm/v3/open");
    }

  }

  /**
   * Filters the mailing HTML and replaces calls to 'extern/url.php' and
   * 'extern/open.php' with their REST counterparts 'civicrm/v3/url' and
   * 'civicrm/v3/open'.
   *
   * @uses 'civicrm_alterMailParams'
   *
   * @since 5.25
   *
   * @param array &$params Mail params.
   * @param string $context The Context.
   * @return array $params The filtered Mail params.
   */
  public function do_mailing_urls(&$params, $context) {

    if (in_array($context, ['civimail', 'flexmailer'])) {

      if (!empty($params['html'])) {
        $params['html'] = $this->is_mail_tracking_url_alterable($params['html'])
          ? $this->replace_html_mailing_tracking_urls($params['html'])
          : $params['html'];
      }

      if (!empty($params['text'])) {
        $params['text'] = $this->is_mail_tracking_url_alterable($params['text'])
          ? $this->replace_text_mailing_tracking_urls($params['text'])
          : $params['text'];
      }

    }

    return $params;

  }

  /**
   * Replace HTML mailing tracking urls.
   *
   * @since 5.25
   *
   * @param string $content The mailing content.
   * @return string $content The mailing content.
   */
  public function replace_html_mailing_tracking_urls(string $content) {

    $doc = \phpQuery::newDocument($content);

    foreach ($doc['[href*="civicrm/extern/url.php"], [src*="civicrm/extern/open.php"]'] as $element) {

      $href = pq($element)->attr('href');
      $src = pq($element)->attr('src');

      // Replace extern/url.
      if (strpos($href, 'civicrm/extern/url.php')) {

        $query_string = strstr($href, '?');
        pq($element)->attr('href', $this->url_endpoint . $query_string);

      }

      // Replace extern/open.
      if (strpos($src, 'civicrm/extern/open.php')) {

        $query_string = strstr($src, '?');
        pq($element)->attr('src', $this->open_endpoint . $query_string);

      }

      unset($href, $src, $query_string);

    }

    return $doc->html();

  }

  /**
   * Replace text mailing tracking URLs.
   *
   * @since 5.25
   *
   * @param string $content The mailing content.
   * @return string $content The mailing content.
   */
  public function replace_text_mailing_tracking_urls(string $content) {

    // Replace extern URL.
    $content = preg_replace('/http.*civicrm\/extern\/url\.php/i', $this->url_endpoint, $content);

    // Replace open URL.
    $content = preg_replace('/http.*civicrm\/extern\/open\.php/i', $this->open_endpoint, $content);

    return $content;

  }

  /**
   * Checks whether for a given mail content (text or HTML) the tracking URLs
   * are alterable or need to be altered.
   *
   * @since 5.25
   *
   * @param string $content The mail content: text or HTML.
   * @return bool $is_alterable
   */
  public function is_mail_tracking_url_alterable($content) {

    if (!is_string($content)) {
      return FALSE;
    }

    return strpos($content, 'civicrm/extern/url.php') || strpos($content, 'civicrm/extern/open.php');

  }

}
