export const pluginIdentity = Object.freeze( {
	slug: 'wp-rag-ai-chatbot',
	version: '0.1.0-dev',
} as const );

export interface AdminApiRequestOptions {
	method?: string;
	body?: unknown;
}

export interface AdminApiClientConfig {
	baseUrl: string;
	nonce: string;
	fetcher: typeof fetch;
}

export interface AdminApiClient {
	request: < T >(
		path: string,
		options?: AdminApiRequestOptions
	) => Promise< T >;
}

export const createAdminApiClient = (
	config: AdminApiClientConfig
): AdminApiClient => {
	const baseUrl = config.baseUrl.replace( /\/+$/, '' );

	return {
		async request< T >(
			path: string,
			options: AdminApiRequestOptions = {}
		): Promise< T > {
			const normalizedPath = path.startsWith( '/' ) ? path : `/${ path }`;
			const request: RequestInit = {
				credentials: 'same-origin',
				headers: {
					Accept: 'application/json',
					'Content-Type': 'application/json',
					'X-WP-Nonce': config.nonce,
				},
				method: options.method ?? 'GET',
			};

			if ( options.body !== undefined ) {
				request.body = JSON.stringify( options.body );
			}

			const response = await config.fetcher(
				`${ baseUrl }${ normalizedPath }`,
				request
			);

			return ( await response.json() ) as T;
		},
	};
};
