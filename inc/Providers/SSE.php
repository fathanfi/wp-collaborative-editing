<?php
/**
 * Class SSE Provider
 */

namespace Fathanfi\WpCollaborativeEditing\Providers;

use Exception;
use Fathanfi\WpCollaborativeEditing\Libraries\Queue;
use Fathanfi\WpCollaborativeEditing\Interfaces;
use V8Js;
use WP_Post;
use WP_User;

/**
 * Class SSE (Server Side-Event)
 *
 * @package Fathanfi\WpCollaborativeEditing\Providers
 */
final class SSE implements Interfaces\ConnectionProvider {

	/**
	 * Application Slug
	 */
	const APP_SLUG = 'hmce-sse';

	/**
	 * Delta Updates Ajax Action
	 */
	const MESSAGE_UPDATE_ACTION = 'hmce_delta_updates';

	/**
	 * Nonce
	 */
	const AJAX_NONCE = 'hmce-nonce';

	/**
	 * Server Side-Event Request action
	 *
	 * @var string
	 */
	const SSE_REQUEST_ACTION = 'hmce-sse';

	/**
	 * Type of message received from client to sync message
	 *
	 * @var int
	 */
	const MESSAGE_SYNC = 0;

	/**
	 * Type of message received from client to sync awareness
	 *
	 * @var int
	 */
	const MESSAGE_AWARENESS_SYNC = 1;

	/**
	 * Type of message received from client to sync message
	 *
	 * @var int
	 */
	const MESSAGE_CLIENT_REGISTER = 2;

	/**
	 * Kickstart all actions and filters.
	 */
	public function register(): void {
		add_action( 'admin_init', [ $this, 'sse_broadcast_channel' ] );
		add_action( 'wp_ajax_' . self::MESSAGE_UPDATE_ACTION, [ $this, 'receive_delta_updates' ] );

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

		$sse_request_action = self::SSE_REQUEST_ACTION;
		$nonce = wp_create_nonce( self::AJAX_NONCE );

		return wp_parse_args( $app_data, [
			'connProvider' => 'sse',
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'app_nonce' => $nonce,
			'message_update_action' => self::MESSAGE_UPDATE_ACTION,
			'sse_url' => admin_url( "/?{$sse_request_action}=1&post_id={$post->ID}&nonce={$nonce}" ),
		] );
	}

	/**
	 * Ajax action to receive delta updates.
	 *
	 * @return void
	 */
	public function receive_delta_updates() {
		if ( ! isset( $_REQUEST['nonce'] ) ) {
			wp_send_json_error( 'Nonce not exist', 401 );

			return;
		}

		// Check for nonce security.
		$nonce = sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, self::AJAX_NONCE ) ) {
			wp_send_json_error( 'Nonce not valid', 401 );

			return;
		}

		// Get the Post ID & Client ID from the HTTP POST.
		if ( empty( $_REQUEST['post_id'] ) || empty( $_REQUEST['client_id'] ) ) {
			wp_send_json_error( 'Post ID or Client ID not exist', 401 );

			return;
		}

		$post_id = absint( wp_unslash( $_REQUEST['post_id'] ) );
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			wp_send_json_error( 'Post ID not found', 404 );

			return;
		}

		$current_user = wp_get_current_user();
		if ( ! $current_user instanceof WP_User ) {
			wp_send_json_error( 'User not found', 404 );

			return;
		}

		// Get the state from the HTTP POST.
		if ( empty( $_REQUEST['data'] ) || ! isset( $_REQUEST['type'] ) ) {
			wp_send_json_error( 'Payload is missing', 401 );

			return;
		}

		// Removes the QM ajax header, causing entity too large for the header.
		add_filter( 'qm/dispatch/ajax', '__return_false', 99 );

		$type = absint( wp_unslash( $_REQUEST['type'] ) );
		$data = sanitize_text_field( wp_unslash( $_REQUEST['data'] ) );

		$response = [];

		switch ( $type ) {
			case self::MESSAGE_SYNC:
				$this->sync_document_state( $post, $current_user, $data );
				break;
			case self::MESSAGE_AWARENESS_SYNC:
				$this->sync_awareness_state( $post, $current_user, $data );
				break;
			case self::MESSAGE_CLIENT_REGISTER:
				$response = $this->register_peers( $post, $current_user, $data );
				break;
		}

		wp_send_json_success( $response );
	}

	/**
	 * Sync document state.
	 *
	 * @param WP_Post $post     Post ID
	 * @param WP_User $user     User ID
	 * @param string  $document Document State
	 *
	 * @return void
	 */
	private function sync_document_state( WP_Post $post, WP_User $user, string $document ) {
		$shared_update = $this->apply_document_delta_updates( $post->ID, $document );

		// Do nothing when error happen.
		if ( ! $shared_update ) {
			return;
		}

		// Get user queue array.
		$registered_peers = $this->get_user_queue( $post->ID )->toArray();

		foreach ( $registered_peers as $user_id ) {
			$message_queue = $this->get_document_queue( $post->ID, $user_id );
			$message_queue->push( [
				'type' => self::MESSAGE_SYNC,
				'payload' => $shared_update,
			] );
		}
	}

	/**
	 * Sync awareness state.
	 *
	 * @param WP_Post $post      Post ID
	 * @param WP_User $user      User ID
	 * @param string  $awareness Document State
	 *
	 * @return void
	 */
	private function sync_awareness_state( WP_Post $post, WP_User $user, string $awareness ) {
		// Get user queue array.
		$registered_peers = $this->get_user_queue( $post->ID )->toArray();
		foreach ( $registered_peers as $user_id ) {
			$document_queue = $this->get_awareness_queue( $post->ID, $user_id );
			$document_queue->push( [
				'type' => self::MESSAGE_AWARENESS_SYNC,
				'payload' => $awareness,
			] );
		}
	}

	/**
	 * Register Peers.
	 *
	 * @param WP_Post $post      Post ID
	 * @param WP_User $user      User ID
	 * @param string  $awareness Awareness State
	 *
	 * @return array Response data;
	 */
	private function register_peers( WP_Post $post, WP_User $user, string $awareness ): array {
		/**
		 * Every connection opened, need to create a queue in the cache.
		 */
		$user_queue = $this->get_user_queue( $post->ID );

		if ( ! isset( $user_queue[ $user->ID ] ) ) {
			$user_queue->push( $user->ID );
		}

		$shared_doc = $this->get_shared_doc_state( $post->ID );
		$shared_awareness = $this->get_awareness_state( $post->ID );

		$document_queue = $this->get_document_queue( $post->ID, $user->ID );
		$document_queue->clear();

		if ( ! empty( $shared_doc ) ) {
			$document_queue->push( [
				'type' => self::MESSAGE_SYNC,
				'payload' => $shared_doc,
			] );
		}

		$awareness_queue = $this->get_awareness_queue( $post->ID, $user->ID );
		$awareness_queue->clear();

		if ( ! empty( $shared_awareness ) ) {
			$awareness_queue->push( [
				'type' => self::MESSAGE_AWARENESS_SYNC,
				'payload' => $shared_awareness,
			] );
		}

		return [
			'doc' => $shared_doc,
			'awareness' => $shared_awareness,
		];
	}

	/**
	 * SSE Broadcast Endpoint
	 *
	 * @return void
	 */
	public function sse_broadcast_channel() {
		if ( ! isset( $_REQUEST[ self::SSE_REQUEST_ACTION ] ) ) {
			return;
		}

		ini_set( 'output_buffering', 'off' ); // @codingStandardsIgnoreLine
		ini_set( 'zlib.output_compression', false ); // @codingStandardsIgnoreLine
		header( 'X-Accel-Buffering: no' );
		header( 'Content-Type: text/event-stream' );
		header( 'Cache-Control: no-cache' );

		if ( ! isset( $_GET['nonce'] ) ) {
			echo "event: stop\n";
			echo 'data: Nonce not exist';
			echo "\n\n";
			exit;
		}

		// Check for nonce security.
		$nonce = sanitize_text_field( wp_unslash( $_GET['nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, self::AJAX_NONCE ) ) {
			echo "event: stop\n";
			echo 'data: Nonce not valid';
			echo "\n\n";
			exit;
		}

		if ( ! isset( $_GET['post_id'] ) ) {
			echo "event: stop\n";
			echo 'data: Post ID does not exist';
			echo "\n\n";
			exit;
		}

		$post_id = absint( wp_unslash( $_GET['post_id'] ) );

		$current_user = wp_get_current_user();
		if ( ! $current_user instanceof WP_User ) {
			return;
		}

		$message_queue = $this->get_document_queue( $post_id, $current_user->ID );
		$this->output_messages_in_queue( $message_queue );

		$awareness_queue = $this->get_awareness_queue( $post_id, $current_user->ID );
		$this->output_messages_in_queue( $awareness_queue );

		flush();
		wp_ob_end_flush_all();
		exit;
	}

	/**
	 * Get shared document state
	 *
	 * @param int $post_id Post ID
	 *
	 * @return string
	 */
	private function get_shared_doc_state( int $post_id ): string {
		$cache_key = $this->get_cache_key( $post_id );
		$cache_group = self::APP_SLUG . '-shared-doc';

		$found = false;
		$shared_doc_state = wp_cache_get( $cache_key, $cache_group, false, $found );

		// Ensure to return empty string, since we check this during the yDoc init.
		if ( ! $found ) {
			return '';
		}

		return $shared_doc_state;
	}

	/**
	 * Set shared document state
	 *
	 * @param string $state   State by the origin user
	 * @param int    $post_id Post ID
	 *
	 * @return void
	 */
	private function set_shared_doc_state( string $state, int $post_id ): void {
		$cache_key = $this->get_cache_key( $post_id );
		$cache_group = self::APP_SLUG . '-shared-doc';

		wp_cache_set( $cache_key, $state, $cache_group );
	}

	/**
	 * Get awareness state
	 *
	 * @param int $post_id Post ID
	 *
	 * @return string
	 */
	private function get_awareness_state( int $post_id ): string {
		$cache_key = $this->get_cache_key( $post_id );
		$cache_group = self::APP_SLUG . '-awareness';

		$awareness = wp_cache_get( $cache_key, $cache_group );

		if ( ! $awareness ) {
			return '';
		}

		return $awareness;
	}

	/**
	 * Pop messages from queue and output into stream.
	 *
	 * @param Queue $message_queue Message Queue.
	 * @param int   $amount        The amount of messages to send.
	 *
	 * @return void
	 */
	private function output_messages_in_queue( Queue $message_queue, int $amount = 3 ): void {
		for ( $i = 0; $i < $amount; $i ++ ) {
			$state = $message_queue->pop();

			if ( empty( $state ) ) {
				echo "event: waiting\n";
				echo 'data: No updates…';
				echo "\n\n";
				continue;
			}

			$event_type = '';
			switch ( $state['type'] ) {
				case self::MESSAGE_SYNC:
					$event_type = 'messageSync';
					break;
				case self::MESSAGE_AWARENESS_SYNC:
					$event_type = 'messageAwarenessSync';
					break;
			}

			if ( empty( $event_type ) ) {
				// Skip.
				echo "event: waiting\n";
				echo 'data: No updates…';
			} else {
				echo 'event: ' . esc_html( $event_type ) . "\n";
				echo 'data: ' . wp_json_encode( $state['payload'] );
			}

			echo "\n\n";

		}//end for
	}

	/**
	 * Set awareness state
	 *
	 * @param string $state   State by the origin user
	 * @param int    $post_id Post ID
	 *
	 * @return void
	 */
	private function set_awareness_state( string $state, int $post_id ): void {
		$cache_key = $this->get_cache_key( $post_id );
		$cache_group = self::APP_SLUG . '-awareness';

		wp_cache_set( $cache_key, $state, $cache_group );
	}

	/**
	 * Get original user id who lock the post
	 *
	 * @param int $post_id Post ID
	 *
	 * @return int Maybe user id. 0 if no one editing.
	 */
	private function get_post_lock_origin_user_id( int $post_id ): int {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return 0;
		}

		$lock = get_post_meta( $post->ID, '_edit_lock', true );
		if ( ! $lock ) {
			return 0;
		}

		$lock = explode( ':', $lock );
		$user = $lock[1] ?? get_post_meta( $post->ID, '_edit_last', true );

		if ( ! get_userdata( $user ) ) {
			return 0;
		}

		return $user;
	}

	/**
	 * Get Cache Key
	 *
	 * @param int $post_id Post ID
	 * @param int $user_id User ID
	 *
	 * @return string cache key in md5 format
	 */
	private function get_cache_key( int $post_id, int $user_id = 0 ): string {
		return md5( self::APP_SLUG . '-' . $post_id . '-' . $user_id );
	}

	/**
	 * Get current user queue for given Post ID
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return Queue User Array Queue.
	 */
	private function get_user_queue( int $post_id ): Queue {
		$cache_key = $this->get_cache_key( $post_id );
		$user_cache_group = self::APP_SLUG . '-users';

		return new Queue( $cache_key, $user_cache_group );
	}

	/**
	 * Get documents queue for give Post ID and User ID
	 *
	 * @param int $post_id Post ID.
	 * @param int $user_id User ID.
	 *
	 * @return Queue Message Array Queue.
	 */
	private function get_document_queue( int $post_id, int $user_id ): Queue {
		$message_cache_key = $this->get_cache_key( $post_id, $user_id );
		$message_cache_group = self::APP_SLUG . '-documents';

		return new Queue( $message_cache_key, $message_cache_group );
	}

	/**
	 * Get awareness queue for give Post ID and User ID
	 *
	 * @param int $post_id Post ID.
	 * @param int $user_id User ID.
	 *
	 * @return Queue Awareness Array Queue.
	 */
	private function get_awareness_queue( int $post_id, int $user_id ): Queue {
		$message_cache_key = $this->get_cache_key( $post_id, $user_id );
		$message_cache_group = self::APP_SLUG . '-awareness';

		return new Queue( $message_cache_key, $message_cache_group );
	}

	/**
	 * Apply Document Delta Updates
	 *
	 * @param int    $post_id Post ID.
	 * @param string $update  Update state as encoded string.
	 *
	 * @return string|null
	 */
	private function apply_document_delta_updates( int $post_id, string $update ): ?string {
		$shared_doc = $this->get_shared_doc_state( $post_id );

		if ( ! $shared_doc ) {
			$shared_doc = '';
		}

		try {
			$v8 = $this->init_v8js();
			$v8->shared_doc = $shared_doc;
			$v8->update = $update;
			$v8->executeString( 'const yDoc = initYDoc(PHP.shared_doc);', 'init' );
			$v8->executeString( 'applyUpdate(yDoc.doc, PHP.update);', 'update' );

			$shared_doc = $v8->executeString( 'encode(yDoc.doc);', 'encode' );

			$this->set_shared_doc_state( $shared_doc, $post_id );

		} catch ( Exception $ex ) {
			error_log( $ex->getMessage() );

			return null;
		}

		return $shared_doc;

	}

	/**
	 * Init V8Js Instance
	 *
	 * @return V8Js V8Js instance
	 * @throws Exception Exception
	 */
	private function init_v8js(): V8Js {
		$v8 = new V8Js();
		$server_script = WP_CONTENT_DIR . '/plugins/sc-market-view/packages/collaborative-editing/dist/server.js';

		if ( ! file_exists( $server_script ) ) {
			throw new Exception( 'Server script not found' );
		}

		$server = file_get_contents( WP_CONTENT_DIR . '/plugins/sc-market-view/packages/collaborative-editing/dist/server.js' );

		$server = preg_replace( '/(exports.*?;)/', '', $server );
		$setup = <<<END
// Set up browser-compatible APIs.
var window = this;
var console = {
	warn: print,
	error: print,
	log: ( print => it => print( JSON.stringify( it ) ) )( print )
};
window.setTimeout = window.clearTimeout = window.setInterval = () => {};
// Expose more globals we might want.
var global = global || this,
	self = self || this;
// Remove default top-level APIs.
delete exit;
delete var_dump;
delete require;
delete sleep;
END;

		$v8->executeString( $setup, 'setup' );
		$v8->executeString( $server, 'server' );

		return $v8;
	}
}
