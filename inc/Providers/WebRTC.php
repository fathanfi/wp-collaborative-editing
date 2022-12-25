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
	 * Room Name post meta
	 *
	 * @var string
	 */
	const ROOM_META = 'hmce_webrtc_room_id';

	/**
	 * Room Secret post meta
	 *
	 * @var string
	 */
	const ROOM_SECRET_META = 'hmce_webrtc_room_secret';

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
		if ( isset( $args['server_url'] ) && ! is_array( $args['server_url'] ) ) {
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
		$room_secret = $this->get_room_secret( $post );

		if ( ! $room_secret ) {
			$room_secret = wp_generate_uuid4();
			update_post_meta( $post->ID, self::ROOM_SECRET_META, $room_secret );
		}

		return wp_parse_args( $app_data, [
			'connProvider' => 'webrtc',
			'roomName' => $room_name,
			'secret' => $room_secret,
			'signalingServerUrls' => $this->server_url,
		] );
	}

	/**
	 * Get WebRTC Room Name
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

	/**
	 * Get WebRTC Room Secret
	 *
	 * @param WP_Post $post Post Object.
	 *
	 * @return string
	 */
	private function get_room_secret( WP_Post $post ): string {
		$room_secret = get_post_meta( $post->ID, self::ROOM_SECRET_META, true );

		if ( ! $room_secret || ! wp_is_uuid( $room_secret ) ) {
			$room_secret = wp_generate_uuid4();
			update_post_meta( $post->ID, self::ROOM_SECRET_META, $room_secret );
		}

		return $room_secret;
	}
}
