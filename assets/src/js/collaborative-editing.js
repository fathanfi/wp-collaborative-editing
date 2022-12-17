/* global scCollaborativeEditing, wp */

/**
 * External dependencies
 */
import * as yjs from 'yjs';

import { BlockEditorBinding } from './libs/block-editor-binding';
import SSEProvider from './providers/sse-provider';

import './scss/index.scss';

/**
 * Init Collaborative Editing
 */
export default async function initCollaborativeEditing() {
	const {
		ajax_url: ajaxUrl,
		app_nonce: appNonce,
		message_update_action: msgUpdateAction,
		post_id: postID,
		slug: postSlug,
		sse_url: sseURL,
	} = scCollaborativeEditing;

	const yDoc = new yjs.Doc();

	const args = {
		ajaxAction: msgUpdateAction,
		ajaxUrl: ajaxUrl,
		ajaxNonce: appNonce,
		postId: postID,
		postSlug: postSlug,
	};

	const provider = new SSEProvider( sseURL, yDoc, args );
	const { awareness } = provider;

	new BlockEditorBinding( yDoc, wp.data, awareness );

	provider.on( 'status', event => {
		console.log( 'provider status', event ); // eslint-disable-line no-console
	} );
}
