<?php
/**
 * CiviCRM Mailing_Hooks class.
 *
 * @since 0.1
 */

namespace CiviCRM_WP_REST\Civi;

class Mailing_Hooks {

	/**
	 * Mailing Url endpoint.
	 *
	 * @since 0.1
	 * @var string
	 */
	public $url_endpoint;

	/**
	 * Mailing Open endpoint.
	 *
	 * @since 0.1
	 * @var string
	 */
	public $open_endpoint;

	/**
	 * Constructor.
	 *
	 * @since 0.1
	 */
	public function __construct() {

		$this->url_endpoint = rest_url( 'civicrm/v3/url' );

		$this->open_endpoint = rest_url( 'civicrm/v3/open' );

	}

	/**
	 * Register hooks.
	 *
	 * @since 0.1
	 */
	public function register_hooks() {

		add_filter( 'civicrm_alterMailParams', [ $this, 'do_mailing_urls' ], 10, 2 );

	}

	/**
	 * Filters the mailing html and replaces calls to 'extern/url.php' and
	 * 'extern/open.php' with their REST counterparts 'civicrm/v3/url' and 'civicrm/v3/open'.
	 *
	 * @uses 'civicrm_alterMailParams'
	 *
	 * @since 0.1
	 * @param array &$params Mail params
	 * @param string $context The Context
	 * @return array $params The filtered Mail params
	 */
	public function do_mailing_urls( &$params, $context ) {

		if ( $context == 'civimail' ) {

			$params['html'] = $this->replace_html_mailing_tracking_urls( $params['html'] );

			$params['text'] = $this->replace_text_mailing_tracking_urls( $params['text'] );

		}

		return $params;

	}

	/**
	 * Replace html mailing tracking urls.
	 *
	 * @since 0.1
	 * @param string $contnet The mailing content
	 * @return string $content The mailing content
	 */
	public function replace_html_mailing_tracking_urls( string $content ) {

		$doc = \phpQuery::newDocument( $content );

		foreach ( $doc[ '[href*="civicrm/extern/url.php"], [src*="civicrm/extern/open.php"]' ] as $element ) {

			$href = pq( $element )->attr( 'href' );
			$src = pq( $element )->attr( 'src' );

			// replace extern/url
			if ( strpos( $href, 'civicrm/extern/url.php' ) )	{

				$query_string = strstr( $href, '?' );
				pq( $element )->attr( 'href', $this->url_endpoint . $query_string );

			}

			// replace extern/open
			if ( strpos( $src, 'civicrm/extern/open.php' ) ) {

				$query_string = strstr( $src, '?' );
				pq( $element )->attr( 'src', $this->open_endpoint . $query_string );

			}

			unset( $href, $src, $query_string );

		}

		return $doc->html();

	}

	/**
	 * Replace text mailing tracking urls.
	 *
	 * @since 0.1
	 * @param string $contnet The mailing content
	 * @return string $content The mailing content
	 */
	public function replace_text_mailing_tracking_urls( string $content ) {

		// replace extern url
		$content = preg_replace( '/http.*civicrm\/extern\/url\.php/i', $this->url_endpoint, $content );

		// replace open url
		$content = preg_replace( '/http.*civicrm\/extern\/open\.php/i', $this->open_endpoint, $content );

		return $content;

	}

}
