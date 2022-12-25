/**
 * Block Editor Binding Class.
 */

/**
 * External dependencies
 */
import ColorHash from 'color-hash';
import debounce from 'lodash.debounce';
import isEmpty from 'lodash.isempty';
import * as yjs from 'yjs';

import { dispatch, select, subscribe } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { STORE_NAME as SelectionStore } from '../components/add-block-selections';
import setYDocBlocks from '../helpers/set-y-doc-blocks';
import yDocBlocksToArray from '../helpers/y-doc-blocks-to-array';

/** @typedef { import("yjs").Doc } YDoc */
/** @typedef { import("yjs").YMapEvent } YMapEvent */
/** @typedef { import("yjs").Transaction } Transaction */
/** @typedef { import("y-protocols/awareness").Awareness } Awareness */

export class BlockEditorBinding {
	/**
	 * Constructor
	 *
	 * @param {YDoc} YDoc YDoc document.
	 * @param {Awareness} awareness Awareness object
	 */
	constructor( YDoc, awareness ) {
		const { getCurrentUser } = select( 'core' );

		const {
			getCurrentPostId,
			getCurrentPostType,
		} = select( 'core/editor' );

		const {
			getBlocks,
			getSelectedBlock,
			isTyping,
		} = select( 'core/block-editor' );

		const { editEntityRecord } = dispatch( 'core' );

		const { setState: setAddBlockSelectionsState } = dispatch( SelectionStore );
		// const { removeBlock, updateBlock } = dispatch( 'core/block-editor' );

		this.postID = getCurrentPostId();
		this.postType = getCurrentPostType();
		this.yDoc = YDoc;
		this.yDocBlocks = this.yDoc.getMap( 'blocks' );
		this.yDocBlocks.set( 'order', new yjs.Map() );
		this.yDocBlocks.set( 'byClientId', new yjs.Map() );
		this.awareness = awareness;
		this.clientId = this.awareness.clientID;

		/**
		 * Set blocks back to state
		 *
		 * Convert the yDocBlocks from YDoc Map back to array of blocks before set this
		 * to block state. And only apply this when the changes come from remotely.
		 *
		 * @param {Array<YMapEvent>} events Array of Y.MapEvent consists of add, update and delete
		 * @param {Transaction} transaction Payload
		 */
		this.maybeSetBlocks = ( events, transaction ) => {
			if ( transaction.origin !== this.clientId ) {
				const newBlocks = yDocBlocksToArray(
					this.yDocBlocks,
				);

				const edits = { blocks: newBlocks };
				editEntityRecord( 'postType', this.postType, this.postID, edits );

				// @TODO: Parked this idea of delta updates till solve the server shared document.
				/*const keysChanged = [];
				const keysDeleted = [];
				console.log( 'events', events ); // eslint-disable-line no-console
				events.forEach( event => {
					event.changes.keys.forEach( ( change, key ) => {
						console.log( 'event > change', change ); // eslint-disable-line no-console
						if ( change.action === 'delete' ) {
							keysDeleted.push( key );
						} else {
							keysChanged.push( key );
						}
					} );
				} );

				console.log( 'keysChanged', keysChanged ); // eslint-disable-line no-console
				console.log( 'keysDeleted', keysDeleted ); // eslint-disable-line no-console
				// If more than 1 key changes then it's synced the whole blocks. 0 mean a block could be deleted.
				if ( keysChanged.length > 1 ) {
					const edits = { blocks: newBlocks };
					editEntityRecord( 'postType', this.postType, this.postID, edits );
				} else {
					// Only replace a block.
					const clientId = keysChanged.pop();
					const blockChanged = getBlockByClientId( newBlocks, clientId );
					if ( blockChanged.length > 0 ) {
						updateBlock( clientId, blockChanged.pop() );
					}
				}

				keysDeleted.forEach( key => {
					removeBlock( key );
				} );*/

			}
		};

		/**
		 * Set new blocks array to YDoc State
		 *
		 * @param {Array} newBlocks New Blocks to store
		 */
		this._setBlocks = newBlocks => {
			this.yDoc.transact( () => {
				setYDocBlocks( this.yDocBlocks, newBlocks  );
			}, this.clientId );
		};

		/**
		 * Set new blocks array to YDoc State
		 *
		 * @param {object} self Object of currentUser
		 */
		this._setSelf = self => {
			if ( isEmpty( this.awareness.getLocalState() ) ) {
				this.awareness.setLocalStateField( 'user', {
					avatar: self.avatar_urls,
					color: new ColorHash().hex( self.name || 'rand' ),
					id: self.id,
					name: self.name,
				} );
			}

		};

		this._awarenessChange = ( { added, removed, updated } ) => {
			const awarenessState = this.awareness.getStates();

			// Convert Map object back to json object.
			const state = {
				peers: [ ...awarenessState ].map( ( [ peerId, state ] ) => state ),
			};
			setAddBlockSelectionsState( state );

			added.forEach( id => {
				// @TODO: handler for this changes.
				// console.log( 'Awareness Change detected: Added: ', awarenessState.get( id ) ); // eslint-disable-line no-console
			} );
			updated.forEach( id => {
				// @TODO: handler for this changes.
				// console.log( 'Awareness Change detected: Updated: ', awarenessState.get( id ) ); // eslint-disable-line no-console
			} );
			removed.forEach( id => {
				// @TODO: handler when another user stop the collaborating.
				// console.log( 'Awareness Change detected: Removed: ', id.toString() ); // eslint-disable-line no-console
			} );
		};

		this._selectedBlock = currentEditedBlock => {
			this.awareness.setLocalStateField( 'selectedBlock', ( currentEditedBlock === null ) ? null : currentEditedBlock.clientId );
		};

		// Observe awareness change.
		this.awareness.on( 'change', this._awarenessChange );

		// Observe Map changes and render back to block.
		this.yDocBlocks.observeDeep( this.maybeSetBlocks );

		// Initialize first block state.
		this._setBlocks( getBlocks() );

		// Subscribe to Gutenberg data state.
		subscribe(
			debounce(
				() => {
					this._setSelf( getCurrentUser() );
					this._selectedBlock( getSelectedBlock() );

					// Send data when stop typing.
					if ( ! isTyping() ){
						this._setBlocks( getBlocks() );
					}
				},
				250,
			),
		);
	}

	destroy() {
		this.awareness.off( 'change', this._awarenessChange );
		this.yDocBlocks.unobserveDeep( this.maybeSetBlocks );
	}
}
