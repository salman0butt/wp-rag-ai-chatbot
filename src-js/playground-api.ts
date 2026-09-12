import type {
	PlaygroundRequestDraft,
	PlaygroundResult,
} from './playground-screen';

export interface PlaygroundApiClient {
	request: < T >(
		path: string,
		options?: { method?: string; body?: unknown }
	) => Promise< T >;
}

export interface PlaygroundApi {
	run: ( request: PlaygroundRequestDraft ) => Promise< PlaygroundResult >;
}

export const createPlaygroundApi = (
	client: PlaygroundApiClient
): PlaygroundApi => ( {
	run: ( request: PlaygroundRequestDraft ) =>
		client.request< PlaygroundResult >( '/admin/debug/playground', {
			method: 'POST',
			body: request,
		} ),
} );
