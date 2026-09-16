import {
	ProviderCredentialState,
	ProviderModelChoice,
	ProviderSettingsIssue,
	ProviderSettingsScreen,
} from './provider-settings';
import { AppearanceCustomizer } from './appearance-customizer';
import { DisplayRulesEditor } from './display-rules-editor';
import {
	normalizeDisplayRules,
	type DisplayRulesConfig,
} from './display-rules';
import type { PlaygroundControllerState } from './playground-controller';
import { PlaygroundPanel } from './playground-panel';
import { createPlaygroundRuntime } from './playground-runtime';
import type { PlaygroundRequestDraft } from './playground-screen';
import {
	normalizeWidgetAppearance,
	type WidgetAppearance,
} from './widget-appearance';
import {
	ModernAdminShell,
	type AdminReadiness,
	type ModernAdminScreen,
	type PublishBotOption,
	type PublishBotStatus,
} from './admin-ui';
import {
	KnowledgeWizard,
	buildKnowledgeSourceRequest,
	type KnowledgeSourceDraft,
	type KnowledgeWizardJob,
	type KnowledgeWizardSource,
} from './knowledge-wizard';
import {
	BotKnowledgeBinding,
	deleteBotKnowledgeBinding,
	saveBotKnowledgeBinding,
	type BotKnowledgeBindingStatus,
	type BotKnowledgeSourcesStatus,
	type BotRetrievalProjection,
	type KnowledgeSourceChoice,
} from './bot-knowledge-binding';

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
	requestFormData: < T >( path: string, body: FormData ) => Promise< T >;
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

const getAdminErrorCode = ( payload: unknown ): string | undefined => {
	if (
		typeof payload === 'object' &&
		payload !== null &&
		'code' in payload &&
		typeof payload.code === 'string'
	) {
		return payload.code;
	}
	if (
		typeof payload === 'object' &&
		payload !== null &&
		'error' in payload &&
		typeof payload.error === 'object' &&
		payload.error !== null &&
		'code' in payload.error &&
		typeof payload.error.code === 'string'
	) {
		return payload.error.code;
	}

	return undefined;
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

			const errorCode = getAdminErrorCode( payload );
			if ( ! response.ok || errorCode !== undefined ) {
				throw new AdminApiError(
					response.status,
					errorCode ?? 'admin_request_failed'
				);
			}

			return payload as T;
		},
		async requestFormData< T >(
			path: string,
			body: FormData
		): Promise< T > {
			const normalizedPath = path.startsWith( '/' ) ? path : `/${ path }`;
			const response = await config.fetcher(
				`${ baseUrl }${ normalizedPath }`,
				{
					credentials: 'same-origin',
					headers: {
						Accept: 'application/json',
						'X-WP-Nonce': config.nonce,
					},
					method: 'POST',
					body,
				}
			);
			const payload = ( await response.json() ) as unknown;

			const errorCode = getAdminErrorCode( payload );
			if ( ! response.ok || errorCode !== undefined ) {
				throw new AdminApiError(
					response.status,
					errorCode ?? 'admin_request_failed'
				);
			}

			return payload as T;
		},
	};
};

export type AdminShellState = 'loading' | 'empty' | 'error' | 'ready';
export type AdminScreen =
	| 'onboarding'
	| 'overview'
	| 'bots'
	| 'providers'
	| 'knowledge'
	| 'playground'
	| 'publish';
type KnowledgeJobMutationError = 'invalid_transition' | 'admin_request_failed';
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
	readiness?: AdminReadiness;
	onboardingStep?: OnboardingStep;
	onboardingIssue?: OnboardingIssue;
	botPage?: BotPage;
	botKnowledgeSources?: ReadonlyArray< KnowledgeSourceChoice >;
	botRetrieval?: BotRetrievalProjection;
	botRetrievalStatus?: BotKnowledgeBindingStatus;
	botRetrievalError?: string;
	botKnowledgeSourcesStatus?: BotKnowledgeSourcesStatus;
	botKnowledgeSourcesError?: string;
	publishBotId?: string;
	publishBotOptions?: ReadonlyArray< PublishBotOption >;
	publishBotPublishable?: boolean;
	publishBotStatus?: PublishBotStatus;
	publishBotError?: string;
	onSelectPublishBot?: ( botId: string ) => void;
	onSaveBotKnowledge?: ( sourceId: number ) => void | Promise< void >;
	onDisconnectBotKnowledge?: () => void | Promise< void >;
	selectedBotId?: string;
	botAppearance?: WidgetAppearance;
	botAppearanceSaving?: boolean;
	botAppearanceError?: string;
	onChangeBotAppearance?: ( next: WidgetAppearance ) => void;
	onSaveBotAppearance?: ( next: WidgetAppearance ) => void;
	botDisplayRules?: DisplayRulesConfig;
	botDisplayRulesSaving?: boolean;
	botDisplayRulesError?: string;
	onChangeBotDisplayRules?: ( next: DisplayRulesConfig ) => void;
	onSaveBotDisplayRules?: ( next: DisplayRulesConfig ) => void;
	knowledgePage?: KnowledgeSourcePage;
	selectedKnowledgeSourceId?: string;
	selectedKnowledgeDocumentKey?: string;
	knowledgeDetail?: KnowledgeSourceDetail;
	knowledgeDocuments?: KnowledgeDocumentPage;
	knowledgeChunks?: KnowledgeChunkPage;
	knowledgeJobs?: KnowledgeJobPage;
	knowledgeJobMutationError?: KnowledgeJobMutationError;
	knowledgeWizardError?: string;
	knowledgeSourceSubmitting?: boolean;
	woocommerceAvailable?: boolean;
	onCreateKnowledgeSource?: (
		draft: KnowledgeSourceDraft
	) => Promise< void >;
	onCancelKnowledgeJob?: ( job: KnowledgeJobItem ) => Promise< void >;
	onRetryKnowledgeJob?: ( job: KnowledgeJobItem ) => Promise< void >;
	providerId?: string;
	providerCredential?: ProviderCredentialState;
	providerModels?: ReadonlyArray< ProviderModelChoice >;
	providerIssue?: ProviderSettingsIssue;
	onCreateBot?: ( draft: BotDraft ) => Promise< void >;
	onUpdateBot?: ( bot: BotListItem, draft: BotDraft ) => Promise< void >;
	onDeleteBot?: ( bot: BotListItem ) => Promise< void >;
	onReplaceProviderCredential?: ( credential: string ) => Promise< void >;
	playgroundState?: PlaygroundControllerState;
	onSubmitPlayground?: ( request: PlaygroundRequestDraft ) => void;
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

interface KnowledgeSourceItem {
	id: string | number;
	source_key: string;
	source_type: string;
	external_id: string | null;
	title: string;
	canonical_url: string | null;
	status: string;
	last_synced_at: string | null;
	updated_at: string;
}

interface KnowledgeSourcePage {
	items: KnowledgeSourceItem[];
	total: number;
	page: number;
	per_page: number;
}

interface KnowledgeSourceDetail {
	id: number;
	source_key: string;
	source_type: string;
	external_id: string | null;
	title: string;
	canonical_url: string | null;
	status: string;
	last_synced_at: string | null;
	created_at: string;
	updated_at: string;
}

interface KnowledgeDocumentItem {
	id: number;
	document_key: string;
	source_id: number;
	external_id: string | null;
	document_type: string;
	title: string;
	canonical_url: string | null;
	source_version: string;
	language: string | null;
	visibility: string;
	created_at: string;
	updated_at: string;
}

interface KnowledgeDocumentPage {
	items: KnowledgeDocumentItem[];
	total: number;
	page: number;
	per_page: number;
}

interface KnowledgeChunkItem {
	content: string;
	content_truncated: boolean;
	sequence: number;
}

interface KnowledgeChunkPage {
	items: KnowledgeChunkItem[];
	total: number;
	page: number;
	per_page: number;
}

interface KnowledgeJobItem {
	job_key: string;
	type: string;
	status: string;
	attempts: number;
	max_attempts: number;
	available_at: string;
	cancel_requested_at: string | null;
	progress_current: number;
	progress_total: number;
	progress_message: string | null;
	last_error_code: string | null;
	last_error_message: string | null;
	started_at: string | null;
	completed_at: string | null;
	created_at: string;
	updated_at: string;
}

interface KnowledgeJobPage {
	items: KnowledgeJobItem[];
	total: number;
	page: number;
	per_page: number;
}

interface KnowledgeManagementScreenProps {
	page: KnowledgeSourcePage;
	selectedSourceId?: string;
	selectedDocumentKey?: string;
	detail?: KnowledgeSourceDetail;
	documents?: KnowledgeDocumentPage;
	chunks?: KnowledgeChunkPage;
	jobs?: KnowledgeJobPage;
	mutationError?: KnowledgeJobMutationError;
	knowledgeWizardError?: string;
	knowledgeSourceSubmitting?: boolean;
	woocommerceAvailable?: boolean;
	onCreateKnowledgeSource?: (
		draft: KnowledgeSourceDraft
	) => Promise< void >;
	onCancelJob?: ( job: KnowledgeJobItem ) => Promise< void >;
	onRetryJob?: ( job: KnowledgeJobItem ) => Promise< void >;
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
type ElementRoot = { render: ( element: unknown ) => void };
type ElementRootFactory = ( root: Element ) => ElementRoot;

interface AdminBootConfig {
	plugin: string;
	restBase: string;
	nonce: string;
	woocommerceAvailable?: boolean;
}

interface AdminOnboardingReadiness extends AdminReadiness {
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
				createRoot?: ElementRootFactory;
			};
		};
		wpRagAiChatbotAdminConfig?: AdminBootConfig;
	}
}

const adminElementRoots = new WeakMap< Element, ElementRoot >();

const ADMIN_SCREENS: ReadonlyArray< {
	screen: AdminScreen;
	label: string;
} > = [
	{ screen: 'onboarding', label: 'Onboarding' },
	{ screen: 'bots', label: 'Bots' },
	{ screen: 'providers', label: 'Providers' },
	{ screen: 'knowledge', label: 'Knowledge' },
	{ screen: 'playground', label: 'Playground' },
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

const KNOWLEDGE_JOB_MUTATION_ERROR_MESSAGES: Readonly<
	Record< KnowledgeJobMutationError, string >
> = {
	invalid_transition:
		'The job state changed. Refresh and try the action again.',
	admin_request_failed: 'The job action could not be completed. Try again.',
};

const knowledgeJobMutationErrorFromError = (
	error: unknown
): KnowledgeJobMutationError =>
	error instanceof AdminApiError && error.code === 'invalid_transition'
		? 'invalid_transition'
		: 'admin_request_failed';

const knowledgeSourceErrorMessage = ( error: unknown ): string => {
	if ( error instanceof AdminApiError ) {
		switch ( error.code ) {
			case 'validation_error':
				return 'Check the source details and try again.';
			case 'conflict':
				return 'A source with these details already exists.';
			case 'queue_error':
				return 'The source could not be queued. Try again.';
		}
	}

	return 'The source could not be saved. Try again.';
};

const providerSettingsIssueFromError = (
	error: unknown
): ProviderSettingsIssue | undefined => {
	if ( ! ( error instanceof AdminApiError ) ) {
		return undefined;
	}

	switch ( error.code ) {
		case 'missing_credential':
		case 'provider_unavailable':
		case 'unsupported_capability':
			return error.code;
		default:
			return undefined;
	}
};

let activeHashChangeHandler: ( () => void ) | null = null;

const normalizeAdminHash = ( hash: string ): string =>
	hash.replace( /^#\/?/, '' );

const resolveHashPath = ( hash: string ): string =>
	normalizeAdminHash( hash ).split( '?' )[ 0 ];

export const resolveAdminScreen = ( hash: string ): AdminScreen => {
	const candidate = resolveHashPath( hash ).split( '/' )[ 0 ];
	if ( candidate === 'overview' || candidate === 'publish' ) {
		return candidate;
	}
	const screen = ADMIN_SCREENS.find( ( item ) => item.screen === candidate );

	return screen?.screen ?? 'onboarding';
};

const resolvePage = ( hash: string ): number => {
	const query = normalizeAdminHash( hash ).split( '?' )[ 1 ] ?? '';
	const pageValue = new URLSearchParams( query ).get( 'page' );
	const page = pageValue === null ? 1 : Number( pageValue );

	return Number.isSafeInteger( page ) && page >= 1 ? page : 1;
};

const resolveBotPage = ( hash: string ): number => resolvePage( hash );
const resolveKnowledgePage = ( hash: string ): number => resolvePage( hash );

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

const resolveSelectedPublishBotId = ( hash: string ): string | undefined => {
	const segments = resolveHashPath( hash ).split( '/' );

	if ( segments[ 0 ] !== 'publish' || ! segments[ 1 ] ) {
		return undefined;
	}

	try {
		return decodeURIComponent( segments[ 1 ] );
	} catch {
		return undefined;
	}
};

const resolveSelectedKnowledgeSourceId = (
	hash: string
): string | undefined => {
	const segments = resolveHashPath( hash ).split( '/' );

	if ( segments[ 0 ] !== 'knowledge' || ! segments[ 1 ] ) {
		return undefined;
	}

	try {
		return decodeURIComponent( segments[ 1 ] );
	} catch {
		return undefined;
	}
};

const resolveSelectedPersistedKnowledgeSourceId = (
	hash: string
): string | undefined => {
	const sourceId = resolveSelectedKnowledgeSourceId( hash );

	return sourceId !== undefined && /^[1-9]\d*$/.test( sourceId )
		? sourceId
		: undefined;
};

const resolveSelectedKnowledgeDocumentKey = (
	hash: string
): string | undefined => {
	const segments = resolveHashPath( hash ).split( '/' );

	if (
		segments[ 0 ] !== 'knowledge' ||
		! segments[ 1 ] ||
		segments[ 2 ] !== 'documents' ||
		! segments[ 3 ]
	) {
		return undefined;
	}

	try {
		return decodeURIComponent( segments[ 3 ] );
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

export const KnowledgeManagementScreen = ( {
	page,
	selectedSourceId,
	selectedDocumentKey,
	detail,
	documents,
	chunks,
	jobs,
	mutationError,
	knowledgeWizardError,
	knowledgeSourceSubmitting,
	woocommerceAvailable,
	onCreateKnowledgeSource,
	onCancelJob,
	onRetryJob,
}: KnowledgeManagementScreenProps ): unknown => {
	const createElement = window.wp.element.createElement;
	const mutationErrorContent =
		mutationError === undefined
			? undefined
			: createElement(
					'div',
					{
						role: 'alert',
						'data-knowledge-job-error': mutationError,
					},
					KNOWLEDGE_JOB_MUTATION_ERROR_MESSAGES[ mutationError ]
			  );
	const jobRows =
		jobs?.items.map( ( item ) => {
			const progress = `${ item.progress_current } of ${ item.progress_total }`;
			const diagnostics = [
				item.last_error_code,
				item.last_error_message,
			].filter( ( value ): value is string => value !== null );

			return createElement(
				'li',
				{
					key: item.job_key,
					'data-knowledge-job-key': item.job_key,
				},
				createElement( 'h3', null, item.type ),
				createElement( 'p', null, `Status: ${ item.status }` ),
				createElement( 'p', null, `Progress: ${ progress }` ),
				item.progress_message === null
					? undefined
					: createElement( 'p', null, item.progress_message ),
				...diagnostics.map( ( value ) =>
					createElement( 'p', { key: value }, value )
				),
				item.status === 'queued' || item.status === 'running'
					? createElement(
							'button',
							{
								type: 'button',
								'data-knowledge-job-action': 'cancel',
								'data-knowledge-job-key': item.job_key,
								onClick: () => {
									if ( onCancelJob !== undefined ) {
										void onCancelJob( item );
									}
								},
							},
							'Cancel'
					  )
					: undefined,
				item.status === 'failed'
					? createElement(
							'button',
							{
								type: 'button',
								'data-knowledge-job-action': 'retry',
								'data-knowledge-job-key': item.job_key,
								onClick: () => {
									if ( onRetryJob !== undefined ) {
										void onRetryJob( item );
									}
								},
							},
							'Retry'
					  )
					: undefined
			);
		} ) ?? [];
	const jobContent =
		jobs === undefined
			? undefined
			: createElement(
					'section',
					{ 'data-knowledge-jobs': 'list' },
					createElement( 'h2', null, 'Indexing jobs' ),
					mutationErrorContent,
					jobRows.length === 0
						? createElement( 'p', null, 'No indexing jobs found.' )
						: createElement( 'ul', null, ...jobRows )
			  );

	if ( page.items.length === 0 ) {
		return createElement(
			'section',
			{
				className: 'wp-rag-ai-chatbot-knowledge-management',
				'data-knowledge-management': 'empty',
			},
			createElement( 'p', null, 'No knowledge sources found.' ),
			KnowledgeWizard( {
				sources: [],
				jobs: ( jobs?.items ??
					[] ) as ReadonlyArray< KnowledgeWizardJob >,
				woocommerceAvailable,
				submitting: knowledgeSourceSubmitting,
				onCreate: onCreateKnowledgeSource,
				onCancelJob:
					onCancelJob === undefined
						? undefined
						: ( job ) => onCancelJob( job as KnowledgeJobItem ),
				onRetryJob:
					onRetryJob === undefined
						? undefined
						: ( job ) => onRetryJob( job as KnowledgeJobItem ),
			} ),
			knowledgeWizardError === undefined
				? undefined
				: createElement(
						'p',
						{
							role: 'alert',
							'data-knowledge-wizard-request-error': '',
						},
						knowledgeWizardError
				  ),
			jobContent
		);
	}

	const totalPages = Math.max( 1, Math.ceil( page.total / page.per_page ) );
	const selectedSource =
		page.items.find( ( item ) => String( item.id ) === selectedSourceId ) ??
		page.items[ 0 ];
	const rows = page.items.map( ( item ) => {
		const linkProps: Record< string, unknown > = {
			href: `#/knowledge/${ encodeURIComponent( item.id ) }?page=${
				page.page
			}`,
		};

		if ( item.id === selectedSource.id ) {
			linkProps[ 'aria-current' ] = 'true';
		}

		return createElement(
			'li',
			{
				key: item.id,
				'data-knowledge-source-id': item.id,
			},
			createElement( 'a', linkProps, item.title )
		);
	} );
	const pagination: unknown[] = [];

	if ( page.page > 1 ) {
		pagination.push(
			createElement(
				'a',
				{
					'data-knowledge-page': 'previous',
					href: `#/knowledge?page=${ page.page - 1 }`,
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
					'data-knowledge-page': 'next',
					href: `#/knowledge?page=${ page.page + 1 }`,
				},
				'Next'
			)
		);
	}

	const selectedSummary =
		detail === undefined
			? createElement(
					'article',
					{ 'data-knowledge-selected-source': selectedSource.id },
					createElement( 'h2', null, selectedSource.title ),
					createElement(
						'p',
						null,
						`Type: ${ selectedSource.source_type }`
					),
					createElement(
						'p',
						null,
						`Status: ${ selectedSource.status }`
					)
			  )
			: createElement(
					'article',
					{ 'data-knowledge-selected-detail': String( detail.id ) },
					createElement( 'h2', null, detail.title ),
					createElement( 'p', null, `Type: ${ detail.source_type }` ),
					createElement( 'p', null, `Status: ${ detail.status }` )
			  );
	const documentSourceId = String( detail?.id ?? selectedSource.id );
	const documentRows =
		documents?.items.map( ( item ) => {
			const linkProps: Record< string, unknown > = {
				href: `#/knowledge/${ encodeURIComponent(
					documentSourceId
				) }/documents/${ encodeURIComponent(
					item.document_key
				) }?page=${ page.page }`,
			};

			if ( item.document_key === selectedDocumentKey ) {
				linkProps[ 'aria-current' ] = 'true';
			}

			return createElement(
				'li',
				{
					key: item.document_key,
					'data-knowledge-document-key': item.document_key,
				},
				createElement(
					'h3',
					null,
					createElement( 'a', linkProps, item.title )
				),
				createElement( 'p', null, `Type: ${ item.document_type }` ),
				createElement( 'p', null, `Visibility: ${ item.visibility }` )
			);
		} ) ?? [];
	const documentContent =
		documents === undefined
			? undefined
			: createElement(
					'section',
					{ 'data-knowledge-documents': 'list' },
					createElement( 'h2', null, 'Documents' ),
					documentRows.length === 0
						? createElement( 'p', null, 'No documents found.' )
						: createElement( 'ul', null, ...documentRows )
			  );
	const chunkRows =
		chunks?.items.map( ( item ) =>
			createElement(
				'li',
				{
					key: item.sequence,
					'data-knowledge-chunk-sequence': item.sequence,
				},
				createElement( 'p', null, item.content )
			)
		) ?? [];
	const chunkContent =
		chunks === undefined
			? undefined
			: createElement(
					'section',
					{ 'data-knowledge-chunks': 'list' },
					createElement( 'h2', null, 'Chunks' ),
					chunkRows.length === 0
						? createElement( 'p', null, 'No chunks found.' )
						: createElement( 'ul', null, ...chunkRows )
			  );

	return createElement(
		'section',
		{
			className: 'wp-rag-ai-chatbot-knowledge-management',
			'data-knowledge-management': 'list',
		},
		KnowledgeWizard( {
			sources: page.items as ReadonlyArray< KnowledgeWizardSource >,
			jobs: ( jobs?.items ?? [] ) as ReadonlyArray< KnowledgeWizardJob >,
			woocommerceAvailable,
			submitting: knowledgeSourceSubmitting,
			onCreate: onCreateKnowledgeSource,
			onCancelJob:
				onCancelJob === undefined
					? undefined
					: ( job ) => onCancelJob( job as KnowledgeJobItem ),
			onRetryJob:
				onRetryJob === undefined
					? undefined
					: ( job ) => onRetryJob( job as KnowledgeJobItem ),
		} ),
		knowledgeWizardError === undefined
			? undefined
			: createElement(
					'p',
					{
						role: 'alert',
						'data-knowledge-wizard-request-error': '',
					},
					knowledgeWizardError
			  ),
		createElement( 'ul', null, ...rows ),
		selectedSummary,
		documentContent,
		chunkContent,
		jobContent,
		createElement(
			'nav',
			{ 'aria-label': 'Knowledge source pagination' },
			...pagination
		)
	);
};

const renderLegacyScreenContent = (
	props: AdminShellProps,
	includeHeading = true
): unknown => {
	const {
		screen = 'onboarding',
		onboardingStep,
		onboardingIssue,
		botPage,
		botKnowledgeSources,
		botRetrieval,
		botRetrievalStatus,
		botRetrievalError,
		botKnowledgeSourcesStatus,
		botKnowledgeSourcesError,
		onSaveBotKnowledge,
		onDisconnectBotKnowledge,
		selectedBotId,
		botAppearance,
		botAppearanceSaving = false,
		botAppearanceError,
		onChangeBotAppearance,
		onSaveBotAppearance,
		botDisplayRules,
		botDisplayRulesSaving = false,
		botDisplayRulesError,
		onChangeBotDisplayRules,
		onSaveBotDisplayRules,
		knowledgePage,
		selectedKnowledgeSourceId,
		selectedKnowledgeDocumentKey,
		knowledgeDetail,
		knowledgeDocuments,
		knowledgeChunks,
		knowledgeJobs,
		knowledgeJobMutationError,
		knowledgeWizardError,
		knowledgeSourceSubmitting,
		woocommerceAvailable,
		onCreateKnowledgeSource,
		onCancelKnowledgeJob,
		onRetryKnowledgeJob,
		providerId,
		providerCredential,
		providerModels,
		providerIssue,
		onCreateBot,
		onUpdateBot,
		onDeleteBot,
		onReplaceProviderCredential,
		playgroundState,
		onSubmitPlayground,
	} = props;
	const createElement = window.wp.element.createElement;
	const selected = ADMIN_SCREENS.find( ( item ) => item.screen === screen );
	const selectedLabel = selected?.label ?? 'Onboarding';
	const heading = (): unknown =>
		includeHeading ? createElement( 'h1', null, selectedLabel ) : undefined;
	let screenContent: unknown = heading();

	if ( screen === 'onboarding' && onboardingStep !== undefined ) {
		screenContent = createElement(
			'div',
			null,
			heading(),
			OnboardingFlow( {
				nextStep: onboardingStep,
				issue: onboardingIssue,
			} )
		);
	} else if ( screen === 'bots' && botPage !== undefined ) {
		screenContent = createElement(
			'div',
			null,
			heading(),
			BotManagementScreen( {
				page: botPage,
				selectedBotId,
				onCreate: onCreateBot,
				onUpdate: onUpdateBot,
				onDelete: onDeleteBot,
			} ),
			botPage.items.length === 0
				? undefined
				: BotKnowledgeBinding( {
						botId: selectedBotId ?? botPage.items[ 0 ]?.id ?? '',
						sources: botKnowledgeSources ?? [],
						sourcesStatus: botKnowledgeSourcesStatus,
						sourcesError: botKnowledgeSourcesError,
						retrieval: botRetrieval ?? {
							configured: false,
							source_id: null,
							source_title: null,
							collection_id: null,
							collection_ready: false,
						},
						status: botRetrievalStatus,
						error: botRetrievalError,
						onSave: onSaveBotKnowledge,
						onDisconnect: onDisconnectBotKnowledge,
				  } ),
			botAppearance === undefined
				? undefined
				: createElement(
						'details',
						{ className: 'wp-rag-ai-admin-advanced', open: true },
						createElement( 'summary', null, 'Advanced appearance' ),
						AppearanceCustomizer( {
							appearance: botAppearance,
							saving: botAppearanceSaving,
							error: botAppearanceError,
							onChange:
								onChangeBotAppearance ?? ( () => undefined ),
							onSave: onSaveBotAppearance ?? ( () => undefined ),
						} )
				  ),
			botDisplayRules === undefined
				? undefined
				: createElement(
						'details',
						{ className: 'wp-rag-ai-admin-advanced', open: true },
						createElement(
							'summary',
							null,
							'Advanced display rules'
						),
						DisplayRulesEditor( {
							config: botDisplayRules,
							saving: botDisplayRulesSaving,
							error: botDisplayRulesError,
							onChange:
								onChangeBotDisplayRules ?? ( () => undefined ),
							onSave:
								onSaveBotDisplayRules ?? ( () => undefined ),
						} )
				  )
		);
	} else if ( screen === 'knowledge' && knowledgePage !== undefined ) {
		screenContent = createElement(
			'div',
			null,
			heading(),
			KnowledgeManagementScreen( {
				page: knowledgePage,
				selectedSourceId: selectedKnowledgeSourceId,
				selectedDocumentKey: selectedKnowledgeDocumentKey,
				detail: knowledgeDetail,
				documents: knowledgeDocuments,
				chunks: knowledgeChunks,
				jobs: knowledgeJobs,
				mutationError: knowledgeJobMutationError,
				knowledgeWizardError,
				knowledgeSourceSubmitting,
				woocommerceAvailable,
				onCreateKnowledgeSource,
				onCancelJob: onCancelKnowledgeJob,
				onRetryJob: onRetryKnowledgeJob,
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
			heading(),
			ProviderSettingsScreen( {
				providerId,
				credential: providerCredential,
				models: providerModels,
				issue: providerIssue,
				onReplace: onReplaceProviderCredential,
			} )
		);
	} else if ( screen === 'playground' ) {
		screenContent = createElement(
			'div',
			null,
			createElement( 'h1', null, selectedLabel ),
			PlaygroundPanel( {
				state: playgroundState ?? { status: 'idle' },
				onSubmit: onSubmitPlayground,
			} )
		);
	}

	return screenContent;
};

const renderLegacyAdminShell = ( props: AdminShellProps ): unknown => {
	const { state, screen = 'onboarding' } = props;
	const createElement = window.wp.element.createElement;

	if ( state === 'loading' ) {
		if ( screen === 'knowledge' ) {
			return createElement(
				'div',
				{
					role: 'status',
					'aria-live': 'polite',
					'data-knowledge-state': 'loading',
				},
				'Loading knowledge data…'
			);
		}

		return createElement(
			'div',
			{ role: 'status', 'aria-live': 'polite' },
			'Loading administration data…'
		);
	}

	if ( state === 'error' ) {
		if ( screen === 'knowledge' ) {
			return createElement(
				'div',
				{ role: 'alert', 'data-knowledge-state': 'error' },
				'Knowledge data could not be loaded.'
			);
		}

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

	const navigation = ADMIN_SCREENS.map( ( item ) => {
		const linkProps: Record< string, unknown > = {
			href: `#/${ item.screen }`,
			key: item.screen,
		};

		if ( item.screen === screen ) {
			linkProps[ 'aria-current' ] = 'page';
		}

		return createElement( 'a', linkProps, item.label );
	} );

	return createElement(
		'div',
		{ 'data-admin-state': 'ready' },
		createElement(
			'nav',
			{ 'aria-label': 'Administration' },
			...navigation
		),
		createElement( 'main', null, renderLegacyScreenContent( props ) )
	);
};

const isModernReadiness = ( readiness: AdminReadiness ): boolean =>
	'source_count' in readiness &&
	'completed_index_present' in readiness &&
	'publishable_bot_present' in readiness;

const modernScreenFor = ( screen: AdminScreen ): ModernAdminScreen => {
	if ( screen === 'onboarding' || screen === 'overview' ) {
		return 'overview';
	}
	if ( screen === 'publish' ) {
		return 'publish';
	}
	return screen;
};

export const AdminShell = ( props: AdminShellProps ): unknown => {
	if (
		props.readiness !== undefined &&
		isModernReadiness( props.readiness )
	) {
		return ModernAdminShell( {
			state: props.state,
			screen: modernScreenFor( props.screen ?? 'onboarding' ),
			readiness: props.readiness,
			botId:
				props.screen === 'publish'
					? props.publishBotId
					: props.selectedBotId ?? props.botPage?.items[ 0 ]?.id,
			botPublishable: props.publishBotPublishable,
			botOptions: props.publishBotOptions,
			botStatus: props.publishBotStatus,
			botError: props.publishBotError,
			onSelectBot: props.onSelectPublishBot,
			legacyContent: renderLegacyScreenContent( props, false ),
		} );
	}

	return renderLegacyAdminShell( props );
};

const renderAdminShell = (
	root: Element,
	state: AdminShellState,
	screen: AdminScreen,
	onboardingStep?: OnboardingStep,
	onboardingIssue?: OnboardingIssue,
	botPage?: BotPage,
	selectedBotId?: string,
	botAppearance?: WidgetAppearance,
	botAppearanceSaving?: boolean,
	botAppearanceError?: string,
	onChangeBotAppearance?: ( next: WidgetAppearance ) => void,
	onSaveBotAppearance?: ( next: WidgetAppearance ) => void,
	botDisplayRules?: DisplayRulesConfig,
	botDisplayRulesSaving?: boolean,
	botDisplayRulesError?: string,
	onChangeBotDisplayRules?: ( next: DisplayRulesConfig ) => void,
	onSaveBotDisplayRules?: ( next: DisplayRulesConfig ) => void,
	knowledgePage?: KnowledgeSourcePage,
	selectedKnowledgeSourceId?: string,
	selectedKnowledgeDocumentKey?: string,
	knowledgeDetail?: KnowledgeSourceDetail,
	knowledgeDocuments?: KnowledgeDocumentPage,
	knowledgeChunks?: KnowledgeChunkPage,
	knowledgeJobs?: KnowledgeJobPage,
	knowledgeJobMutationError?: KnowledgeJobMutationError,
	knowledgeWizardError?: string,
	knowledgeSourceSubmitting?: boolean,
	woocommerceAvailable?: boolean,
	onCreateKnowledgeSource?: (
		draft: KnowledgeSourceDraft
	) => Promise< void >,
	onCancelKnowledgeJob?: ( job: KnowledgeJobItem ) => Promise< void >,
	onRetryKnowledgeJob?: ( job: KnowledgeJobItem ) => Promise< void >,
	providerId?: string,
	providerCredential?: ProviderCredentialState,
	providerModels?: ReadonlyArray< ProviderModelChoice >,
	providerIssue?: ProviderSettingsIssue,
	onCreateBot?: ( draft: BotDraft ) => Promise< void >,
	onUpdateBot?: ( bot: BotListItem, draft: BotDraft ) => Promise< void >,
	onDeleteBot?: ( bot: BotListItem ) => Promise< void >,
	onReplaceProviderCredential?: ( credential: string ) => Promise< void >,
	playgroundState?: PlaygroundControllerState,
	onSubmitPlayground?: ( request: PlaygroundRequestDraft ) => void,
	readiness?: AdminReadiness,
	botKnowledgeSources?: ReadonlyArray< KnowledgeSourceChoice >,
	botRetrieval?: BotRetrievalProjection,
	botRetrievalStatus?: BotKnowledgeBindingStatus,
	botRetrievalError?: string,
	botKnowledgeSourcesStatus?: BotKnowledgeSourcesStatus,
	botKnowledgeSourcesError?: string,
	onSaveBotKnowledge?: ( sourceId: number ) => void | Promise< void >,
	onDisconnectBotKnowledge?: () => void | Promise< void >,
	publishBotId?: string,
	publishBotOptions?: ReadonlyArray< PublishBotOption >,
	publishBotPublishable?: boolean,
	publishBotStatus?: PublishBotStatus,
	publishBotError?: string,
	onSelectPublishBot?: ( botId: string ) => void
): void => {
	const element = AdminShell( {
		state,
		screen,
		onboardingStep,
		onboardingIssue,
		botPage,
		botKnowledgeSources,
		botRetrieval,
		botRetrievalStatus,
		botRetrievalError,
		botKnowledgeSourcesStatus,
		botKnowledgeSourcesError,
		publishBotId,
		publishBotOptions,
		publishBotPublishable,
		publishBotStatus,
		publishBotError,
		onSelectPublishBot,
		onSaveBotKnowledge,
		onDisconnectBotKnowledge,
		selectedBotId,
		botAppearance,
		botAppearanceSaving,
		botAppearanceError,
		onChangeBotAppearance,
		onSaveBotAppearance,
		botDisplayRules,
		botDisplayRulesSaving,
		botDisplayRulesError,
		onChangeBotDisplayRules,
		onSaveBotDisplayRules,
		knowledgePage,
		selectedKnowledgeSourceId,
		selectedKnowledgeDocumentKey,
		knowledgeDetail,
		knowledgeDocuments,
		knowledgeChunks,
		knowledgeJobs,
		knowledgeJobMutationError,
		knowledgeWizardError,
		knowledgeSourceSubmitting,
		woocommerceAvailable,
		onCreateKnowledgeSource,
		onCancelKnowledgeJob,
		onRetryKnowledgeJob,
		providerId,
		providerCredential,
		providerModels,
		providerIssue,
		onCreateBot,
		onUpdateBot,
		onDeleteBot,
		onReplaceProviderCredential,
		playgroundState,
		onSubmitPlayground,
		readiness,
	} );
	const createRoot = window.wp.element.createRoot;

	if ( typeof createRoot === 'function' ) {
		let elementRoot = adminElementRoots.get( root );
		if ( elementRoot === undefined ) {
			elementRoot = createRoot( root );
			adminElementRoots.set( root, elementRoot );
		}
		elementRoot.render( element );
		return;
	}

	window.wp.element.render( element, root );
};

const stateFromReadiness = (): AdminShellState => 'ready';

type ReadinessRefreshResult = 'success' | 'stale' | 'failed';

export const bootstrapAdminApp = ( hash = window.location.hash ): boolean => {
	const root = document.getElementById( 'wp-rag-ai-chatbot-admin' );

	if ( root === null ) {
		return false;
	}

	let currentState: AdminShellState = 'ready';
	let currentReadiness: AdminReadiness | undefined;
	let readinessGeneration = 0;
	let currentOnboardingStep: OnboardingStep | undefined;
	let currentOnboardingIssue: OnboardingIssue | undefined;
	let currentBotPage: BotPage | undefined;
	let currentBotKnowledgeSources: KnowledgeSourceChoice[] = [];
	let currentBotKnowledgeSourcesStatus: BotKnowledgeSourcesStatus = 'loading';
	let currentBotKnowledgeSourcesError: string | undefined;
	let currentBotRetrieval: BotRetrievalProjection | undefined;
	let currentBotRetrievalStatus: BotKnowledgeBindingStatus = 'loading';
	let currentBotRetrievalError: string | undefined;
	let loadedBotRetrievalId: string | undefined;
	let botRetrievalGeneration = 0;
	let botKnowledgeSourcesGeneration = 0;
	let currentPublishBotId: string | undefined;
	let currentPublishBotOptions: PublishBotOption[] = [];
	let currentPublishBotPublishable = false;
	let currentPublishBotStatus: PublishBotStatus = 'loading';
	let currentPublishBotError: string | undefined;
	let publishBotGeneration = 0;
	let currentBotAppearance: WidgetAppearance | undefined;
	let currentBotAppearanceSaving = false;
	let currentBotAppearanceError: string | undefined;
	let loadedBotAppearanceId: string | undefined;
	let botAppearanceGeneration = 0;
	let currentBotDisplayRules: DisplayRulesConfig | undefined;
	let currentBotDisplayRulesSaving = false;
	let currentBotDisplayRulesError: string | undefined;
	let loadedBotDisplayRulesId: string | undefined;
	let botDisplayRulesGeneration = 0;
	let currentKnowledgePage: KnowledgeSourcePage | undefined;
	let currentKnowledgeDetail: KnowledgeSourceDetail | undefined;
	let currentKnowledgeDocuments: KnowledgeDocumentPage | undefined;
	let currentKnowledgeChunks: KnowledgeChunkPage | undefined;
	let currentKnowledgeJobs: KnowledgeJobPage | undefined;
	let currentKnowledgeJobMutationError: KnowledgeJobMutationError | undefined;
	let currentKnowledgeWizardError: string | undefined;
	let currentKnowledgeSourceSubmitting = false;
	let currentWooCommerceAvailable = false;
	let createKnowledgeSource: (
		draft: KnowledgeSourceDraft
	) => Promise< void > = async () => undefined;
	let cancelKnowledgeJob: (
		job: KnowledgeJobItem
	) => Promise< void > = async () => undefined;
	let retryKnowledgeJob: (
		job: KnowledgeJobItem
	) => Promise< void > = async () => undefined;
	let loadedKnowledgeSourceId: string | undefined;
	let loadedKnowledgeDocumentKey: string | undefined;
	let knowledgePageGeneration = 0;
	let knowledgeSelectionGeneration = 0;
	let knowledgeJobsGeneration = 0;
	let knowledgeJobMutationGeneration = 0;
	let knowledgeSourceMutationGeneration = 0;
	let currentProviderCredential: ProviderCredentialState | undefined;
	let currentProviderModels: ProviderModelChoice[] | undefined;
	let currentProviderIssue: ProviderSettingsIssue | undefined;
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
	let saveBotKnowledge: ( sourceId: number ) => Promise< void > = async () =>
		undefined;
	let disconnectBotKnowledge: () => Promise< void > = async () => undefined;
	const selectPublishBot = ( botId: string ): void => {
		window.location.hash = `#/publish/${ encodeURIComponent( botId ) }`;
	};
	let changeBotAppearance: ( next: WidgetAppearance ) => void = () =>
		undefined;
	let saveBotAppearance: ( next: WidgetAppearance ) => void = () => undefined;
	let changeBotDisplayRules: ( next: DisplayRulesConfig ) => void = () =>
		undefined;
	let saveBotDisplayRules: ( next: DisplayRulesConfig ) => void = () =>
		undefined;
	let replaceProviderCredential: (
		credential: string
	) => Promise< void > = async () => undefined;
	let currentPlaygroundState: PlaygroundControllerState = { status: 'idle' };
	let submitPlayground: (
		request: PlaygroundRequestDraft
	) => Promise< void > = async () => undefined;
	const currentHash = (): string => window.location.hash || hash;
	const activeBotId = (): string | undefined =>
		resolveSelectedBotId( currentHash() ) ?? currentBotPage?.items[ 0 ]?.id;
	const renderState = ( state: AdminShellState ): void => {
		currentState = state;
		const providerId = resolveSelectedProviderId( currentHash() );
		const selectedBotId = resolveSelectedBotId( currentHash() );
		const currentActiveBotId = activeBotId();
		const selectedKnowledgeSourceId =
			resolveSelectedPersistedKnowledgeSourceId( currentHash() );
		const selectedKnowledgeDocumentKey =
			resolveSelectedKnowledgeDocumentKey( currentHash() );
		renderAdminShell(
			root,
			state,
			resolveAdminScreen( currentHash() ),
			currentOnboardingStep,
			currentOnboardingIssue,
			currentBotPage,
			selectedBotId,
			currentActiveBotId === loadedBotAppearanceId
				? currentBotAppearance
				: undefined,
			currentBotAppearanceSaving,
			currentActiveBotId === loadedBotAppearanceId
				? currentBotAppearanceError
				: undefined,
			changeBotAppearance,
			saveBotAppearance,
			currentActiveBotId === loadedBotDisplayRulesId
				? currentBotDisplayRules
				: undefined,
			currentBotDisplayRulesSaving,
			currentActiveBotId === loadedBotDisplayRulesId
				? currentBotDisplayRulesError
				: undefined,
			changeBotDisplayRules,
			saveBotDisplayRules,
			currentKnowledgePage,
			resolveSelectedKnowledgeSourceId( currentHash() ),
			selectedKnowledgeDocumentKey,
			selectedKnowledgeSourceId === loadedKnowledgeSourceId
				? currentKnowledgeDetail
				: undefined,
			selectedKnowledgeSourceId === loadedKnowledgeSourceId
				? currentKnowledgeDocuments
				: undefined,
			selectedKnowledgeSourceId === loadedKnowledgeSourceId &&
				selectedKnowledgeDocumentKey === loadedKnowledgeDocumentKey
				? currentKnowledgeChunks
				: undefined,
			resolveHashPath( currentHash() ) === 'knowledge'
				? currentKnowledgeJobs
				: undefined,
			resolveHashPath( currentHash() ) === 'knowledge'
				? currentKnowledgeJobMutationError
				: undefined,
			resolveHashPath( currentHash() ) === 'knowledge'
				? currentKnowledgeWizardError
				: undefined,
			resolveHashPath( currentHash() ) === 'knowledge'
				? currentKnowledgeSourceSubmitting
				: false,
			currentWooCommerceAvailable,
			createKnowledgeSource,
			cancelKnowledgeJob,
			retryKnowledgeJob,
			providerId,
			providerId === loadedProviderId
				? currentProviderCredential
				: undefined,
			providerId === loadedProviderModelsId
				? currentProviderModels
				: undefined,
			providerId === loadedProviderModelsId
				? currentProviderIssue
				: undefined,
			createBot,
			updateBot,
			deleteBot,
			replaceProviderCredential,
			currentPlaygroundState,
			( request ) => {
				void submitPlayground( request );
			},
			currentReadiness,
			currentBotKnowledgeSources,
			resolveAdminScreen( currentHash() ) === 'bots' &&
				currentActiveBotId !== undefined &&
				( currentActiveBotId === loadedBotRetrievalId ||
					currentBotRetrievalStatus === 'loading' )
				? currentBotRetrieval
				: undefined,
			currentBotRetrievalStatus,
			resolveAdminScreen( currentHash() ) === 'bots' &&
				currentActiveBotId !== undefined &&
				( currentActiveBotId === loadedBotRetrievalId ||
					currentBotRetrievalStatus === 'loading' )
				? currentBotRetrievalError
				: undefined,
			currentBotKnowledgeSourcesStatus,
			currentBotKnowledgeSourcesError,
			saveBotKnowledge,
			disconnectBotKnowledge,
			currentPublishBotId,
			currentPublishBotOptions,
			currentPublishBotPublishable,
			currentPublishBotStatus,
			currentPublishBotError,
			selectPublishBot
		);
	};

	const config = window.wpRagAiChatbotAdminConfig;
	const fetcher = window.fetch;

	if ( config === undefined ) {
		renderState( 'error' );
		return true;
	}
	currentWooCommerceAvailable = config.woocommerceAvailable === true;

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
	const refreshReadiness = async (
		render = true
	): Promise< ReadinessRefreshResult > => {
		const requestGeneration = ++readinessGeneration;

		try {
			const readiness = await client.request< AdminOnboardingReadiness >(
				'/admin/onboarding/readiness'
			);

			if ( requestGeneration !== readinessGeneration ) {
				return 'stale';
			}

			currentReadiness = readiness;
			currentOnboardingStep = readiness.next_step;
			currentOnboardingIssue = readiness.issue;
			if ( render ) {
				renderState( stateFromReadiness() );
			}

			return 'success';
		} catch {
			if ( requestGeneration !== readinessGeneration ) {
				return 'stale';
			}
			return 'failed';
		}
	};
	const playgroundRuntime = createPlaygroundRuntime( client, ( state ) => {
		currentPlaygroundState = state;
		renderState( stateFromReadiness() );
	} );
	submitPlayground = ( request: PlaygroundRequestDraft ): Promise< void > =>
		playgroundRuntime.submit( request );
	const refreshBotPage = async (
		page = resolveBotPage( currentHash() )
	): Promise< void > => {
		currentBotPage = await client.request< BotPage >(
			`/admin/bots?page=${ page }&per_page=20`
		);
	};
	const emptyBotRetrieval = (): BotRetrievalProjection => ( {
		configured: false,
		source_id: null,
		source_title: null,
		collection_id: null,
		collection_ready: false,
	} );
	const normalizeBotRetrieval = (
		value: unknown
	): BotRetrievalProjection => {
		if ( typeof value !== 'object' || value === null ) {
			return emptyBotRetrieval();
		}
		const candidate = value as Record< string, unknown >;
		return {
			configured: candidate.configured === true,
			source_id:
				typeof candidate.source_id === 'number' &&
				Number.isSafeInteger( candidate.source_id )
					? candidate.source_id
					: null,
			source_title:
				typeof candidate.source_title === 'string'
					? candidate.source_title
					: null,
			collection_id:
				typeof candidate.collection_id === 'string'
					? candidate.collection_id
					: null,
			collection_ready: candidate.collection_ready === true,
		};
	};
	const refreshBotKnowledgeSources = async (
		botId: string
	): Promise< boolean > => {
		const requestGeneration = ++botKnowledgeSourcesGeneration;
		const isCurrentRequest = (): boolean =>
			requestGeneration === botKnowledgeSourcesGeneration &&
			resolveAdminScreen( currentHash() ) === 'bots' &&
			activeBotId() === botId;

		currentBotKnowledgeSourcesStatus = 'loading';
		currentBotKnowledgeSourcesError = undefined;
		try {
			const page = await client.request< Partial< KnowledgeSourcePage > >(
				'/admin/knowledge/sources?page=1&per_page=100'
			);
			if ( ! isCurrentRequest() ) {
				return false;
			}
			currentBotKnowledgeSources = Array.isArray( page.items )
				? page.items
				: [];
			currentBotKnowledgeSourcesStatus = 'ready';
			return true;
		} catch {
			if ( ! isCurrentRequest() ) {
				return false;
			}
			currentBotKnowledgeSources = [];
			currentBotKnowledgeSourcesStatus = 'error';
			currentBotKnowledgeSourcesError =
				'Knowledge sources could not be loaded. Try again.';
			return true;
		}
	};
	const refreshBotRetrieval = async ( botId: string ): Promise< boolean > => {
		const requestGeneration = ++botRetrievalGeneration;
		const isCurrentRequest = (): boolean =>
			requestGeneration === botRetrievalGeneration &&
			resolveAdminScreen( currentHash() ) === 'bots' &&
			activeBotId() === botId;

		currentBotRetrievalStatus = 'loading';
		currentBotRetrievalError = undefined;
		currentBotRetrieval = emptyBotRetrieval();
		loadedBotRetrievalId = undefined;
		try {
			const response = await client.request< { retrieval?: unknown } >(
				`/admin/bots/${ encodeURIComponent( botId ) }/retrieval`
			);
			if ( ! isCurrentRequest() ) {
				return false;
			}
			currentBotRetrieval = normalizeBotRetrieval( response.retrieval );
			loadedBotRetrievalId = botId;
			currentBotRetrievalStatus = 'ready';
			return true;
		} catch {
			if ( ! isCurrentRequest() ) {
				return false;
			}
			currentBotRetrieval = emptyBotRetrieval();
			loadedBotRetrievalId = botId;
			currentBotRetrievalStatus = 'error';
			currentBotRetrievalError =
				'Knowledge connection could not be loaded. Try again.';
			return true;
		}
	};
	const normalizeBot = ( value: unknown ): BotListItem | undefined => {
		if ( typeof value !== 'object' || value === null ) {
			return undefined;
		}
		const candidate = value as Record< string, unknown >;
		if (
			typeof candidate.id !== 'string' ||
			typeof candidate.name !== 'string' ||
			typeof candidate.provider_id !== 'string' ||
			typeof candidate.model_id !== 'string' ||
			typeof candidate.enabled !== 'boolean'
		) {
			return undefined;
		}
		return {
			id: candidate.id,
			name: candidate.name,
			enabled: candidate.enabled,
			provider_id: candidate.provider_id,
			model_id: candidate.model_id,
			version:
				typeof candidate.version === 'number' ? candidate.version : 0,
			created_at:
				typeof candidate.created_at === 'string'
					? candidate.created_at
					: '',
			updated_at:
				typeof candidate.updated_at === 'string'
					? candidate.updated_at
					: '',
		};
	};
	const botModelIsAvailable = (
		value: unknown,
		modelId: string
	): boolean => {
		if ( typeof value !== 'object' || value === null ) {
			return false;
		}
		const models = ( value as { models?: unknown } ).models;
		return (
			Array.isArray( models ) &&
			models.some(
				( model ) =>
					typeof model === 'object' &&
					model !== null &&
					( model as { model_id?: unknown } ).model_id === modelId
			)
		);
	};
	const refreshPublishState = async (): Promise< boolean > => {
		const requestGeneration = ++publishBotGeneration;
		const isCurrentRequest = (): boolean =>
			requestGeneration === publishBotGeneration &&
			resolveAdminScreen( currentHash() ) === 'publish';
		const selectedId = resolveSelectedPublishBotId( currentHash() );

		currentPublishBotStatus = 'loading';
		currentPublishBotError = undefined;
		currentPublishBotId = undefined;
		currentPublishBotPublishable = false;

		try {
			let bot: BotListItem | undefined;
			if ( selectedId !== undefined ) {
				const result = await client.request< { bot?: unknown } >(
					`/admin/bots/${ encodeURIComponent( selectedId ) }`
				);
				bot = normalizeBot( result.bot );
				if ( bot === undefined || bot.id !== selectedId ) {
					throw new Error( 'Selected chatbot was not found.' );
				}
				currentPublishBotOptions = [ { id: bot.id, name: bot.name } ];
			} else {
				const page = await client.request< Partial< BotPage > >(
					'/admin/bots?page=1&per_page=20'
				);
				const bots = Array.isArray( page.items )
					? page.items.flatMap( ( item ) => {
							const normalized = normalizeBot( item );
							return normalized === undefined
								? []
								: [ normalized ];
					  } )
					: [];
				currentPublishBotOptions = bots.map( ( item ) => ( {
					id: item.id,
					name: item.name,
				} ) );
				bot = bots[ 0 ];
			}

			if ( ! isCurrentRequest() ) {
				return false;
			}
			if ( bot === undefined ) {
				currentPublishBotStatus = 'ready';
				return true;
			}

			currentPublishBotId = bot.id;
			const [ retrievalResponse, modelsResponse ] = await Promise.all( [
				client.request< { retrieval?: unknown } >(
					`/admin/bots/${ encodeURIComponent( bot.id ) }/retrieval`
				),
				client.request< unknown >(
					`/admin/models?provider_id=${ encodeURIComponent(
						bot.provider_id
					) }&purpose=generation`
				),
			] );
			if ( ! isCurrentRequest() ) {
				return false;
			}
			const publishRetrieval = normalizeBotRetrieval(
				retrievalResponse.retrieval
			);
			const modelAvailable = botModelIsAvailable(
				modelsResponse,
				bot.model_id
			);
			currentPublishBotPublishable =
				bot.enabled &&
				modelAvailable &&
				publishRetrieval.configured &&
				publishRetrieval.collection_ready;
			currentPublishBotStatus = 'ready';
			return true;
		} catch {
			if ( ! isCurrentRequest() ) {
				return false;
			}
			currentPublishBotPublishable = false;
			currentPublishBotStatus = 'error';
			currentPublishBotError =
				'Selected chatbot readiness could not be loaded. Try again.';
			return true;
		}
	};
	const refreshBotAppearance = async (
		botId: string
	): Promise< boolean > => {
		const requestGeneration = ++botAppearanceGeneration;
		const isCurrentRequest = (): boolean =>
			requestGeneration === botAppearanceGeneration &&
			resolveAdminScreen( currentHash() ) === 'bots' &&
			activeBotId() === botId;

		currentBotAppearanceError = undefined;

		try {
			const response = await client.request< { appearance: unknown } >(
				`/admin/bots/${ encodeURIComponent( botId ) }/appearance`
			);

			if ( ! isCurrentRequest() ) {
				return false;
			}

			currentBotAppearance = normalizeWidgetAppearance(
				response.appearance
			);
			loadedBotAppearanceId = botId;
			return true;
		} catch {
			if ( ! isCurrentRequest() ) {
				return false;
			}

			currentBotAppearance = normalizeWidgetAppearance( undefined );
			currentBotAppearanceError =
				'Appearance settings could not be loaded.';
			loadedBotAppearanceId = botId;
			return true;
		}
	};
	const refreshBotDisplayRules = async (
		botId: string
	): Promise< boolean > => {
		const requestGeneration = ++botDisplayRulesGeneration;
		const isCurrentRequest = (): boolean =>
			requestGeneration === botDisplayRulesGeneration &&
			resolveAdminScreen( currentHash() ) === 'bots' &&
			activeBotId() === botId;

		currentBotDisplayRulesError = undefined;

		try {
			const response = await client.request< { display_rules: unknown } >(
				`/admin/bots/${ encodeURIComponent( botId ) }/display-rules`
			);

			if ( ! isCurrentRequest() ) {
				return false;
			}

			currentBotDisplayRules = normalizeDisplayRules(
				response.display_rules
			);
			loadedBotDisplayRulesId = botId;
			return true;
		} catch {
			if ( ! isCurrentRequest() ) {
				return false;
			}

			currentBotDisplayRules = normalizeDisplayRules( undefined );
			currentBotDisplayRulesError = 'Display rules could not be loaded.';
			loadedBotDisplayRulesId = botId;
			return true;
		}
	};
	changeBotAppearance = ( next: WidgetAppearance ): void => {
		const botId = activeBotId();

		if ( botId === undefined || botId !== loadedBotAppearanceId ) {
			return;
		}

		currentBotAppearance = normalizeWidgetAppearance( next );
		currentBotAppearanceError = undefined;
		renderState( stateFromReadiness() );
	};
	saveBotAppearance = ( next: WidgetAppearance ): void => {
		const botId = activeBotId();

		if ( botId === undefined ) {
			return;
		}

		const requestGeneration = ++botAppearanceGeneration;
		const isCurrentRequest = (): boolean =>
			requestGeneration === botAppearanceGeneration &&
			resolveAdminScreen( currentHash() ) === 'bots' &&
			activeBotId() === botId;
		const normalized = normalizeWidgetAppearance( next );
		currentBotAppearance = normalized;
		currentBotAppearanceSaving = true;
		currentBotAppearanceError = undefined;
		loadedBotAppearanceId = botId;
		renderState( stateFromReadiness() );

		void client
			.request< { appearance: unknown } >(
				`/admin/bots/${ encodeURIComponent( botId ) }/appearance`,
				{
					method: 'PUT',
					body: normalized,
				}
			)
			.then( ( response ) => {
				if ( ! isCurrentRequest() ) {
					return;
				}

				currentBotAppearance = normalizeWidgetAppearance(
					response.appearance
				);
				currentBotAppearanceError = undefined;
			} )
			.catch( () => {
				if ( isCurrentRequest() ) {
					currentBotAppearanceError =
						'Appearance settings could not be saved.';
				}
			} )
			.finally( () => {
				if ( isCurrentRequest() ) {
					currentBotAppearanceSaving = false;
					renderState( stateFromReadiness() );
				}
			} );
	};
	changeBotDisplayRules = ( next: DisplayRulesConfig ): void => {
		const botId = activeBotId();

		if ( botId === undefined || botId !== loadedBotDisplayRulesId ) {
			return;
		}

		currentBotDisplayRules = normalizeDisplayRules( next );
		currentBotDisplayRulesError = undefined;
		renderState( stateFromReadiness() );
	};
	saveBotDisplayRules = ( next: DisplayRulesConfig ): void => {
		const botId = activeBotId();

		if ( botId === undefined ) {
			return;
		}

		const requestGeneration = ++botDisplayRulesGeneration;
		const isCurrentRequest = (): boolean =>
			requestGeneration === botDisplayRulesGeneration &&
			resolveAdminScreen( currentHash() ) === 'bots' &&
			activeBotId() === botId;
		const normalized = normalizeDisplayRules( next );
		currentBotDisplayRules = normalized;
		currentBotDisplayRulesSaving = true;
		currentBotDisplayRulesError = undefined;
		loadedBotDisplayRulesId = botId;
		renderState( stateFromReadiness() );

		void client
			.request< { display_rules: unknown } >(
				`/admin/bots/${ encodeURIComponent( botId ) }/display-rules`,
				{
					method: 'PUT',
					body: normalized,
				}
			)
			.then( ( response ) => {
				if ( ! isCurrentRequest() ) {
					return;
				}

				currentBotDisplayRules = normalizeDisplayRules(
					response.display_rules
				);
				currentBotDisplayRulesError = undefined;
			} )
			.catch( () => {
				if ( isCurrentRequest() ) {
					currentBotDisplayRulesError =
						'Display rules could not be saved.';
				}
			} )
			.finally( () => {
				if ( isCurrentRequest() ) {
					currentBotDisplayRulesSaving = false;
					renderState( stateFromReadiness() );
				}
			} );
	};
	const refreshKnowledgePage = async (
		page = resolveKnowledgePage( currentHash() )
	): Promise< boolean > => {
		const requestGeneration = ++knowledgePageGeneration;
		const isCurrentRequest = (): boolean =>
			requestGeneration === knowledgePageGeneration &&
			resolveAdminScreen( currentHash() ) === 'knowledge' &&
			resolveKnowledgePage( currentHash() ) === page;

		try {
			const knowledgePage = await client.request< KnowledgeSourcePage >(
				`/admin/knowledge/sources?page=${ page }&per_page=20`
			);

			if ( ! isCurrentRequest() ) {
				return false;
			}

			currentKnowledgePage = knowledgePage;
			return true;
		} catch ( error ) {
			if ( ! isCurrentRequest() ) {
				return false;
			}

			throw error;
		}
	};
	const refreshKnowledgeJobs = async (): Promise< boolean > => {
		const requestGeneration = ++knowledgeJobsGeneration;
		const requestHash = currentHash();
		const isCurrentRequest = (): boolean =>
			requestGeneration === knowledgeJobsGeneration &&
			currentHash() === requestHash &&
			resolveAdminScreen( currentHash() ) === 'knowledge' &&
			resolveHashPath( currentHash() ) === 'knowledge';

		try {
			const jobs = await client.request< KnowledgeJobPage >(
				'/admin/knowledge/jobs?page=1&per_page=20'
			);
			if ( ! isCurrentRequest() ) {
				return false;
			}
			currentKnowledgeJobs = jobs;
			return true;
		} catch ( error ) {
			if ( ! isCurrentRequest() ) {
				return false;
			}
			throw error;
		}
	};
	const refreshKnowledgeDetail = async (
		sourceId: string
	): Promise< {
		detail: KnowledgeSourceDetail;
		documents: KnowledgeDocumentPage;
	} > => {
		const encodedSourceId = encodeURIComponent( sourceId );
		const detail = await client.request< KnowledgeSourceDetail >(
			`/admin/knowledge/sources/${ encodedSourceId }`
		);
		const documents = await client.request< KnowledgeDocumentPage >(
			`/admin/knowledge/sources/${ encodedSourceId }/documents?page=1&per_page=20`
		);

		return { detail, documents };
	};
	const refreshKnowledgeChunks = async (
		sourceId: string,
		documentKey: string,
		requestGeneration = knowledgeSelectionGeneration
	): Promise< boolean > => {
		const chunks = await client.request< KnowledgeChunkPage >(
			`/admin/knowledge/sources/${ encodeURIComponent(
				sourceId
			) }/documents/${ encodeURIComponent(
				documentKey
			) }/chunks?page=1&per_page=20`
		);

		if (
			requestGeneration !== knowledgeSelectionGeneration ||
			resolveSelectedPersistedKnowledgeSourceId( currentHash() ) !==
				sourceId ||
			resolveSelectedKnowledgeDocumentKey( currentHash() ) !== documentKey
		) {
			return false;
		}

		currentKnowledgeChunks = chunks;
		loadedKnowledgeDocumentKey = documentKey;
		return true;
	};
	const refreshKnowledgeSelection = async (
		sourceId: string
	): Promise< boolean > => {
		const requestGeneration = ++knowledgeSelectionGeneration;

		try {
			const { detail, documents } =
				await refreshKnowledgeDetail( sourceId );

			if (
				requestGeneration !== knowledgeSelectionGeneration ||
				resolveSelectedPersistedKnowledgeSourceId( currentHash() ) !==
					sourceId
			) {
				return false;
			}

			currentKnowledgeDetail = detail;
			currentKnowledgeDocuments = documents;
			loadedKnowledgeSourceId = sourceId;

			const documentKey = resolveSelectedKnowledgeDocumentKey(
				currentHash()
			);

			if ( documentKey !== undefined ) {
				return refreshKnowledgeChunks(
					sourceId,
					documentKey,
					requestGeneration
				);
			}

			currentKnowledgeChunks = undefined;
			loadedKnowledgeDocumentKey = undefined;
			return true;
		} catch ( error ) {
			if ( requestGeneration !== knowledgeSelectionGeneration ) {
				return false;
			}

			throw error;
		}
	};
	createKnowledgeSource = async (
		draft: KnowledgeSourceDraft
	): Promise< void > => {
		if ( currentKnowledgeSourceSubmitting ) {
			return;
		}

		knowledgeJobMutationGeneration += 1;
		const requestGeneration = ++knowledgeSourceMutationGeneration;
		currentKnowledgeSourceSubmitting = true;
		currentKnowledgeWizardError = undefined;
		renderState( stateFromReadiness() );

		try {
			const sourceRequest = buildKnowledgeSourceRequest( draft );
			let response: { error?: { code?: unknown } };
			if ( sourceRequest.kind === 'formData' ) {
				response = await client.requestFormData< {
					source: unknown;
					job: unknown;
					error?: { code?: unknown };
				} >( '/admin/knowledge/sources', sourceRequest.body );
			} else {
				response = await client.request< {
					source: unknown;
					job: unknown;
					error?: { code?: unknown };
				} >( '/admin/knowledge/sources', {
					method: 'POST',
					body: sourceRequest.body,
				} );
			}
			if (
				typeof response.error?.code === 'string' &&
				response.error.code !== ''
			) {
				throw new AdminApiError( 400, response.error.code );
			}

			if (
				requestGeneration !== knowledgeSourceMutationGeneration ||
				resolveAdminScreen( currentHash() ) !== 'knowledge'
			) {
				return;
			}

			const refreshed = await refreshKnowledgePage(
				resolveKnowledgePage( currentHash() )
			);
			if (
				! refreshed ||
				requestGeneration !== knowledgeSourceMutationGeneration
			) {
				return;
			}
			if ( ! ( await refreshKnowledgeJobs() ) ) {
				return;
			}
			if ( requestGeneration === knowledgeSourceMutationGeneration ) {
				currentKnowledgeWizardError = undefined;
			}
		} catch ( error ) {
			if ( requestGeneration === knowledgeSourceMutationGeneration ) {
				currentKnowledgeWizardError =
					knowledgeSourceErrorMessage( error );
			}
		} finally {
			if ( requestGeneration === knowledgeSourceMutationGeneration ) {
				currentKnowledgeSourceSubmitting = false;
				renderState( stateFromReadiness() );
			}
		}
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
		currentProviderIssue = undefined;
		loadedProviderModelsId = providerId;
	};
	const refreshProviderModelsState = async (
		providerId: string
	): Promise< void > => {
		try {
			await refreshProviderModels( providerId );
		} catch ( error ) {
			const issue = providerSettingsIssueFromError( error );

			if ( issue === undefined ) {
				throw error;
			}

			currentProviderModels = undefined;
			currentProviderIssue = issue;
			loadedProviderModelsId = providerId;
		}
	};
	const refreshProviderSettings = async (
		providerId: string
	): Promise< void > => {
		currentProviderIssue = undefined;
		await refreshProviderCredential( providerId );
		await refreshProviderModelsState( providerId );
	};

	if ( activeHashChangeHandler !== null ) {
		window.removeEventListener( 'hashchange', activeHashChangeHandler );
	}

	activeHashChangeHandler = () => {
		if ( ! root.isConnected ) {
			return;
		}
		knowledgeJobsGeneration += 1;
		knowledgeJobMutationGeneration += 1;

		const screen = resolveAdminScreen( currentHash() );

		if ( screen !== 'bots' ) {
			botRetrievalGeneration += 1;
			botKnowledgeSourcesGeneration += 1;
			currentBotRetrieval = undefined;
			currentBotRetrievalStatus = 'loading';
			currentBotRetrievalError = undefined;
			loadedBotRetrievalId = undefined;
			currentBotKnowledgeSources = [];
			currentBotKnowledgeSourcesStatus = 'loading';
			currentBotKnowledgeSourcesError = undefined;
			botAppearanceGeneration += 1;
			currentBotAppearance = undefined;
			currentBotAppearanceSaving = false;
			currentBotAppearanceError = undefined;
			loadedBotAppearanceId = undefined;
			botDisplayRulesGeneration += 1;
			currentBotDisplayRules = undefined;
			currentBotDisplayRulesSaving = false;
			currentBotDisplayRulesError = undefined;
			loadedBotDisplayRulesId = undefined;
		}

		if ( screen !== 'publish' ) {
			publishBotGeneration += 1;
			currentPublishBotId = undefined;
			currentPublishBotOptions = [];
			currentPublishBotPublishable = false;
			currentPublishBotStatus = 'loading';
			currentPublishBotError = undefined;
		}

		if ( screen === 'overview' ) {
			void refreshReadiness();
			return;
		}

		if ( screen !== 'knowledge' ) {
			knowledgePageGeneration += 1;
			knowledgeSelectionGeneration += 1;
			knowledgeSourceMutationGeneration += 1;
			currentKnowledgePage = undefined;
			currentKnowledgeDetail = undefined;
			currentKnowledgeDocuments = undefined;
			currentKnowledgeChunks = undefined;
			currentKnowledgeJobs = undefined;
			currentKnowledgeJobMutationError = undefined;
			currentKnowledgeWizardError = undefined;
			currentKnowledgeSourceSubmitting = false;
			loadedKnowledgeSourceId = undefined;
			loadedKnowledgeDocumentKey = undefined;
		}

		if ( screen !== 'playground' ) {
			currentPlaygroundState = { status: 'idle' };
		}

		const targetPage = resolveBotPage( currentHash() );

		if (
			screen === 'bots' &&
			( currentBotPage === undefined ||
				currentBotPage.page !== targetPage )
		) {
			void refreshBotPage( targetPage )
				.then( async () => {
					const botId = activeBotId();
					if ( botId !== undefined ) {
						await Promise.all( [
							refreshBotAppearance( botId ),
							refreshBotDisplayRules( botId ),
							refreshBotKnowledgeSources( botId ),
							refreshBotRetrieval( botId ),
						] );
					}
				} )
				.then( () => renderState( stateFromReadiness() ) )
				.catch( () => renderState( 'error' ) );
			return;
		}

		if ( screen === 'publish' ) {
			void refreshPublishState().then( ( isCurrent ) => {
				if ( isCurrent ) {
					renderState( stateFromReadiness() );
				}
			} );
			renderState( stateFromReadiness() );
			return;
		}

		const currentActiveBotId = activeBotId();

		if (
			screen === 'bots' &&
			currentActiveBotId !== undefined &&
			( currentActiveBotId !== loadedBotAppearanceId ||
				currentActiveBotId !== loadedBotDisplayRulesId ||
				currentBotRetrievalStatus !== 'ready' ||
				currentBotKnowledgeSourcesStatus !== 'ready' )
		) {
			currentBotAppearance = undefined;
			currentBotAppearanceSaving = false;
			currentBotAppearanceError = undefined;
			currentBotDisplayRules = undefined;
			currentBotDisplayRulesSaving = false;
			currentBotDisplayRulesError = undefined;
			currentBotRetrieval = undefined;
			currentBotRetrievalStatus = 'loading';
			currentBotRetrievalError = undefined;
			loadedBotRetrievalId = undefined;
			currentBotKnowledgeSourcesStatus = 'loading';
			currentBotKnowledgeSourcesError = undefined;
			renderState( currentState );
			void Promise.all( [
				refreshBotAppearance( currentActiveBotId ),
				refreshBotDisplayRules( currentActiveBotId ),
				refreshBotKnowledgeSources( currentActiveBotId ),
				refreshBotRetrieval( currentActiveBotId ),
			] ).then( ( results ) => {
				if ( results.some( Boolean ) ) {
					renderState( stateFromReadiness() );
				}
			} );
			return;
		}

		const targetKnowledgePage = resolveKnowledgePage( currentHash() );
		const selectedKnowledgeSourceId =
			resolveSelectedPersistedKnowledgeSourceId( currentHash() );

		if (
			screen === 'knowledge' &&
			( currentKnowledgePage === undefined ||
				currentKnowledgePage.page !== targetKnowledgePage )
		) {
			currentKnowledgePage = undefined;
			currentKnowledgeDetail = undefined;
			currentKnowledgeDocuments = undefined;
			currentKnowledgeChunks = undefined;
			currentKnowledgeJobs = undefined;
			currentKnowledgeJobMutationError = undefined;
			loadedKnowledgeSourceId = undefined;
			loadedKnowledgeDocumentKey = undefined;
			void refreshKnowledgePage( targetKnowledgePage )
				.then( async ( isCurrentPage ) => {
					if ( ! isCurrentPage ) {
						return false;
					}
					if ( resolveHashPath( currentHash() ) === 'knowledge' ) {
						const isCurrentJobs = await refreshKnowledgeJobs();
						if ( ! isCurrentJobs ) {
							return false;
						}
					}
					if ( selectedKnowledgeSourceId !== undefined ) {
						await refreshKnowledgeSelection(
							selectedKnowledgeSourceId
						);
					}

					return true;
				} )
				.then( ( isCurrentPage ) => {
					if ( isCurrentPage ) {
						renderState( stateFromReadiness() );
					}
				} )
				.catch( () => renderState( 'error' ) );
			return;
		}

		if (
			screen === 'knowledge' &&
			resolveHashPath( currentHash() ) === 'knowledge' &&
			currentKnowledgeJobs === undefined
		) {
			void refreshKnowledgeJobs()
				.then( ( isCurrentJobs ) => {
					if ( isCurrentJobs ) {
						renderState( stateFromReadiness() );
					}
				} )
				.catch( () => renderState( 'error' ) );
			return;
		}

		if (
			screen === 'knowledge' &&
			selectedKnowledgeSourceId !== loadedKnowledgeSourceId
		) {
			currentKnowledgeDetail = undefined;
			currentKnowledgeDocuments = undefined;
			currentKnowledgeChunks = undefined;
			loadedKnowledgeSourceId = undefined;
			loadedKnowledgeDocumentKey = undefined;

			if ( selectedKnowledgeSourceId !== undefined ) {
				void refreshKnowledgeSelection( selectedKnowledgeSourceId )
					.then( () => renderState( stateFromReadiness() ) )
					.catch( () => renderState( 'error' ) );
				return;
			}
		}

		const selectedKnowledgeDocumentKey =
			resolveSelectedKnowledgeDocumentKey( currentHash() );

		if (
			screen === 'knowledge' &&
			selectedKnowledgeSourceId !== undefined &&
			selectedKnowledgeSourceId === loadedKnowledgeSourceId &&
			selectedKnowledgeDocumentKey !== loadedKnowledgeDocumentKey
		) {
			currentKnowledgeChunks = undefined;
			loadedKnowledgeDocumentKey = undefined;

			if ( selectedKnowledgeDocumentKey !== undefined ) {
				void refreshKnowledgeChunks(
					selectedKnowledgeSourceId,
					selectedKnowledgeDocumentKey
				)
					.then( () => renderState( stateFromReadiness() ) )
					.catch( () => renderState( 'error' ) );
				return;
			}
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
			currentProviderIssue = undefined;
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
			if ( ( await refreshReadiness() ) === 'failed' ) {
				renderState( stateFromReadiness() );
			}
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
			if ( ( await refreshReadiness() ) === 'failed' ) {
				renderState( stateFromReadiness() );
			}
		} catch {
			renderState( 'error' );
		}
	};
	saveBotKnowledge = async ( sourceId: number ): Promise< void > => {
		const botId = activeBotId();
		if ( botId === undefined ) {
			return;
		}
		const requestGeneration = ++botRetrievalGeneration;
		const isCurrentRequest = (): boolean =>
			requestGeneration === botRetrievalGeneration &&
			resolveAdminScreen( currentHash() ) === 'bots' &&
			activeBotId() === botId;

		currentBotRetrievalStatus = 'saving';
		currentBotRetrievalError = undefined;
		renderState( stateFromReadiness() );
		try {
			const response = ( await saveBotKnowledgeBinding(
				client,
				botId,
				sourceId
			) ) as {
				retrieval?: unknown;
			};
			if ( ! isCurrentRequest() ) {
				return;
			}
			currentBotRetrieval = normalizeBotRetrieval( response.retrieval );
			loadedBotRetrievalId = botId;
			currentBotRetrievalStatus = 'ready';
			if ( ( await refreshReadiness() ) === 'failed' ) {
				renderState( stateFromReadiness() );
			}
		} catch {
			if ( ! isCurrentRequest() ) {
				return;
			}
			currentBotRetrievalStatus = 'error';
			currentBotRetrievalError =
				'Knowledge connection could not be saved. Try again.';
			renderState( stateFromReadiness() );
		}
	};
	disconnectBotKnowledge = async (): Promise< void > => {
		const botId = activeBotId();
		if ( botId === undefined ) {
			return;
		}
		const requestGeneration = ++botRetrievalGeneration;
		const isCurrentRequest = (): boolean =>
			requestGeneration === botRetrievalGeneration &&
			resolveAdminScreen( currentHash() ) === 'bots' &&
			activeBotId() === botId;

		currentBotRetrievalStatus = 'saving';
		currentBotRetrievalError = undefined;
		renderState( stateFromReadiness() );
		try {
			await deleteBotKnowledgeBinding( client, botId );
			if ( ! isCurrentRequest() ) {
				return;
			}
			currentBotRetrieval = emptyBotRetrieval();
			loadedBotRetrievalId = botId;
			currentBotRetrievalStatus = 'ready';
			if ( ( await refreshReadiness() ) === 'failed' ) {
				renderState( stateFromReadiness() );
			}
		} catch {
			if ( ! isCurrentRequest() ) {
				return;
			}
			currentBotRetrievalStatus = 'error';
			currentBotRetrievalError =
				'Knowledge connection could not be disconnected. Try again.';
			renderState( stateFromReadiness() );
		}
	};
	const mutateKnowledgeJob = async (
		job: KnowledgeJobItem,
		action: 'cancel' | 'retry'
	): Promise< void > => {
		const mutationGeneration = ++knowledgeJobMutationGeneration;
		const mutationHash = currentHash();
		const isCurrentMutation = (): boolean =>
			mutationGeneration === knowledgeJobMutationGeneration &&
			currentHash() === mutationHash &&
			resolveAdminScreen( currentHash() ) === 'knowledge' &&
			resolveHashPath( currentHash() ) === 'knowledge';
		currentKnowledgeJobMutationError = undefined;
		renderState( stateFromReadiness() );
		try {
			await client.request< KnowledgeJobItem >(
				`/admin/knowledge/jobs/${ encodeURIComponent(
					job.job_key
				) }/${ action }`,
				{ method: 'POST' }
			);
			if ( ! isCurrentMutation() ) {
				return;
			}
			if ( ! ( await refreshKnowledgeJobs() ) ) {
				return;
			}
			if ( ! isCurrentMutation() ) {
				return;
			}
			if ( ( await refreshReadiness() ) === 'failed' ) {
				renderState( stateFromReadiness() );
			}
		} catch ( error ) {
			if ( ! isCurrentMutation() ) {
				return;
			}
			currentKnowledgeJobMutationError =
				knowledgeJobMutationErrorFromError( error );
			renderState( stateFromReadiness() );
		}
	};
	cancelKnowledgeJob = async ( job: KnowledgeJobItem ): Promise< void > =>
		mutateKnowledgeJob( job, 'cancel' );
	retryKnowledgeJob = async ( job: KnowledgeJobItem ): Promise< void > =>
		mutateKnowledgeJob( job, 'retry' );

	deleteBot = async ( bot: BotListItem ): Promise< void > => {
		try {
			await client.request< { deleted: true } >(
				`/admin/bots/${ encodeURIComponent( bot.id ) }`,
				{ method: 'DELETE' }
			);
			await refreshBotPage( 1 );
			if ( ( await refreshReadiness() ) === 'failed' ) {
				renderState( stateFromReadiness() );
			}
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
			const previousIssue = currentProviderIssue;
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
			currentProviderIssue = undefined;

			if ( previousIssue === 'missing_credential' ) {
				await refreshProviderModelsState( providerId );
			}

			if ( ( await refreshReadiness() ) === 'failed' ) {
				renderState( stateFromReadiness() );
			}
		} catch {
			renderState( 'error' );
		}
	};

	void refreshReadiness( false ).then( async ( readinessResult ) => {
		if ( readinessResult === 'failed' ) {
			renderState( 'error' );
			return;
		}
		if ( readinessResult === 'stale' ) {
			return;
		}
		const screen = resolveAdminScreen( currentHash() );

		if ( screen === 'bots' ) {
			await refreshBotPage();
			const botId = activeBotId();
			if ( botId !== undefined ) {
				await Promise.all( [
					refreshBotAppearance( botId ),
					refreshBotDisplayRules( botId ),
					refreshBotKnowledgeSources( botId ),
					refreshBotRetrieval( botId ),
				] );
			}
		}

		if ( screen === 'publish' ) {
			await refreshPublishState();
			renderState( stateFromReadiness() );
			return;
		}

		if ( screen === 'knowledge' ) {
			const isCurrentKnowledgePage = await refreshKnowledgePage();

			if ( ! isCurrentKnowledgePage ) {
				return;
			}
			if ( resolveHashPath( currentHash() ) === 'knowledge' ) {
				if ( ! ( await refreshKnowledgeJobs() ) ) {
					return;
				}
			}
			const sourceId = resolveSelectedPersistedKnowledgeSourceId(
				currentHash()
			);

			if ( sourceId !== undefined ) {
				await refreshKnowledgeSelection( sourceId );
			}
		}

		if ( screen === 'providers' ) {
			const providerId = resolveSelectedProviderId( currentHash() );

			if ( providerId !== undefined ) {
				await refreshProviderSettings( providerId );
			}
		}

		renderState( stateFromReadiness() );
	} );

	return true;
};

bootstrapAdminApp();
