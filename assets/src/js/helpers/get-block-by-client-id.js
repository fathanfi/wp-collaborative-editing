
/**
 * Get block by client ID
 *
 * @param {Array} blocks Array of blocks
 * @param {string} clientId client ID
 * @param {Array} found Found block
 * @returns {Array} Found block, empty if not found.
 */
export default function getBlockByClientId( blocks, clientId, found = [] ) {

	// @TODO: Park this idea, as this function needed when updating some of the blocks when receiving delta updates.
	/*const foundBlock = blocks.filter( block => {
		return block.clientId === clientId;
	} );

	if ( foundBlock.length > 0 ) {
		found.push( foundBlock.pop() );
		return found;
	}

	blocks.every( block => {
		return getBlockByClientId( block.innerBlocks, clientId, found );
	} );*/

	return found;
}
