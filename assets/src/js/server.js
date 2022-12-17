import { fromUint8Array, toUint8Array } from 'js-base64';
import * as yjs from 'yjs';

/**
 * Apply Update to yDoc instance
 *
 * @param {yjs.Doc} yDoc yjs.Doc instance
 * @param {string} update Document update
 * @returns {yjs.Doc} yjs.Doc instance
 */
function applyUpdate( yDoc, update ) {
	const state = toUint8Array( update );
	yjs.applyUpdate( yDoc, state );
	return yDoc;
}

/**
 * Initialize yDoc and Awareness object
 *
 * @param {string} doc Stored document as base64 string.
 * @returns {object} yjs.Doc instance
 */
function initYDoc( doc ) {
	const yDoc = new yjs.Doc();

	if ( doc !== '' ) {
		yjs.applyUpdate( yDoc, toUint8Array( doc ) );
	}

	return {
		doc: yDoc,
	};
}

/**
 * Encode back yDoc state to base64 string
 *
 * @param {yjs.Doc} yDoc yDoc yjs.Doc instance
 * @returns {string} State as base64 encoded string.
 */
function encode( yDoc ){
	return fromUint8Array( yjs.encodeStateAsUpdate( yDoc ) );
}

export {
	applyUpdate,
	encode,
	initYDoc,
};
