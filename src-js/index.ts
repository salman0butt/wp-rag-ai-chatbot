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
export type OnboardingStep = 'provider' | 'model' | 'first_bot' | 'complete';
export type OnboardingIssue =
	| 'provider_unavailable'
	| 'missing_credential'
	| 'unsupported_capability';

export interface AdminShellProps {
	state: AdminShellState;
	screen?: AdminScreen;
	onboardingStep?: OnboardingStep;
	onboardingIssue?: OnboardingIssue;
	botPage?: BotPage;
}

export interface OnboardingFlowProps {
	nextStep: OnboardingStep;
	issue?: OnboardingIssue;
}

interface BotListItem {
	id: string;
	name: string;
	enabled: boolean;
	provider_id: string;
	model_id: string;
	version: number;
	created_at: string;
	updated_at: string;
}

interface BotPage {
	items: BotListItem[];
	total: number;
	page: number;
	per_page: number;
}

interface BotManagementScreenProps {
	page: BotPage;
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
	next_step: OnboardingStep;
	issue?: OnboardingIssue;
}

interface OnboardingIssueCopy {
	message: string;
	action: string;
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

const ONBOARDING_HEADINGS: Readonly< Record< OnboardingStep, string > > = {
	provider: 'Connect a provider',
	model: 'Choose a model',
	first_bot: 'Create your first bot',
	complete: 'Onboarding complete',
};

const ONBOARDING_ISSUES: Readonly<
	Record< OnboardingIssue, OnboardingIssueCopy >
> = {
	provider_unavailable: {
		message: 'The provider is currently unavailable.',
		action: 'Review provider settings',
	},
	missing_credential: {
		message: 'Add a provider credential to continue.',
		action: 'Configure provider',
	},
	unsupported_capability: {
		message: 'Choose a provider and model that support generation.',
		action: 'Review compatible models',
	},
};

let activeHashChangeHandler: ( () => void ) | null = null;

export const resolveAdminScreen = ( hash: string ): AdminScreen => {
	const candidate = hash.replace( /^#\/?/, '' ).split( '/' )[ 0 ];
	const screen = ADMIN_SCREENS.find( ( item ) => item.screen === candidate );

	return screen?.screen ?? 'onboarding';
};

export const OnboardingFlow = ( {
	nextStep,
	issue,
}: OnboardingFlowProps ): unknown => {
	const createElement = window.wp.element.createElement;
	const issueCopy =
		issue === undefined ? undefined : ONBOARDING_ISSUES[ issue ];
	const issueContent =
		issueCopy === undefined
			? undefined
			: createElement(
					'div',
					{ role: 'alert' },
					createElement( 'p', null, issueCopy.message ),
					createElement(
						'a',
						{
							href: '#/providers',
							autoFocus: true,
						},
						issueCopy.action
					)
			  );

	return createElement(
		'section',
		{ 'data-onboarding-step': nextStep },
		createElement( 'h2', null, ONBOARDING_HEADINGS[ nextStep ] ),
		issueContent
	);
};

export const BotManagementScreen = ( {
	page,
}: BotManagementScreenProps ): unknown => {
	const createElement = window.wp.element.createElement;

	if ( page.items.length === 0 ) {
		return createElement(
			'section',
			{ 'data-bot-management': 'empty' },
			createElement(
				'p',
				{ 'data-bot-list-empty': true },
				'No bots configured yet.'
			)
		);
	}

	const totalPages = Math.max( 1, Math.ceil( page.total / page.per_page ) );
	const rows = page.items.map( ( item ) =>
		createElement(
			'li',
			{
				key: item.id,
				'data-bot-id': item.id,
			},
			item.name
		)
	);

	return createElement(
		'section',
		{ 'data-bot-management': 'list' },
		createElement( 'ul', null, ...rows ),
		createElement(
			'nav',
			{ 'aria-label': 'Bot list pagination' },
			`Page ${ page.page } of ${ totalPages }`
		)
	);
};

export const AdminShell = ( {
	state,
	screen = 'onboarding',
	onboardingStep,
	onboardingIssue,
	botPage,
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
	let screenContent: unknown = createElement( 'h1', null, selectedLabel );

	if ( screen === 'onboarding' && onboardingStep !== undefined ) {
		screenContent = createElement(
			'div',
			null,
			createElement( 'h1', null, selectedLabel ),
			OnboardingFlow( {
				nextStep: onboardingStep,
				issue: onboardingIssue,
			} )
		);
	} else if ( screen === 'bots' && botPage !== undefined ) {
		screenContent = createElement(
			'div',
			null,
			createElement( 'h1', null, selectedLabel ),
			BotManagementScreen( { page: botPage } )
		);
	}

	return createElement(
		'div',
		{ 'data-admin-state': 'ready' },
		createElement(
			'nav',
			{ 'aria-label': 'Administration' },
			...navigation
		),
		createElement( 'main', null, screenContent )
	);
};

const renderAdminShell = (
	root: Element,
	state: AdminShellState,
	screen: AdminScreen,
	onboardingStep?: OnboardingStep,
	onboardingIssue?: OnboardingIssue,
	botPage?: BotPage
): void => {
	window.wp.element.render(
		AdminShell( {
			state,
			screen,
			onboardingStep,
			onboardingIssue,
			botPage,
		} ),
		root
	);
};

const stateFromReadiness = (): AdminShellState => 'ready';

export const bootstrapAdminApp = ( hash = window.location.hash ): boolean => {
	const root = document.getElementById( 'wp-rag-ai-chatbot-admin' );

	if ( root === null ) {
		return false;
	}

	let currentState: AdminShellState = 'ready';
	let currentOnboardingStep: OnboardingStep | undefined;
	let currentOnboardingIssue: OnboardingIssue | undefined;
	let currentBotPage: BotPage | undefined;
	const currentHash = (): string => window.location.hash || hash;
	const renderState = ( state: AdminShellState ): void => {
		currentState = state;
		renderAdminShell(
			root,
			state,
			resolveAdminScreen( currentHash() ),
			currentOnboardingStep,
			currentOnboardingIssue,
			currentBotPage
		);
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
		renderState( 'error' );
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
		.then( async ( readiness ) => {
			currentOnboardingStep = readiness.next_step;
			currentOnboardingIssue = readiness.issue;

			if ( resolveAdminScreen( currentHash() ) === 'bots' ) {
				currentBotPage = await client.request< BotPage >(
					'/admin/bots?page=1&per_page=20'
				);
			}

			renderState( stateFromReadiness() );
		} )
		.catch( () => {
			renderState( 'error' );
		} );

	return true;
};

bootstrapAdminApp();
