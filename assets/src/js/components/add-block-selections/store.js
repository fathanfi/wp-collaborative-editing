import { registerStore } from '@wordpress/data';

export const STORE_NAME = 'hmce/add-block-selections';

const DEFAULT_STATE = {};

/**
 * Reducer
 *
 * @param {object} state The state.
 * @param {object} action The action.
 * @returns {object} New state object
 */
function reducer( state = DEFAULT_STATE, action ) {
	switch ( action.type ) {
		case 'SET_ADD_BLOCK_SELECTIONS_STATE': {
			return {
				...state,
				...action.state,
			};
		}
		default:
			return state;
	}
}

const actions = {
	setState( state ) {
		return {
			type: 'SET_ADD_BLOCK_SELECTIONS_STATE',
			state,
		};
	},
};

const selectors = {
	getState( state ) {
		return state;
	},
};

export const storeConfig = {
	reducer,
	actions,
	selectors,
};

export default registerStore( STORE_NAME, {
	...storeConfig,
} );
