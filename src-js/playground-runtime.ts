import type { PlaygroundApiClient } from './playground-api';
import { createPlaygroundApi } from './playground-api';
import type {
	PlaygroundController,
	PlaygroundControllerState,
} from './playground-controller';
import { createPlaygroundController } from './playground-controller';

let activeNavigationInvalidationHandler: ( () => void ) | null = null;

export const createPlaygroundRuntime = (
	client: PlaygroundApiClient,
	onChange: ( state: PlaygroundControllerState ) => void
): PlaygroundController => {
	const controller = createPlaygroundController(
		createPlaygroundApi( client ),
		onChange
	);

	if ( activeNavigationInvalidationHandler !== null ) {
		window.removeEventListener(
			'hashchange',
			activeNavigationInvalidationHandler
		);
	}

	activeNavigationInvalidationHandler = () => controller.invalidate();
	window.addEventListener(
		'hashchange',
		activeNavigationInvalidationHandler
	);

	return controller;
};
