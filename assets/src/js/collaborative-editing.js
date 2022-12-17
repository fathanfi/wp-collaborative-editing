/* global scCollaborativeEditing, wp */

/**
 * External dependencies
 */
import { WebrtcProvider } from 'y-webrtc';
import { WebsocketProvider } from 'y-websocket';
import * as yjs from 'yjs';

import { BlockEditorBinding } from './libs/block-editor-binding';
import SSEProvider from './providers/sse-provider';

import '../scss/index.scss';

/**
 * Init Collaborative Editing
 */
export default async function initCollaborativeEditing() {
	const {
		connProvider,
		post_id: postID,
		slug: postSlug,
	} = scCollaborativeEditing;

	const yDoc = new yjs.Doc();

	const allowedConnectionProviders = [ 'sse', 'websocket', 'webrtc' ];

	if ( ! allowedConnectionProviders.includes( connProvider ) ) {
		console.error( 'connection provider not allowed' ); // eslint-disable-line no-console
		return;
	}

	let provider = null;
	switch ( connProvider ) {
		case 'sse': {
			const {
				ajax_url: ajaxUrl,
				app_nonce: appNonce,
				message_update_action: msgUpdateAction,
				sse_url: sseURL,
			} = scCollaborativeEditing;

			const args = {
				ajaxAction: msgUpdateAction,
				ajaxUrl: ajaxUrl,
				ajaxNonce: appNonce,
				postId: postID,
				postSlug: postSlug,
			};
			provider = new SSEProvider( sseURL, yDoc, args );

			break;
		}

		case 'webrtc': {
			const {
				roomName,
				signalingServerUrls,
			} = scCollaborativeEditing;

			provider = new WebrtcProvider(
				roomName,
				yDoc,
				{
					password: 'optional-room-password',
					signaling: signalingServerUrls,
				} );

			break;
		}

		default:
		case 'websocket': {
			const {
				roomName,
				wsServerUrl,
			} = scCollaborativeEditing;

			provider = new WebsocketProvider(
				wsServerUrl,
				roomName,
				yDoc,
			);

			break;
		}

	}

	const { awareness } = provider;

	// Prevent race condition, wait for post and user object initialized.
	const { select, subscribe } = wp.data;
	const closeListener = subscribe( () => {
		const postID = select( 'core/editor' ).getCurrentPostId();
		const currentUser = select( 'core' ).getCurrentUser();

		if ( postID && currentUser ) {
			new BlockEditorBinding( yDoc, wp.data, awareness );
			closeListener();
		}
	} );

	provider.on( 'status', event => {
		console.log( 'provider status', event ); // eslint-disable-line no-console
	} );

}
