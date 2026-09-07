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

export type AdminShellState = 'loading' | 'empty' | 'error' | 'ready';
export type AdminScreen = 'onboarding' | 'bots' | 'providers';

export interface AdminShellProps {
	state: AdminShellState;
	screen?: AdminScreen;
}

type ElementFactory = (
	type: string,
	props: Record< string, unknown > | null,
	...children: unknown[]
) => unknown;

type ElementRenderer = ( element: unknown, root: Element ) => void;

interface AdminBootConfig {
	plugin: string;
	restBase: string;
	nonce: string;
}

interface AdminOnboardingReadiness {
	ready: boolean;
	next_step: 'provider' | 'model' | 'first_bot' | 'complete';
}

declare global {
	interface Window {
		wp: {
			element: {
				createElement: ElementFactory;
				render: ElementRenderer;
			};
		};
		wpRagAiChatbotAdminConfig?: AdminBootConfig;
	}
}

const ADMIN_SCREENS: ReadonlyArray< {
	screen: AdminScreen;
	label: string;
} > = [
	{ screen: 'onboarding', label: 'Onboarding' },
	{ screen: 'bots', label: 'Bots' },
	{ screen: 'providers', label: 'Providers' },
];

let activeHashChangeHandler: ( () => void ) | null = null;

export const resolveAdminScreen = ( hash: string ): AdminScreen => {
	const candidate = hash.replace( /^#\/?/, '' ).split( '/' )[ 0 ];
	const screen = ADMIN_SCREENS.find( ( item ) => item.screen === candidate );

	return screen?.screen ?? 'onboarding';
};

export const AdminShell = ( {
	state,
	screen = 'onboarding',
}: AdminShellProps ): unknown => {
	const createElement = window.wp.element.createElement;

	if ( state === 'loading' ) {
		return createElement(
			'div',
			{ role: 'status', 'aria-live': 'polite' },
			'Loading administration data…'
		);
	}

	if ( state === 'error' ) {
		return createElement(
			'div',
			{ role: 'alert' },
			'Administration data could not be loaded.'
		);
	}

	if ( state === 'empty' ) {
		return createElement(
			'section',
			{ 'data-admin-state': 'empty' },
			createElement( 'h2', null, 'Bots' ),
			createElement( 'p', null, 'No bots configured yet.' )
		);
	}

	const selected = ADMIN_SCREENS.find( ( item ) => item.screen === screen );
	const selectedLabel = selected?.label ?? 'Onboarding';
	const navigation = ADMIN_SCREENS.map( ( item ) => {
		const props: Record< string, unknown > = {
			href: `#/${ item.screen }`,
			key: item.screen,
		};

		if ( item.screen === screen ) {
			props[ 'aria-current' ] = 'page';
		}

		return createElement( 'a', props, item.label );
	} );

	return createElement(
		'div',
		{ 'data-admin-state': 'ready' },
		createElement(
			'nav',
			{ 'aria-label': 'Administration' },
			...navigation
		),
		createElement(
			'main',
			null,
			createElement( 'h1', null, selectedLabel )
		)
	);
};

const renderAdminShell = (
	root: Element,
	state: AdminShellState,
	screen: AdminScreen
): void => {
	window.wp.element.render( AdminShell( { state, screen } ), root );
};

const stateFromReadiness = (
	readiness: AdminOnboardingReadiness
): AdminShellState => {
	return ! readiness.ready && readiness.next_step === 'first_bot'
		? 'empty'
		: 'ready';
};

export const bootstrapAdminApp = ( hash = window.location.hash ): boolean => {
	const root = document.getElementById( 'wp-rag-ai-chatbot-admin' );

	if ( root === null ) {
		return false;
	}

	let currentState: AdminShellState = 'ready';
	const currentHash = (): string => window.location.hash || hash;
	const renderState = ( state: AdminShellState ): void => {
		currentState = state;
		renderAdminShell( root, state, resolveAdminScreen( currentHash() ) );
	};

	if ( activeHashChangeHandler !== null ) {
		window.removeEventListener( 'hashchange', activeHashChangeHandler );
	}

	activeHashChangeHandler = () => {
		if ( root.isConnected ) {
			renderState( currentState );
		}
	};
	window.addEventListener( 'hashchange', activeHashChangeHandler );

	const config = window.wpRagAiChatbotAdminConfig;
	const fetcher = window.fetch;

	if ( config === undefined ) {
		renderState( 'error' );
		return true;
	}

	if ( typeof fetcher !== 'function' ) {
		renderState( 'ready' );
		return true;
	}

	renderState( 'loading' );

	const client = createAdminApiClient( {
		baseUrl: config.restBase,
		nonce: config.nonce,
		fetcher: fetcher.bind( window ),
	} );

	void client
		.request< AdminOnboardingReadiness >( '/admin/onboarding/readiness' )
		.then( ( readiness ) => {
			renderState( stateFromReadiness( readiness ) );
		} )
		.catch( () => {
			renderState( 'error' );
		} );

	return true;
};

bootstrapAdminApp();
