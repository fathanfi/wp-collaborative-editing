<?php
/**
 * Class Connection.
 */

namespace Fathanfi\WpCollaborativeEditing\Admin;

use Fathanfi\WpCollaborativeEditing\Providers;

/**
 * Class Connection
 *
 * @package Fathanfi\WpCollaborativeEditing\Admin
 */
final class Connection {

	const ALLOWED_PROVIDERS = [
		'websocket' => Providers\WebSocket::class,
		'webrtc' => Providers\webrtc::class,
	];

	/**
	 * Kickstart all actions and filters.
	 */
	public function register(): void {
		// Register our provider as late as possible to allow filter.
		add_action( 'admin_init', [ $this, 'register_provider' ], PHP_INT_MAX );
	}

	public function register_provider() {
		$connection_provider = apply_filters( 'hmce_connection_provider', 'websocket' );

		if ( ! array_key_exists( $connection_provider, self::ALLOWED_PROVIDERS ) ) {
			$connection_provider = 'websocket';
		}

		$connection_provider_class = self::ALLOWED_PROVIDERS[ $connection_provider ];

		$args = apply_filters( 'hmce_init_args_' . $connection_provider_class, [] );

		$conn = new $connection_provider_class( $args );

		do_action( 'hmce_init_' . $connection_provider );

		$conn->register();
	}
}
