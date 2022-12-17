<?php
/**
 * ViteJS integration
 *
 * See https://vitejs.dev
 */

namespace Fathanfi\WpCollaborativeEditing\Vite;

const VITE_CLIENT_SCRIPT_HANDLE = 'vite-client';

/**
 * Enqueue asset
 *
 * @param string $manifest_path Manifest file path.
 * @param string $entry         Entry filename.
 * @param array  $args          Misc. arguments for vite.
 *
 * @return bool Enqueue status.
 */
function enqueue( string $manifest_path, string $entry, array $args ): bool {
	$args = sanitize_args( $args );

	if ( empty( $args['handle'] ) || ! is_string( $args['handle'] ) ) {
		return false;
	}

	$manifest = get_manifest( $manifest_path );
	$is_production = is_array( $manifest ) && ! empty( $manifest[ $entry ] );

	if ( $is_production ) {
		return enqueue_production_assets( $manifest, $entry, $args );
	} else {
		return enqueue_development_assets( $entry, $args );
	}
}

/**
 * Sanitize enqueue arguments
 *
 * @param array $args Arguments passed to enqueue().
 *
 * @return array Sanitized arguments array.
 */
function sanitize_args( array $args ): array {
	$vite_defaults = [
		'base' => '/',
		'server_origin' => 'http://localhost:3000',
		'with_react_refresh' => false,
	];

	$args = wp_parse_args( $args, [
		'dependencies' => [],
		'public_url' => home_url(),
		'in_footer' => false,
		'style_dependencies' => [],
		'vite' => [],
	] );

	$args['vite'] = wp_parse_args( $args['vite'], $vite_defaults );

	return $args;
}

/**
 * Enqueue development assets
 *
 * @param string $entry Entry filename.
 * @param array  $args  Sanitized arguments passed to enqueue().
 *
 * @return bool Enqueue status.
 */
function enqueue_development_assets( string $entry, array $args ): bool {
	enqueue_vite_client( $args['vite'] );

	$dependencies = array_merge(
		[ VITE_CLIENT_SCRIPT_HANDLE ],
		$args['dependencies']
	);

	$src = sprintf(
		'%s/%s',
		untrailingslashit( $args['vite']['server_origin'] ),
		trim( $entry, '/' )
	);

	wp_enqueue_script( $args['handle'], $src, $dependencies, null, $args['in_footer'] );

	set_script_tag_attributes( $args['handle'] );

	return true;
}

/**
 * Enqueue production assets
 *
 * @param array  $manifest Assets manifest data.
 * @param string $entry    Entry filename.
 * @param array  $args     Validated arguments passed to enqueue().
 *
 * @return bool Enqueue status.
 */
function enqueue_production_assets( array $manifest, string $entry, array $args ): bool {
	if ( empty( $manifest[ $entry ] ) ) {
		return false;
	}

	$public_url = untrailingslashit( $args['public_url'] );
	$item = $manifest[ $entry ];
	$src = sprintf( '%s/%s', $public_url, $item->file );

	wp_enqueue_script( $args['handle'], $src, $args['dependencies'], null, true );

	set_script_tag_attributes( $args['handle'] );

	if ( empty( $item->css ) ) {
		return true;
	}

	foreach ( $item->css as $index => $css_file_path ) {
		wp_enqueue_style(
			"{$args['handle']}-{$index}",
			sprintf( '%s/%s', $public_url, $css_file_path ),
			$args['style_dependencies'],
			null
		);
	}

	return true;
}

/**
 * Get assets manifest
 *
 * @param string $manifest_file Manifest file path.
 *
 * @return array|null Manifest data or NULL if file doesn't exist or manifest is empty/broken.
 */
function get_manifest( string $manifest_file ): ?array {
	if ( ! file_exists( $manifest_file ) ) {
		return null;
	}

	$data = json_decode( file_get_contents( $manifest_file ) );

	if ( json_last_error() ) {
		return null;
	}

	return (array) $data;
}

/**
 * Enqueue vite client script
 *
 * @param array $vite_config Vite configuration.
 *
 * @return void
 */
function enqueue_vite_client( array $vite_config ): void {
	if ( wp_script_is( VITE_CLIENT_SCRIPT_HANDLE ) ) {
		return;
	}

	$src = sprintf(
		'%s/@vite/client',
		untrailingslashit( $vite_config['server_origin'] ),
	);

	wp_enqueue_script( VITE_CLIENT_SCRIPT_HANDLE, $src, [], null );

	set_script_tag_attributes( VITE_CLIENT_SCRIPT_HANDLE );

	if ( $vite_config['with_react_refresh'] ) {
		add_filter(
			'script_loader_tag',
			function ( ...$args ) use ( $vite_config ) {
				return inject_react_refresh_script( $vite_config, ...$args );
			},
			10,
			3
		);
	}
}

/**
 * Set script tag attributes
 *
 * This creates a dynamic function to be attached to the `script_loader_tag` filter hook.
 *
 * @param string $handle Script handle.
 *
 * @return void
 */
function set_script_tag_attributes( string $handle ): void {
	$callback = function ( ...$args ) use ( $handle ) {
		return modify_script_tag_attributes( $handle, ...$args );
	};

	add_filter( 'script_loader_tag', $callback, 10, 3 );
}

/**
 * Inject react refresh script
 *
 * @param array  $vite_config Vite configuration
 * @param string $tag         Original script tag.
 * @param string $handle      Script handle.
 *
 * @return string Script tag appended with react refresh script.
 */
function inject_react_refresh_script( array $vite_config, string $tag, string $handle ): string {
	if ( $handle !== VITE_CLIENT_SCRIPT_HANDLE ) {
		return $tag;
	}

	$script_path = sprintf(
		'%s/@react-refresh',
		untrailingslashit( $vite_config['server_origin'] ),
	);

	$tag = <<<"EOS"
{$tag}
<script type="module">
import RefreshRuntime from "{$script_path}";
RefreshRuntime.injectIntoGlobalHook(window);
window.__VITE_IS_MODERN__ = true;
window.\$RefreshReg$ = () => {};
window.\$RefreshSig$ = () => (type) => type;
window.__vite_plugin_react_preamble_installed__ = true;
</script>

EOS;

	return $tag;
}

/**
 * Add `type="module"` to script tags
 *
 * @see https://vitejs.dev/guide/backend-integration.html
 *
 * @param string $handle         Script handle.
 * @param string $tag            Original script tag.
 * @param string $current_handle Current script handle being filtered.
 * @param string $src            Script source URL.
 *
 * @return string Script tag with attribute `type="module"` added.
 */
function modify_script_tag_attributes( string $handle, string $tag, string $current_handle, string $src ): string {
	if ( $handle === $current_handle ) {
		$tag = sprintf( '<script type="module" src="%s" id="%s"></script>%s', esc_url( $src ), esc_attr( $handle ), "\n" );
	}

	return $tag;
}
