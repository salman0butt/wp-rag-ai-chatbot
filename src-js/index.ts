import {
	ProviderCredentialState,
	ProviderModelChoice,
	ProviderSettingsIssue,
	ProviderSettingsScreen,
} from './provider-settings';
import { AppearanceCustomizer } from './appearance-customizer';
import type { PlaygroundControllerState } from './playground-controller';
import { PlaygroundPanel } from './playground-panel';
import { createPlaygroundRuntime } from './playground-runtime';
import type { PlaygroundRequestDraft } from './playground-screen';
import {
	normalizeWidgetAppearance,
	type WidgetAppearance,
} from './widget-appearance';

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
export type AdminScreen =
	| 'onboarding'
	| 'bots'
	| 'providers'
	| 'knowledge'
	| 'playground';
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
	onboardingStep?: OnboardingStep;
	onboardingIssue?: OnboardingIssue;
	botPage?: BotPage;
	selectedBotId?: string;
	botAppearance?: WidgetAppearance;
	botAppearanceSaving?: boolean;
	botAppearanceError?: string;
	onChangeBotAppearance?: ( next: WidgetAppearance ) => void;
	onSaveBotAppearance?: ( next: WidgetAppearance ) => void;
	knowledgePage?: KnowledgeSourcePage;
	selectedKnowledgeSourceId?: string;
	selectedKnowledgeDocumentKey?: string;
	knowledgeDetail?: KnowledgeSourceDetail;
	knowledgeDocuments?: KnowledgeDocumentPage;
	knowledgeChunks?: KnowledgeChunkPage;
	knowledgeJobs?: KnowledgeJobPage;
	knowledgeJobMutationError?: KnowledgeJobMutationError;
	onEnqueueKnowledgeJob?: (
		draft: KnowledgeJobEnqueueDraft
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

interface KnowledgeJobEnqueueDraft {
	document_key: string;
	source_id: number;
	collection_id: string;
	configuration_id: string;
	generation: string;
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
	onEnqueueJob?: ( draft: KnowledgeJobEnqueueDraft ) => Promise< void >;
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
	onEnqueueJob,
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
	const enqueueForm = createElement(
		'form',
		{
			'data-knowledge-job-enqueue': 'true',
			onSubmit: ( event: Event ) => {
				event.preventDefault();

				if ( onEnqueueJob === undefined ) {
					return;
				}

				const form = event.currentTarget as HTMLFormElement;
				const documentKey = form.elements.namedItem(
					'document_key'
				) as HTMLInputElement;
				const sourceId = form.elements.namedItem(
					'source_id'
				) as HTMLInputElement;
				const collectionId = form.elements.namedItem(
					'collection_id'
				) as HTMLInputElement;
				const configurationId = form.elements.namedItem(
					'configuration_id'
				) as HTMLInputElement;
				const generation = form.elements.namedItem(
					'generation'
				) as HTMLInputElement;

				void onEnqueueJob( {
					document_key: documentKey.value.trim(),
					source_id: Number.parseInt( sourceId.value, 10 ),
					collection_id: collectionId.value.trim(),
					configuration_id: configurationId.value.trim(),
					generation: generation.value.trim(),
				} );
			},
		},
		createElement(
			'label',
			{ htmlFor: 'knowledge-job-document-key' },
			'Document key'
		),
		createElement( 'input', {
			id: 'knowledge-job-document-key',
			name: 'document_key',
			required: true,
			type: 'text',
		} ),
		createElement(
			'label',
			{ htmlFor: 'knowledge-job-source-id' },
			'Source ID'
		),
		createElement( 'input', {
			id: 'knowledge-job-source-id',
			min: 1,
			name: 'source_id',
			required: true,
			type: 'number',
		} ),
		createElement(
			'label',
			{ htmlFor: 'knowledge-job-collection-id' },
			'Collection ID'
		),
		createElement( 'input', {
			id: 'knowledge-job-collection-id',
			name: 'collection_id',
			required: true,
			type: 'text',
		} ),
		createElement(
			'label',
			{ htmlFor: 'knowledge-job-configuration-id' },
			'Configuration ID'
		),
		createElement( 'input', {
			id: 'knowledge-job-configuration-id',
			name: 'configuration_id',
			required: true,
			type: 'text',
		} ),
		createElement(
			'label',
			{ htmlFor: 'knowledge-job-generation' },
			'Generation'
		),
		createElement( 'input', {
			id: 'knowledge-job-generation',
			name: 'generation',
			required: true,
			type: 'text',
		} ),
		createElement( 'button', { type: 'submit' }, 'Enqueue indexing job' )
	);
	const jobContent =
		jobs === undefined
			? undefined
			: createElement(
					'section',
					{ 'data-knowledge-jobs': 'list' },
					createElement( 'h2', null, 'Indexing jobs' ),
					mutationErrorContent,
					enqueueForm,
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

export const AdminShell = ( {
	state,
	screen = 'onboarding',
	onboardingStep,
	onboardingIssue,
	botPage,
	selectedBotId,
	botAppearance,
	botAppearanceSaving = false,
	botAppearanceError,
	onChangeBotAppearance,
	onSaveBotAppearance,
	knowledgePage,
	selectedKnowledgeSourceId,
	selectedKnowledgeDocumentKey,
	knowledgeDetail,
	knowledgeDocuments,
	knowledgeChunks,
	knowledgeJobs,
	knowledgeJobMutationError,
	onEnqueueKnowledgeJob,
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
}: AdminShellProps ): unknown => {
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
			} ),
			botAppearance === undefined
				? undefined
				: AppearanceCustomizer( {
						appearance: botAppearance,
						saving: botAppearanceSaving,
						error: botAppearanceError,
						onChange: onChangeBotAppearance ?? ( () => undefined ),
						onSave: onSaveBotAppearance ?? ( () => undefined ),
				  } )
		);
	} else if ( screen === 'knowledge' && knowledgePage !== undefined ) {
		screenContent = createElement(
			'div',
			null,
			createElement( 'h1', null, selectedLabel ),
			KnowledgeManagementScreen( {
				page: knowledgePage,
				selectedSourceId: selectedKnowledgeSourceId,
				selectedDocumentKey: selectedKnowledgeDocumentKey,
				detail: knowledgeDetail,
				documents: knowledgeDocuments,
				chunks: knowledgeChunks,
				jobs: knowledgeJobs,
				mutationError: knowledgeJobMutationError,
				onEnqueueJob: onEnqueueKnowledgeJob,
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
			createElement( 'h1', null, selectedLabel ),
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
	botAppearance?: WidgetAppearance,
	botAppearanceSaving?: boolean,
	botAppearanceError?: string,
	onChangeBotAppearance?: ( next: WidgetAppearance ) => void,
	onSaveBotAppearance?: ( next: WidgetAppearance ) => void,
	knowledgePage?: KnowledgeSourcePage,
	selectedKnowledgeSourceId?: string,
	selectedKnowledgeDocumentKey?: string,
	knowledgeDetail?: KnowledgeSourceDetail,
	knowledgeDocuments?: KnowledgeDocumentPage,
	knowledgeChunks?: KnowledgeChunkPage,
	knowledgeJobs?: KnowledgeJobPage,
	knowledgeJobMutationError?: KnowledgeJobMutationError,
	onEnqueueKnowledgeJob?: (
		draft: KnowledgeJobEnqueueDraft
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
	onSubmitPlayground?: ( request: PlaygroundRequestDraft ) => void
): void => {
	window.wp.element.render(
		AdminShell( {
			state,
			screen,
			onboardingStep,
			onboardingIssue,
			botPage,
			selectedBotId,
			botAppearance,
			botAppearanceSaving,
			botAppearanceError,
			onChangeBotAppearance,
			onSaveBotAppearance,
			knowledgePage,
			selectedKnowledgeSourceId,
			selectedKnowledgeDocumentKey,
			knowledgeDetail,
			knowledgeDocuments,
			knowledgeChunks,
			knowledgeJobs,
			knowledgeJobMutationError,
			onEnqueueKnowledgeJob,
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
	let currentBotAppearance: WidgetAppearance | undefined;
	let currentBotAppearanceSaving = false;
	let currentBotAppearanceError: string | undefined;
	let loadedBotAppearanceId: string | undefined;
	let botAppearanceGeneration = 0;
	let currentKnowledgePage: KnowledgeSourcePage | undefined;
	let currentKnowledgeDetail: KnowledgeSourceDetail | undefined;
	let currentKnowledgeDocuments: KnowledgeDocumentPage | undefined;
	let currentKnowledgeChunks: KnowledgeChunkPage | undefined;
	let currentKnowledgeJobs: KnowledgeJobPage | undefined;
	let currentKnowledgeJobMutationError: KnowledgeJobMutationError | undefined;
	let enqueueKnowledgeJob: (
		draft: KnowledgeJobEnqueueDraft
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
	let changeBotAppearance: ( next: WidgetAppearance ) => void = () =>
		undefined;
	let saveBotAppearance: ( next: WidgetAppearance ) => void = () => undefined;
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
			enqueueKnowledgeJob,
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
			}
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
	const refreshKnowledgeJobs = async (): Promise< void > => {
		currentKnowledgeJobs = await client.request< KnowledgeJobPage >(
			'/admin/knowledge/jobs?page=1&per_page=20'
		);
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

		const screen = resolveAdminScreen( currentHash() );

		if ( screen !== 'bots' ) {
			botAppearanceGeneration += 1;
			currentBotAppearance = undefined;
			currentBotAppearanceSaving = false;
			currentBotAppearanceError = undefined;
			loadedBotAppearanceId = undefined;
		}

		if ( screen !== 'knowledge' ) {
			knowledgePageGeneration += 1;
			knowledgeSelectionGeneration += 1;
			currentKnowledgePage = undefined;
			currentKnowledgeDetail = undefined;
			currentKnowledgeDocuments = undefined;
			currentKnowledgeChunks = undefined;
			currentKnowledgeJobs = undefined;
			currentKnowledgeJobMutationError = undefined;
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
						await refreshBotAppearance( botId );
					}
				} )
				.then( () => renderState( stateFromReadiness() ) )
				.catch( () => renderState( 'error' ) );
			return;
		}

		const currentActiveBotId = activeBotId();

		if (
			screen === 'bots' &&
			currentActiveBotId !== undefined &&
			currentActiveBotId !== loadedBotAppearanceId
		) {
			currentBotAppearance = undefined;
			currentBotAppearanceSaving = false;
			currentBotAppearanceError = undefined;
			renderState( currentState );
			void refreshBotAppearance( currentActiveBotId ).then(
				( current ) => {
					if ( current ) {
						renderState( stateFromReadiness() );
					}
				}
			);
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
						await refreshKnowledgeJobs();
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
				.then( () => renderState( stateFromReadiness() ) )
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
	enqueueKnowledgeJob = async (
		draft: KnowledgeJobEnqueueDraft
	): Promise< void > => {
		currentKnowledgeJobMutationError = undefined;
		renderState( stateFromReadiness() );
		try {
			await client.request< KnowledgeJobItem >( '/admin/knowledge/jobs', {
				method: 'POST',
				body: draft,
			} );
			await refreshKnowledgeJobs();
			renderState( stateFromReadiness() );
		} catch ( error ) {
			currentKnowledgeJobMutationError =
				knowledgeJobMutationErrorFromError( error );
			renderState( stateFromReadiness() );
		}
	};
	const mutateKnowledgeJob = async (
		job: KnowledgeJobItem,
		action: 'cancel' | 'retry'
	): Promise< void > => {
		currentKnowledgeJobMutationError = undefined;
		renderState( stateFromReadiness() );
		try {
			await client.request< KnowledgeJobItem >(
				`/admin/knowledge/jobs/${ encodeURIComponent(
					job.job_key
				) }/${ action }`,
				{ method: 'POST' }
			);
			await refreshKnowledgeJobs();
			renderState( stateFromReadiness() );
		} catch ( error ) {
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
				const botId = activeBotId();
				if ( botId !== undefined ) {
					await refreshBotAppearance( botId );
				}
			}

			if ( screen === 'knowledge' ) {
				const isCurrentKnowledgePage = await refreshKnowledgePage();

				if ( ! isCurrentKnowledgePage ) {
					return;
				}
				if ( resolveHashPath( currentHash() ) === 'knowledge' ) {
					await refreshKnowledgeJobs();
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
		} )
		.catch( () => {
			renderState( 'error' );
		} );

	return true;
};

bootstrapAdminApp();
