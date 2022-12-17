/**
 * External dependencies
 */
import { isEqual } from 'lodash';
import * as yjs from 'yjs';

/** @typedef { import("yjs").Map } YMap */

// Code borrowed from https://github.com/WordPress/gutenberg/pull/23129

/**
 * Simple diff between array a and b.
 *
 * Returns information for splicing array
 * `a` into array `b`, by swapping the
 * minimum slice of disagreement.
 *
 * @param {Array} a Array A
 * @param {Array} b Array B
 * @returns {object} Object of differences
 */
export function simpleDiff( a, b ) {
	let left = 0;
	let right = 0;

	while ( left < a.length && left < b.length && a[ left ] === b[ left ] ){
		left++;
	}

	if ( left !== a.length || left !== b.length ) {
		while (
			right + left < a.length &&
			right + left < b.length &&
			a[ a.length - right - 1 ] === b[ b.length - right - 1 ]
		)
			right++;
	}

	return {
		index: left,
		remove: a.length - left - right,
		insert: b.slice( left, b.length - right ),
	};
}

/**
 * Updates the shared block data types with the local block changes.
 *
 * @param {YMap} yDocBlocks Y.Map Data Type
 * @param {Array} blocks Array of blocks
 * @param {string} clientId Origin Client ID
 */
export default function setYDocBlocks( yDocBlocks, blocks, clientId = '' ) {
	let order = yDocBlocks.get( 'order' );
	if ( ! order.has( clientId ) ) order.set( clientId, new yjs.Array() );
	order = order.get( clientId );
	const byClientId = yDocBlocks.get( 'byClientId' );

	const currentOrder = order.toArray();
	const orderDiff = simpleDiff(
		currentOrder,
		blocks.map( block => block.clientId ),
	);

	currentOrder
		.slice( orderDiff.index, orderDiff.remove )
		.forEach(
			_clientId =>
				! orderDiff.insert.includes( _clientId ) &&
				byClientId.delete( _clientId ),
		);
	order.delete( orderDiff.index, orderDiff.remove );
	order.insert( orderDiff.index, orderDiff.insert );

	for ( const _block of blocks ) {
		const { innerBlocks, ...block } = _block;

		if (
			! byClientId.has( block.clientId ) ||
			! isEqual( byClientId.get( block.clientId ), block )
		) {
			byClientId.set( block.clientId, block );
		}

		setYDocBlocks( yDocBlocks, innerBlocks, block.clientId );
	}
}
