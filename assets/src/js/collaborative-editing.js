/* global scCollaborativeEditing, wp */

/**
 * External dependencies
 */
import * as yjs from 'yjs';

import { select, subscribe } from '@wordpress/data';

import { BlockEditorBinding } from './libs/block-editor-binding';

import '../scss/index.scss';

/**
 * Init Collaborative Editing
 */
export default async function initCollaborativeEditing() {
	const {
		connProvider,
	} = scCollaborativeEditing;

	const yDoc = new yjs.Doc();

	const allowedConnectionProviders = [ 'websocket', 'webrtc' ];

	if ( ! allowedConnectionProviders.includes( connProvider ) ) {
		console.error( 'connection provider not allowed' ); // eslint-disable-line no-console
		return;
	}

	let provider = null;
	switch ( connProvider ) {
		case 'webrtc': {
			const {
				roomName,
				secret,
				signalingServerUrls,
			} = scCollaborativeEditing;

			const { WebrtcProvider } = await import( 'y-webrtc' );
			provider = new WebrtcProvider(
				roomName,
				yDoc,
				{
					password: secret,
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

			const { WebsocketProvider } = await import( 'y-websocket' );
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
	// const { select, subscribe } = wp.data;
	const closeListener = subscribe( () => {
		const postID = select( 'core/editor' ).getCurrentPostId();
		const currentUser = select( 'core' ).getCurrentUser();

		if ( postID && currentUser && provider ) {
			new BlockEditorBinding( yDoc, awareness );
			closeListener();
		}
	} );

	if ( provider ) {
		provider.on( 'status', event => {
			console.log( 'provider status', event ); // eslint-disable-line no-console
		} );
	}

}
