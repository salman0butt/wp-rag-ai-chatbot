import {
	ProviderCredentialState,
	ProviderModelChoice,
	ProviderSettingsScreen,
} from './provider-settings';

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

interface BotDraft {
	name: string;
	provider_id: string;
	model_id: string;
	enabled: boolean;
}

export interface AdminShellProps {
	state: AdminShellState;
	screen?: AdminScreen;
	onboardingStep?: OnboardingStep;
	onboardingIssue?: OnboardingIssue;
	botPage?: BotPage;
	selectedBotId?: string;
	providerId?: string;
	providerCredential?: ProviderCredentialState;
	providerModels?: ReadonlyArray< ProviderModelChoice >;
	onCreateBot?: ( draft: BotDraft ) => Promise< void >;
	onUpdateBot?: ( bot: BotListItem, draft: BotDraft ) => Promise< void >;
	onDeleteBot?: ( bot: BotListItem ) => Promise< void >;
	onReplaceProviderCredential?: ( credential: string ) => Promise< void >;
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
	selectedBotId?: string;
	onCreate?: ( draft: BotDraft ) => Promise< void >;
	onUpdate?: ( bot: BotListItem, draft: BotDraft ) => Promise< void >;
	onDelete?: ( bot: BotListItem ) => Promise< void >;
}

interface BotEditorScreenProps {
	mode: 'create' | 'edit';
	bot?: BotListItem;
	onSave?: ( draft: BotDraft ) => Promise< void >;
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

interface AdminProviderModels {
	models?: unknown;
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

const normalizeAdminHash = ( hash: string ): string =>
	hash.replace( /^#\/?/, '' );

const resolveHashPath = ( hash: string ): string =>
	normalizeAdminHash( hash ).split( '?' )[ 0 ];

export const resolveAdminScreen = ( hash: string ): AdminScreen => {
	const candidate = resolveHashPath( hash ).split( '/' )[ 0 ];
	const screen = ADMIN_SCREENS.find( ( item ) => item.screen === candidate );

	return screen?.screen ?? 'onboarding';
};

const resolveBotPage = ( hash: string ): number => {
	const query = normalizeAdminHash( hash ).split( '?' )[ 1 ] ?? '';
	const pageValue = new URLSearchParams( query ).get( 'page' );
	const page = pageValue === null ? 1 : Number( pageValue );

	return Number.isSafeInteger( page ) && page >= 1 ? page : 1;
};

const resolveSelectedBotId = ( hash: string ): string | undefined => {
	const segments = resolveHashPath( hash ).split( '/' );

	if ( segments[ 0 ] !== 'bots' || ! segments[ 1 ] ) {
		return undefined;
	}

	try {
		return decodeURIComponent( segments[ 1 ] );
	} catch {
		return undefined;
	}
};

const resolveSelectedProviderId = ( hash: string ): string | undefined => {
	const segments = resolveHashPath( hash ).split( '/' );

	if ( segments[ 0 ] !== 'providers' || ! segments[ 1 ] ) {
		return undefined;
	}

	try {
		return decodeURIComponent( segments[ 1 ] );
	} catch {
		return undefined;
	}
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

export const BotEditorScreen = ( {
	mode,
	bot,
	onSave,
}: BotEditorScreenProps ): unknown => {
	const createElement = window.wp.element.createElement;
	const editorId = bot?.id ?? 'create';
	const validationId = `bot-${ editorId }-validation`;
	const nameId = bot === undefined ? 'bot-name' : `bot-${ editorId }-name`;
	const providerId =
		bot === undefined ? 'bot-provider' : `bot-${ editorId }-provider`;
	const modelId = bot === undefined ? 'bot-model' : `bot-${ editorId }-model`;

	return createElement(
		'form',
		{
			'data-bot-editor': mode,
			'data-edit-bot-id': bot?.id,
			onSubmit: ( event: Event ) => {
				event.preventDefault();

				if ( onSave === undefined ) {
					return;
				}

				const form = event.currentTarget as HTMLFormElement;
				const name = form.elements.namedItem(
					'name'
				) as HTMLInputElement;
				const provider = form.elements.namedItem(
					'provider_id'
				) as HTMLInputElement;
				const model = form.elements.namedItem(
					'model_id'
				) as HTMLInputElement;
				const fields = [ name, provider, model ];
				const validation = form.querySelector< HTMLElement >(
					`#${ validationId }`
				);

				for ( const field of fields ) {
					field.removeAttribute( 'aria-invalid' );
				}
				validation?.setAttribute( 'hidden', '' );

				const firstInvalid = fields.find(
					( field ) => field.value.trim() === ''
				);

				if ( firstInvalid !== undefined ) {
					for ( const field of fields ) {
						if ( field.value.trim() === '' ) {
							field.setAttribute( 'aria-invalid', 'true' );
						}
					}
					validation?.removeAttribute( 'hidden' );
					firstInvalid.focus();
					return;
				}

				void onSave( {
					name: name.value,
					provider_id: provider.value,
					model_id: model.value,
					enabled: bot?.enabled ?? true,
				} );
			},
		},
		createElement(
			'p',
			{
				hidden: true,
				id: validationId,
				role: 'alert',
			},
			'Complete the required bot fields.'
		),
		createElement( 'label', { htmlFor: nameId }, 'Bot name' ),
		createElement( 'input', {
			'aria-describedby': validationId,
			defaultValue: bot?.name,
			id: nameId,
			name: 'name',
			required: true,
			type: 'text',
		} ),
		createElement( 'label', { htmlFor: providerId }, 'Provider' ),
		createElement( 'input', {
			'aria-describedby': validationId,
			defaultValue: bot?.provider_id,
			id: providerId,
			name: 'provider_id',
			required: true,
			type: 'text',
		} ),
		createElement( 'label', { htmlFor: modelId }, 'Model' ),
		createElement( 'input', {
			'aria-describedby': validationId,
			defaultValue: bot?.model_id,
			id: modelId,
			name: 'model_id',
			required: true,
			type: 'text',
		} ),
		createElement(
			'button',
			{ type: 'submit' },
			mode === 'create' ? 'Create bot' : 'Save bot'
		)
	);
};

export const BotManagementScreen = ( {
	page,
	selectedBotId,
	onCreate,
	onUpdate,
	onDelete,
}: BotManagementScreenProps ): unknown => {
	const createElement = window.wp.element.createElement;
	const createEditor = BotEditorScreen( {
		mode: 'create',
		onSave: onCreate,
	} );

	if ( page.items.length === 0 ) {
		return createElement(
			'section',
			{ 'data-bot-management': 'empty' },
			createElement(
				'p',
				{ 'data-bot-list-empty': true },
				'No bots configured yet.'
			),
			createEditor
		);
	}

	const totalPages = Math.max( 1, Math.ceil( page.total / page.per_page ) );
	const selectedBot =
		page.items.find( ( item ) => item.id === selectedBotId ) ??
		page.items[ 0 ];
	const rows = page.items.map( ( item ) => {
		const linkProps: Record< string, unknown > = {
			href: `#/bots/${ encodeURIComponent( item.id ) }?page=${
				page.page
			}`,
		};

		if ( item.id === selectedBot.id ) {
			linkProps[ 'aria-current' ] = 'true';
		}

		return createElement(
			'li',
			{
				key: item.id,
				'data-bot-id': item.id,
			},
			createElement( 'a', linkProps, item.name )
		);
	} );
	const editor = BotEditorScreen( {
		mode: 'edit',
		bot: selectedBot,
		onSave:
			onUpdate === undefined
				? undefined
				: ( draft ) => onUpdate( selectedBot, draft ),
	} );
	const deleteButton = createElement(
		'button',
		{
			'data-delete-bot-id': selectedBot.id,
			type: 'button',
			onClick: () => {
				if (
					onDelete !== undefined &&
					// Browser-native confirmation is intentional for this destructive action.
					// eslint-disable-next-line no-alert
					window.confirm(
						`Delete ${ selectedBot.name }? This action cannot be undone.`
					)
				) {
					void onDelete( selectedBot );
				}
			},
		},
		'Delete bot'
	);
	const pagination: unknown[] = [];

	if ( page.page > 1 ) {
		pagination.push(
			createElement(
				'a',
				{
					'data-bot-page': 'previous',
					href: `#/bots?page=${ page.page - 1 }`,
				},
				'Previous'
			)
		);
	}

	pagination.push(
		createElement( 'span', null, `Page ${ page.page } of ${ totalPages }` )
	);

	if ( page.page < totalPages ) {
		pagination.push(
			createElement(
				'a',
				{
					'data-bot-page': 'next',
					href: `#/bots?page=${ page.page + 1 }`,
				},
				'Next'
			)
		);
	}

	return createElement(
		'section',
		{ 'data-bot-management': 'list' },
		createEditor,
		createElement( 'ul', null, ...rows ),
		editor,
		deleteButton,
		createElement(
			'nav',
			{ 'aria-label': 'Bot list pagination' },
			...pagination
		)
	);
};

export const AdminShell = ( {
	state,
	screen = 'onboarding',
	onboardingStep,
	onboardingIssue,
	botPage,
	selectedBotId,
	providerId,
	providerCredential,
	providerModels,
	onCreateBot,
	onUpdateBot,
	onDeleteBot,
	onReplaceProviderCredential,
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
			BotManagementScreen( {
				page: botPage,
				selectedBotId,
				onCreate: onCreateBot,
				onUpdate: onUpdateBot,
				onDelete: onDeleteBot,
			} )
		);
	} else if (
		screen === 'providers' &&
		providerId !== undefined &&
		providerCredential !== undefined
	) {
		screenContent = createElement(
			'div',
			null,
			createElement( 'h1', null, selectedLabel ),
			ProviderSettingsScreen( {
				providerId,
				credential: providerCredential,
				models: providerModels,
				onReplace: onReplaceProviderCredential,
			} )
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
	botPage?: BotPage,
	selectedBotId?: string,
	providerId?: string,
	providerCredential?: ProviderCredentialState,
	providerModels?: ReadonlyArray< ProviderModelChoice >,
	onCreateBot?: ( draft: BotDraft ) => Promise< void >,
	onUpdateBot?: ( bot: BotListItem, draft: BotDraft ) => Promise< void >,
	onDeleteBot?: ( bot: BotListItem ) => Promise< void >,
	onReplaceProviderCredential?: ( credential: string ) => Promise< void >
): void => {
	window.wp.element.render(
		AdminShell( {
			state,
			screen,
			onboardingStep,
			onboardingIssue,
			botPage,
			selectedBotId,
			providerId,
			providerCredential,
			providerModels,
			onCreateBot,
			onUpdateBot,
			onDeleteBot,
			onReplaceProviderCredential,
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
	let currentProviderCredential: ProviderCredentialState | undefined;
	let currentProviderModels: ProviderModelChoice[] | undefined;
	let loadedProviderId: string | undefined;
	let loadedProviderModelsId: string | undefined;
	let createBot: ( draft: BotDraft ) => Promise< void > = async () =>
		undefined;
	let updateBot: (
		bot: BotListItem,
		draft: BotDraft
	) => Promise< void > = async () => undefined;
	let deleteBot: ( bot: BotListItem ) => Promise< void > = async () =>
		undefined;
	let replaceProviderCredential: (
		credential: string
	) => Promise< void > = async () => undefined;
	const currentHash = (): string => window.location.hash || hash;
	const renderState = ( state: AdminShellState ): void => {
		currentState = state;
		const providerId = resolveSelectedProviderId( currentHash() );
		renderAdminShell(
			root,
			state,
			resolveAdminScreen( currentHash() ),
			currentOnboardingStep,
			currentOnboardingIssue,
			currentBotPage,
			resolveSelectedBotId( currentHash() ),
			providerId,
			providerId === loadedProviderId
				? currentProviderCredential
				: undefined,
			providerId === loadedProviderModelsId
				? currentProviderModels
				: undefined,
			createBot,
			updateBot,
			deleteBot,
			replaceProviderCredential
		);
	};

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
	const refreshBotPage = async (
		page = resolveBotPage( currentHash() )
	): Promise< void > => {
		currentBotPage = await client.request< BotPage >(
			`/admin/bots?page=${ page }&per_page=20`
		);
	};
	const refreshProviderCredential = async (
		providerId: string
	): Promise< void > => {
		const credential = await client.request< ProviderCredentialState >(
			`/admin/providers/${ encodeURIComponent( providerId ) }/credential`
		);
		currentProviderCredential = {
			configured: credential.configured === true,
			source:
				typeof credential.source === 'string'
					? credential.source
					: 'none',
		};
		loadedProviderId = providerId;
	};
	const refreshProviderModels = async (
		providerId: string
	): Promise< void > => {
		const response = await client.request< AdminProviderModels >(
			`/admin/models?provider_id=${ encodeURIComponent(
				providerId
			) }&purpose=generation`
		);
		const models = Array.isArray( response.models ) ? response.models : [];
		currentProviderModels = models.flatMap( ( model ) => {
			if (
				typeof model !== 'object' ||
				model === null ||
				! ( 'model_id' in model ) ||
				! ( 'display_name' in model ) ||
				typeof model.model_id !== 'string' ||
				typeof model.display_name !== 'string'
			) {
				return [];
			}

			return [
				{
					model_id: model.model_id,
					display_name: model.display_name,
				},
			];
		} );
		loadedProviderModelsId = providerId;
	};
	const refreshProviderSettings = async (
		providerId: string
	): Promise< void > => {
		await refreshProviderCredential( providerId );
		await refreshProviderModels( providerId );
	};

	if ( activeHashChangeHandler !== null ) {
		window.removeEventListener( 'hashchange', activeHashChangeHandler );
	}

	activeHashChangeHandler = () => {
		if ( ! root.isConnected ) {
			return;
		}

		const screen = resolveAdminScreen( currentHash() );
		const targetPage = resolveBotPage( currentHash() );

		if (
			screen === 'bots' &&
			( currentBotPage === undefined ||
				currentBotPage.page !== targetPage )
		) {
			void refreshBotPage( targetPage )
				.then( () => renderState( stateFromReadiness() ) )
				.catch( () => renderState( 'error' ) );
			return;
		}

		const providerId = resolveSelectedProviderId( currentHash() );

		if (
			screen === 'providers' &&
			providerId !== undefined &&
			( providerId !== loadedProviderId ||
				providerId !== loadedProviderModelsId )
		) {
			currentProviderCredential = undefined;
			currentProviderModels = undefined;
			void refreshProviderSettings( providerId )
				.then( () => renderState( stateFromReadiness() ) )
				.catch( () => renderState( 'error' ) );
			return;
		}

		renderState( currentState );
	};
	window.addEventListener( 'hashchange', activeHashChangeHandler );

	createBot = async ( draft: BotDraft ): Promise< void > => {
		try {
			await client.request< BotListItem >( '/admin/bots', {
				method: 'POST',
				body: draft,
			} );
			await refreshBotPage( 1 );
			renderState( stateFromReadiness() );
		} catch {
			renderState( 'error' );
		}
	};
	updateBot = async (
		bot: BotListItem,
		draft: BotDraft
	): Promise< void > => {
		try {
			await client.request< { bot: BotListItem } >(
				`/admin/bots/${ encodeURIComponent( bot.id ) }`,
				{
					method: 'PUT',
					body: {
						version: bot.version,
						...draft,
					},
				}
			);
			await refreshBotPage( 1 );
			renderState( stateFromReadiness() );
		} catch {
			renderState( 'error' );
		}
	};
	deleteBot = async ( bot: BotListItem ): Promise< void > => {
		try {
			await client.request< { deleted: true } >(
				`/admin/bots/${ encodeURIComponent( bot.id ) }`,
				{ method: 'DELETE' }
			);
			await refreshBotPage( 1 );
			renderState( stateFromReadiness() );
		} catch {
			renderState( 'error' );
		}
	};
	replaceProviderCredential = async (
		credential: string
	): Promise< void > => {
		const providerId = resolveSelectedProviderId( currentHash() );

		if ( providerId === undefined ) {
			return;
		}

		try {
			await client.request< { managed: true } >(
				`/admin/providers/${ encodeURIComponent(
					providerId
				) }/credential`,
				{
					method: 'PUT',
					body: { credential },
				}
			);
			await refreshProviderCredential( providerId );
			renderState( stateFromReadiness() );
		} catch {
			renderState( 'error' );
		}
	};

	void client
		.request< AdminOnboardingReadiness >( '/admin/onboarding/readiness' )
		.then( async ( readiness ) => {
			currentOnboardingStep = readiness.next_step;
			currentOnboardingIssue = readiness.issue;
			const screen = resolveAdminScreen( currentHash() );

			if ( screen === 'bots' ) {
				await refreshBotPage();
			}

			if ( screen === 'providers' ) {
				const providerId = resolveSelectedProviderId( currentHash() );

				if ( providerId !== undefined ) {
					await refreshProviderSettings( providerId );
				}
			}

			renderState( stateFromReadiness() );
		} )
		.catch( () => {
			renderState( 'error' );
		} );

	return true;
};

bootstrapAdminApp();
