/** @typedef { import("yjs").Map } YMap */

/**
 * Converts the shared block data types into a renderable block list.
 *
 * Code borrowed from https://github.com/WordPress/gutenberg/pull/23129
 *
 * @param {YMap} yDocBlocks Y.Map Data Type
 * @param {string} clientId origin client ID
 * @returns {Array} Array of blocks
 */
export default function yDocBlocksToArray( yDocBlocks, clientId = '' ) {
	let order = yDocBlocks.get( 'order' );
	order = order.get( clientId )?.toArray();
	if ( ! order ) return [];
	const byClientId = yDocBlocks.get( 'byClientId' );

	return order.map( _clientId => ( {
		...byClientId.get( _clientId ),
		innerBlocks: yDocBlocksToArray( yDocBlocks, _clientId ),
	} ) );
}
