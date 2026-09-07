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

export class AdminApiError extends Error {
	public readonly code: string;
	public readonly status: number;

	public constructor( status: number, code: string ) {
		super( 'The admin request could not be completed.' );
		this.name = 'AdminApiError';
		this.code = code;
		this.status = status;
	}
}

const getErrorCode = ( payload: unknown ): string => {
	if (
		typeof payload === 'object' &&
		payload !== null &&
		'code' in payload &&
		typeof payload.code === 'string'
	) {
		return payload.code;
	}

	return 'admin_request_failed';
};

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
			const payload = ( await response.json() ) as unknown;

			if ( ! response.ok ) {
				throw new AdminApiError(
					response.status,
					getErrorCode( payload )
				);
			}

			return payload as T;
		},
	};
};
