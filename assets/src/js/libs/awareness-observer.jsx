/* global wp */
import classnames from 'classnames';
import isEmpty from 'lodash.isempty';
import PropTypes from 'prop-types';

/**
 * Awareness Observer
 */
export default function AwarenessObserver() {
	const { createHigherOrderComponent } = wp.compose;
	const { registerStore, useSelect } = wp.data;
	const { addFilter } = wp.hooks;

	registerStore( 'collaborative-editing/add-block-selections', {
		reducer( state = {}, action ) {
			return action.type === 'SET_ADD_BLOCK_SELECTIONS_STATE'
				? action.state
				: state;
		},
		selectors: {
			getState( state ) {
				return state;
			},
		},
		actions: {
			setState( state ) {
				return {
					type: 'SET_ADD_BLOCK_SELECTIONS_STATE',
					state,
				};
			},
		},
	} );

	const addBlockSelections = createHigherOrderComponent(
		OriginalComponent => {
			const withBlockSelections = props => {
				const component = <OriginalComponent { ...props } />;

				// eslint-disable-next-line react-hooks/rules-of-hooks
				const state = useSelect(
					select => select(
						'collaborative-editing/add-block-selections',
					).getState(),
					[ props.clientId ],
				);

				if ( isEmpty( state ) || isEmpty( state.peers ) ) {
					return component;
				}

				const activePeers = state.peers.filter( peer => {
					return ! isEmpty( peer ) && peer.selectedBlock && peer.selectedBlock === props.clientId;
				} );

				if ( activePeers.length < 1 ) {
					return component;
				}

				const activePeer = activePeers.pop();

				return (
					<div
						className={ classnames( 'collaborative-editing-add-block-selections__block', 'is-peer-selected' ) }
						style={ {
							outlineColor: activePeer.user.color,
						} }
					>
						<div
							className="collaborative-editing-add-block-selections__block-peer-names"
							style={ {
								backgroundColor: activePeer.user.color,
							} }>{ activePeer.user.name }</div>
						{ component }
					</div>
				);
			};

			withBlockSelections.propTypes = {
				clientId: PropTypes.string.isRequired,
			};
			withBlockSelections.displayName = 'withBlockSelections';

			return withBlockSelections;
		},
		'addBlockSelections',
	);

	addFilter(
		'editor.BlockListBlock',
		'collaborative-editing/awareness-observer',
		addBlockSelections,
	);
}
