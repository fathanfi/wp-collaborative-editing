<?php
/**
 * Class WebSocket Provider
 */

namespace Fathanfi\WpCollaborativeEditing\Providers;

use Fathanfi\WpCollaborativeEditing\Interfaces;

/**
 * Class WebSocket
 *
 * @package Fathanfi\WpCollaborativeEditing\Providers
 */
final class WebSocket implements Interfaces\ConnectionProvider {

	/**
	 * WebSocket Server URL
	 *
	 * @var string
	 */
	private $server_url = 'wss://ywebsocket1.herokuapp.com';

	/**
	 * Constructor
	 *
	 * @param string $server_url WebSocket server URL
	 */
	public function __construct( string $server_url = '' ) {
		if ( ! empty( $server_url ) ) {
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
			'connProvider' => 'websocket',
			'wsServerUrl' => $this->server_url,
			'roomName' => sha1( $post->guid ),
		] );
	}
}
