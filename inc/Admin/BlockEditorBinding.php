<?php
/**
 * Class BlockEditor.
 */

namespace Fathanfi\WpCollaborativeEditing\Admin;

use Fathanfi\WpCollaborativeEditing\Vite;
use WP_Block_Editor_Context;

/**
 * Class BlockEditor
 *
 * @package Fathanfi\WpCollaborativeEditing\Admin
 */
final class BlockEditorBinding {
	/**
	 * Application Slug
	 */
	const APP_SLUG = 'ff-collaborative-editing';

	/**
	 * Kickstart all actions and filters.
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_init', [ $this, 'disable_post_lock' ] );
	}

	/**
	 * Enqueue JavaScript and CSS assets.
	 */
	public function enqueue_assets(): void {
		if ( ! $this->is_post_edit_screen() ) {
			return;
		}

		$plugin_dir_url = untrailingslashit( plugin_dir_url( dirname( __DIR__ ) ) );
		$manifest_path  = sprintf( '%s/assets/dist/manifest.json', dirname( __DIR__, 2 ) );

		Vite\enqueue( $manifest_path, 'assets/src/js/main.js', [
			'handle'       => self::APP_SLUG,
			'in_footer'    => true,
			'public_url'   => "{$plugin_dir_url}/assets/dist",
			'dependencies' => [
				'wp-compose',
				'wp-data',
				'wp-edit-post',
				'wp-editor',
				'wp-hooks',
				'wp-plugins',
			],
			'vite'         => [
				'base'               => '',
				'server_origin'      => 'http://localhost:3010',
				'with_react_refresh' => true,
			],
		] );

		wp_localize_script( self::APP_SLUG, 'ffCollaborativeEditing', $this->generate_app_data() );
	}

	/**
	 * Disable post locking feature from the core.
	 *
	 * @return void
	 */
	public function disable_post_lock(): void {
		// Remove check for post lock, so post lock modal will not show anymore.
		// This feature come from the core and cannot be removed properly at this point.
		remove_filter( 'heartbeat_received', 'wp_refresh_post_lock' );
		add_filter( 'block_editor_settings_all', function ( array $editor_settings, WP_Block_Editor_Context $editor_context ): array {
			if ( empty( $editor_context->post ) ) {
				return $editor_settings;
			}

			$lock_details                = [
				'isLocked'       => false,
				'activePostLock' => '',
			];
			$editor_settings['postLock'] = $lock_details;

			return $editor_settings;
		}, 10, 2 );
	}

	/**
	 * Generate app data to be exposed to JS
	 *
	 * @return array
	 */
	private function generate_app_data(): array {
		global $post;

		$app_data = [
			'post_id' => absint( $post->ID ),
			'slug'    => $post->post_name,
		];

		return apply_filters( 'hmce_app_data', $app_data );
	}

	/**
	 * Check if we're on post edit screen of supported post types
	 *
	 * @return bool
	 */
	private function is_post_edit_screen(): bool {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return false;
		}

		return $screen->base === 'post' && $screen->post_type === 'post';
	}
}
