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
	 * Room Name post meta
	 *
	 * @var string
	 */
	const ROOM_META = 'hmce_websocket_room_id';

	/**
	 * WebSocket Server URL
	 *
	 * @var string
	 */
	private $server_url = 'wss://ywebsocket1.herokuapp.com';

	/**
	 * Constructor
	 *
	 * @param array $args Initial arguments
	 */
	public function __construct( array $args = [] ) {
		if ( isset ( $args['server_url'] ) && ! empty( $args['server_url'] ) ) {
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

		if ( ! $post instanceof WP_Post ) {
			return [];
		}

		$room_name = $this->get_room_name( $post );

		return wp_parse_args( $app_data, [
			'connProvider' => 'websocket',
			'wsServerUrl' => $this->server_url,
			'roomName' => $room_name,
		] );
	}

	/**
	 * Get WebSocket Room Name
	 *
	 * @param WP_Post $post Post Object.
	 *
	 * @return string
	 */
	private function get_room_name( WP_Post $post ): string {
		$room_name = get_post_meta( $post->ID, self::ROOM_META, true );

		if ( ! $room_name ) {
			$room_name = wp_generate_uuid4() . '-' . sha1( $post->guid );
			update_post_meta( $post->ID, self::ROOM_META, $room_name );
		}

		return $room_name;
	}
}
