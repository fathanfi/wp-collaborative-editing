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
	 * @param array $server_url WebRTC Signaling server URL
	 */
	public function __construct( array $server_url = [] ) {
		if ( ! empty( $server_url ) && is_array( $server_url ) ) {
			$this->server_url = $server_url;
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
			'signalingServerUrls' => $this->server_url,
		] );
	}
}
