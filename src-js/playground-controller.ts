import type { PlaygroundApi } from './playground-api';
import type {
	PlaygroundRequestDraft,
	PlaygroundResult,
} from './playground-screen';

export type PlaygroundControllerState =
	| { status: 'idle' }
	| { status: 'loading' }
	| { status: 'success'; result: PlaygroundResult }
	| { status: 'error'; errorCode: string };

export interface PlaygroundController {
	submit: ( request: PlaygroundRequestDraft ) => Promise< void >;
}

const errorCodeFrom = ( error: unknown ): string => {
	if (
		typeof error === 'object' &&
		error !== null &&
		'code' in error &&
		typeof error.code === 'string'
	) {
		return error.code;
	}

	return 'admin_request_failed';
};

export const createPlaygroundController = (
	api: PlaygroundApi,
	onChange: ( state: PlaygroundControllerState ) => void
): PlaygroundController => ( {
	async submit( request: PlaygroundRequestDraft ): Promise< void > {
		onChange( { status: 'loading' } );

		try {
			const result = await api.run( request );
			onChange( { status: 'success', result } );
		} catch ( error ) {
			onChange( {
				status: 'error',
				errorCode: errorCodeFrom( error ),
			} );
		}
	},
} );
