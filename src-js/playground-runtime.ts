import type { PlaygroundApiClient } from './playground-api';
import { createPlaygroundApi } from './playground-api';
import type {
	PlaygroundController,
	PlaygroundControllerState,
} from './playground-controller';
import { createPlaygroundController } from './playground-controller';

export const createPlaygroundRuntime = (
	client: PlaygroundApiClient,
	onChange: ( state: PlaygroundControllerState ) => void
): PlaygroundController =>
	createPlaygroundController( createPlaygroundApi( client ), onChange );
