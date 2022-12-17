import { fromUint8Array, toUint8Array } from 'js-base64';
import * as mutex from 'lib0/mutex';
import { Observable } from 'lib0/observable';
import isEmpty from 'lodash.isempty';
import * as awarenessProtocol from 'y-protocols/awareness';
import * as yjs from 'yjs';

/** @typedef { import("yjs").Doc } YDoc */
/** @typedef { import("yjs").YMapEvent } YMapEvent */
/** @typedef { import("yjs").Transaction } Transaction */
/** @typedef { import("y-protocols/awareness").Awareness } Awareness */

const messageSync = 0;
const messageAwarenessSync = 1;
const messageClientRegister = 2;

class SSEProvider extends Observable {

	/**
	 * Constructor
	 *
	 * @param {string} serverUrl SSE Url to listen to.
	 * @param {yjs.Doc} ydoc YDoc Document.
	 * @param {object} args Other configs.
	 */
	constructor( serverUrl, ydoc, args ) {
		super();

		this.ajaxAction = args.ajaxAction;
		this.ajaxUrl = args.ajaxUrl;
		this.ajaxNonce = args.ajaxNonce;
		this.postId = args.postId;
		this.sseUrl = serverUrl;
		this.sseListener = null;
		this.synced = false;
		this.mux = mutex.createMutex();
		this.yDoc = ydoc;

		this.awareness = new awarenessProtocol.Awareness( ydoc );
		this.clientID = this.awareness.clientID;

		/**
		 * YDoc Document update handler
		 *
		 * @param {Uint8Array} update Encoded Document update as Uint8Array
		 * @param {any} origin Origin of the changes
		 * @param {yjs.Doc} doc YDoc Document
		 * @param {yjs.Transaction} transaction YDoc Transaction
		 * @returns {Promise<void>}
		 * @private
		 */
		this._docUpdateHandler = async ( update, origin, doc, transaction ) => {
			const provider = this;

			if ( origin === this.clientID ) {
				await this.sendBlockState( doc );
				// await this.sendDeltaState( update );
			} else {
				// this update was produced by remotely.
				provider.emit( 'update', [ update, origin, doc, transaction ] );
			}
		};

		/**
		 * Awareness update handler
		 *
		 * @param {object} Awareness maps
		 * @param {Array} Awareness.added Added list to awareness state.
		 * @param {Array} Awareness.updated Updated list to awareness state.
		 * @param {Array} Awareness.removed Removed list to awareness state.
		 * @param {string} origin Awareness update origin
		 * @returns {Promise<void>}
		 * @private
		 */
		this._awarenessUpdateHandler = async ( { added, updated, removed }, origin ) => {
			if ( this.clientID ){
				// Only send if current user awareness state.
				await this.sendAwarenessState( this.awareness, [ this.clientID ] );
			}
		};

		/**
		 * Get Awareness State
		 *
		 * @returns {Uint8Array} Encoded awareness state
		 */
		this.getAwarenessState = () => {
			return awarenessProtocol.encodeAwarenessUpdate( this.awareness, Array.from( this.awareness.getStates().keys() ) );
		};

		/**
		 * Send data to server
		 *
		 * @param {object} Payload Payload object
		 * @param {number} Payload.type messageSync or messageAwarenessSync flag
		 * @param {string} Payload.data Base64 encoded string
		 * @returns {Promise<Response>} Response from server
		 */
		this.send = async ( { type, data } ) => {
			const url = new URL( this.ajaxUrl );

			const formData = new FormData();

			formData.append( 'action', this.ajaxAction );
			formData.append( 'nonce', this.ajaxNonce );
			formData.append( 'post_id', this.postId );
			formData.append( 'client_id', this.clientID );
			formData.append( 'type', type );
			formData.append( 'data', data );

			return await fetch( url.toString(), {
				method: 'POST',
				body: formData,
			} );
		};

		/**
		 * Send Block Vector to server
		 *
		 * @param {yjs.Doc} doc YDoc Document Update
		 * @returns {Promise<Response>} Response from server
		 */
		this.sendBlockVector = async doc => {
			const payload = {
				type: messageSync,
				data: fromUint8Array( yjs.encodeStateVector( doc ) ),
			};

			return await this.send( payload );
		};

		/**
		 * Send Block State to server
		 *
		 * @param {yjs.Doc} doc YDoc Document Update
		 * @returns {Promise<Response>} Response from server
		 */
		this.sendBlockState = async doc => {
			const payload = {
				type: messageSync,
				data: fromUint8Array( yjs.encodeStateAsUpdate( doc ) ),
			};

			return await this.send( payload );
		};

		/**
		 * Send Delta Update to server
		 *
		 * @param {Uint8Array} update YDoc Document Update
		 * @returns {Promise<Response>} Response from server
		 */
		this.sendDeltaState = async update => {
			const payload = {
				type: messageSync,
				data: fromUint8Array( update ),
			};

			return await this.send( payload );
		};

		/**
		 * Send Awareness State to server
		 *
		 * @param {Awareness} awareness Awareness shared data type
		 * @param {Array} changedClients Array of change client ID
		 * @returns {Promise<Response>} Response from server
		 */
		this.sendAwarenessState = async ( awareness, changedClients = [ this.clientID ] ) => {
			const payload = {
				type: messageAwarenessSync,
				data: fromUint8Array( awarenessProtocol.encodeAwarenessUpdate( awareness, changedClients ) ),
			};

			return await this.send( payload );
		};

		/**
		 * Register self to server
		 *
		 * @param {Awareness} awareness Awareness shared data type
		 * @param {Array} changedClients Array of change client ID
		 * @returns {Promise<Response>} Response from server
		 */
		this.registerSelf = async ( awareness, changedClients = [ this.clientID ] ) => {
			const payload = {
				type: messageClientRegister,
				data: fromUint8Array( awarenessProtocol.encodeAwarenessUpdate( awareness, changedClients ) ),
			};

			return await this.send( payload );
		};

		// Send initial connection.
		this.connect();
	}

	/**
	 * Start the SSE Listener
	 *
	 * @private
	 */
	_startSSEListener() {
		const provider = this;
		this.sseListener = new EventSource( this.sseUrl, { withCredentials: true } );

		this.sseListener.addEventListener( 'messageSync', function ( event ) {
			if ( ! isEmpty( event.data ) ) {
				const update = toUint8Array( JSON.parse( event.data ) );
				yjs.applyUpdate( provider.yDoc, update, provider ); // the third parameter sets the transaction-origin
			}
		} );

		this.sseListener.addEventListener( 'messageAwarenessSync', function ( event ) {
			if ( ! isEmpty( event.data ) ) {
				const update = toUint8Array( JSON.parse( event.data ) );
				awarenessProtocol.applyAwarenessUpdate( provider.awareness, update,
					provider );
			}
		} );
	}

	/**
	 * Starting connection
	 */
	connect() {
		this.mux( async () => {
			try {
				// register self
				const response = await this.registerSelf( this.awareness, [ this.clientID ] );
				const responseJson = await response.json();

				if ( responseJson.success ){
					// Set initial shared doc.
					if ( isEmpty( responseJson.data.doc ) ) {
						await this.sendBlockState( this.yDoc );
					} else {
						const update = toUint8Array( responseJson.data.doc );
						yjs.applyUpdate( this.yDoc, update, this );
					}

					if ( ! isEmpty( responseJson.data.awareness ) ) {
						const awareness = toUint8Array( responseJson.data.awareness );
						awarenessProtocol.applyAwarenessUpdate( this.awareness, awareness, this );
					}

					// Listen to yDoc update event.
					this.yDoc.on( 'update', this._docUpdateHandler );

					// Listen to awareness update event.
					this.awareness.on( 'update', this._awarenessUpdateHandler );

					// Listen to SSE.
					this._startSSEListener();

					this.emit( 'status', [ { status: 'connected' } ] );
				}

			} catch ( e ) {
				this.emit( 'status', [ { status: e.message } ] );
			}

		} );
	}

	disconnect() {
		// @TODO: Send disconnect awareness signal to server.
		console.log( 'Run disconnect' ); // eslint-disable-line no-console
	}

	/**
	 * Destructor
	 */
	destroy() {
		this.disconnect();
		this.yDoc.off( 'update', this._docUpdateHandler );
		this.awareness.off( 'update', this._awarenessUpdateHandler );
		this.sseListener.close();
		this.awareness.destroy();
		super.destroy();
	}
}

export default SSEProvider;
