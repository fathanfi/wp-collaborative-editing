/**
 * @typedef { import("react").ReactElement } ReactElement
 */

/**
 * External dependencies
 */
import classnames from 'classnames';
import isEmpty from 'lodash.isempty';
import PropTypes from 'prop-types';

/**
 * WordPress dependencies
 */
import { createHigherOrderComponent } from '@wordpress/compose';
import { useSelect } from '@wordpress/data';
import { addFilter } from '@wordpress/hooks';

import { STORE_NAME as SelectionStore } from './store';
/**
 * Add Block Selections Component
 *
 * @returns {ReactElement} Higher Order Component.
 */
export default function addBlockSelections() {
	return createHigherOrderComponent(
		OriginalComponent => {
			const withBlockSelections = props => {
				const component = <OriginalComponent { ...props } />;

				// eslint-disable-next-line react-hooks/rules-of-hooks
				const state = useSelect(
					select => select(
						SelectionStore,
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
}

addFilter(
	'editor.BlockListBlock',
	'hmce/add-block-selections',
	addBlockSelections(),
);
