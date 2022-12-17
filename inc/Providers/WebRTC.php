<?php
/**
 * Class WebRTC Provider
 */

namespace Fathanfi\WpCollaborativeEditing\Providers;

use Fathanfi\WpCollaborativeEditing\Interfaces;

/**
 * Class WebRTC
 *
 * @package Fathanfi\WpCollaborativeEditing\Providers
 */
final class WebRTC implements Interfaces\ConnectionProvider {
	/**
	 * WebRTC Signaling Server URL
	 *
	 * @var string
	 */
	private $server_url = [
		'wss://signaling.yjs.dev',
		'wss://y-webrtc-signaling-eu.herokuapp.com',
		'wss://y-webrtc-signaling-us.herokuapp.com',
	];

	/**
	 * Constructor
	 *
	 * @param array $args Initial arguments
	 */
	public function __construct( array $args = [] ) {
		if ( isset ( $args['server_url'] ) && ! is_array( $args['server_url'] ) ) {
			$this->server_url = $args['server_url'];
		}
	}

	/**
	 * Register network service requirements.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'hmce_app_data', [ $this, 'generate_app_data' ] );
	}

	/**
	 * Generate app data to be exposed to JS
	 *
	 * @use hmce_app_data filter
	 *
	 * @param array $app_data localized app data.
	 *
	 * @return array
	 */
	public function generate_app_data( array $app_data ): array {
		global $post;

		return wp_parse_args( $app_data, [
			'connProvider' => 'webrtc',
			'roomName' => sha1( $post->guid ),
			'secret' => wp_hash( $post->guid ),
			'signalingServerUrls' => $this->server_url,
		] );
	}
}
