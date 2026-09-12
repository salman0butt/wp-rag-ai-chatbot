import type { PlaygroundControllerState } from './playground-controller';
import { PlaygroundScreen } from './playground-screen';
import type { PlaygroundRequestDraft } from './playground-screen';

export interface PlaygroundPanelProps {
	state: PlaygroundControllerState;
	onSubmit?: ( request: PlaygroundRequestDraft ) => void;
}

export const PlaygroundPanel = ( {
	state,
	onSubmit,
}: PlaygroundPanelProps ): unknown => {
	const createElement = window.wp.element.createElement;
	let screen: unknown;

	if ( state.status === 'success' ) {
		screen = PlaygroundScreen( { result: state.result, onSubmit } );
	} else if ( state.status === 'error' ) {
		screen = PlaygroundScreen( { errorCode: state.errorCode, onSubmit } );
	} else {
		screen = PlaygroundScreen( { onSubmit } );
	}

	if ( state.status !== 'loading' ) {
		return screen;
	}

	return createElement(
		'div',
		{ 'data-playground-panel': true },
		screen,
		createElement(
			'p',
			{
				role: 'status',
				'aria-live': 'polite',
				'data-playground-status': 'loading',
			},
			'Running the production retrieval pipeline…'
		)
	);
};
