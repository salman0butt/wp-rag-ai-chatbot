export type AdminUiState = 'loading' | 'empty' | 'error' | 'ready';

export interface AdminReadiness {
	ready: boolean;
	next_step: string;
	configured_generation_provider: boolean;
	configured_gemini_embedding: boolean;
	model_available: boolean;
	source_count: number;
	completed_index_present: boolean;
	enabled_bot_count: number;
	bound_bot_present: boolean;
	publishable_bot_present: boolean;
	issue?: string;
}

export type ModernAdminScreen =
	| 'overview'
	| 'knowledge'
	| 'bots'
	| 'publish'
	| 'providers'
	| 'playground';

type ElementFactory = (
	type: string,
	props: Record< string, unknown > | null,
	...children: unknown[]
) => unknown;

const create = (): ElementFactory => window.wp.element.createElement;

const safeCount = ( value: number ): number =>
	Number.isSafeInteger( value ) && value >= 0 ? value : 0;

const stepTarget = (
	readiness: AdminReadiness
): {
	href: string;
	label: string;
	description: string;
} => {
	switch ( readiness.next_step ) {
		case 'provider':
		case 'model':
			return {
				href: '#/providers/gemini_direct',
				label: 'Connect Gemini',
				description: 'Connect Gemini and choose a generation model.',
			};
		case 'first_bot':
			return {
				href: '#/bots',
				label: 'Create a chatbot',
				description: 'Create an enabled chatbot for your site.',
			};
		case 'knowledge':
			return {
				href: '#/knowledge',
				label: 'Add knowledge',
				description: 'Add a source to ground your chatbot answers.',
			};
		case 'binding':
			return {
				href: '#/bots',
				label: 'Connect knowledge',
				description: 'Attach an indexed source to a chatbot.',
			};
		case 'publish':
		case 'complete':
		default:
			return {
				href: '#/publish',
				label: 'Open Publish/Test',
				description: 'Test the chatbot and choose a publish method.',
			};
	}
};

const statusBadge = ( text: string, ready: boolean ): unknown => {
	const createElement = create();

	return createElement(
		'span',
		{
			className: `wp-rag-ai-admin-badge ${
				ready ? 'wp-rag-ai-admin-badge--ready' : ''
			}`,
			'aria-label': text,
		},
		text
	);
};

const readinessCards = ( readiness: AdminReadiness ): unknown[] => {
	const createElement = create();
	const sourceCount = safeCount( readiness.source_count );
	const botCount = safeCount( readiness.enabled_bot_count );
	const providerReady =
		readiness.configured_generation_provider &&
		readiness.configured_gemini_embedding &&
		readiness.model_available;
	const cards = [
		{
			key: 'provider',
			title: 'Provider',
			value: providerReady ? 'Gemini connected' : 'Connect Gemini',
			detail: providerReady
				? 'Generation and embedding are ready.'
				: 'Add a Gemini key and choose a model.',
			ready: providerReady,
		},
		{
			key: 'knowledge',
			title: 'Knowledge',
			value: `${ sourceCount } source${ sourceCount === 1 ? '' : 's' }`,
			detail: readiness.completed_index_present
				? 'Your indexed content can ground answers.'
				: 'Add knowledge and wait for indexing to finish.',
			ready: sourceCount > 0 && readiness.completed_index_present,
		},
		{
			key: 'chatbots',
			title: 'Chatbots',
			value: `${ botCount } enabled chatbot${
				botCount === 1 ? '' : 's'
			}`,
			detail: readiness.bound_bot_present
				? 'A chatbot is connected to knowledge.'
				: 'Create a chatbot and connect a source.',
			ready: botCount > 0 && readiness.bound_bot_present,
		},
		{
			key: 'publish',
			title: 'Publish',
			value: readiness.publishable_bot_present
				? 'Ready to publish'
				: 'Not ready yet',
			detail: readiness.publishable_bot_present
				? 'Choose a frontend mount for your chatbot.'
				: 'Finish the setup steps before publishing.',
			ready: readiness.publishable_bot_present,
		},
	];

	return cards.map( ( card ) =>
		createElement(
			'article',
			{
				className:
					'wp-rag-ai-admin-card wp-rag-ai-admin-readiness-card',
				'data-readiness-card': card.key,
				key: card.key,
			},
			createElement(
				'div',
				{ className: 'wp-rag-ai-admin-card__topline' },
				createElement( 'h2', null, card.title ),
				statusBadge(
					card.ready ? 'Ready' : 'Action needed',
					card.ready
				)
			),
			createElement(
				'p',
				{ className: 'wp-rag-ai-admin-card__value' },
				card.value
			),
			createElement(
				'p',
				{ className: 'wp-rag-ai-admin-card__detail' },
				card.detail
			)
		)
	);
};

export const ReadinessOverview = ( {
	readiness,
}: {
	readiness: AdminReadiness;
} ): unknown => {
	const createElement = create();
	const target = stepTarget( readiness );

	return createElement(
		'section',
		{ className: 'wp-rag-ai-admin-overview', 'data-overview': true },
		createElement(
			'section',
			{
				className: 'wp-rag-ai-admin-step-panel',
				'data-current-step': readiness.next_step,
			},
			createElement(
				'p',
				{ className: 'wp-rag-ai-admin-eyebrow' },
				'NEXT STEP'
			),
			createElement( 'h2', null, target.label ),
			createElement( 'p', null, target.description ),
			createElement(
				'a',
				{ className: 'button button-primary', href: target.href },
				'Continue setup'
			)
		),
		createElement(
			'div',
			{ className: 'wp-rag-ai-admin-card-grid' },
			...readinessCards( readiness )
		)
	);
};

interface PublishCard {
	key: 'floating' | 'embedded' | 'fullscreen' | 'gutenberg';
	title: string;
	description: string;
	snippet: string;
}

const publishCards = ( botId: string ): PublishCard[] => [
	{
		key: 'floating',
		title: 'Floating widget',
		description: 'Add the widget to a template, footer, or site-wide hook.',
		snippet: `[wp_rag_ai_chatbot bot="${ botId }"]`,
	},
	{
		key: 'embedded',
		title: 'Embedded chat',
		description: 'Place a full chat surface inside a page or post.',
		snippet: `[wp_rag_ai_chatbot_embed bot="${ botId }"]`,
	},
	{
		key: 'fullscreen',
		title: 'Fullscreen chat',
		description:
			'Use a dedicated page or template for the chat experience.',
		snippet: `[wp_rag_ai_chatbot_fullscreen bot="${ botId }"]`,
	},
	{
		key: 'gutenberg',
		title: 'Gutenberg block',
		description: 'Insert the RAG AI Chatbot block in the editor.',
		snippet: `<!-- wp:wp-rag-ai-chatbot/chatbot {"bot":"${ botId }"} /-->`,
	},
];

export const PublishTestScreen = ( {
	readiness,
	botId = 'BOT_ID',
}: {
	readiness: AdminReadiness;
	botId?: string;
} ): unknown => {
	const createElement = create();
	let copyMessage = '';
	const cards = publishCards( botId );
	const announce = ( message: string, source?: Element ): void => {
		copyMessage = message;
		const status =
			source
				?.closest( '[data-publish-test]' )
				?.querySelector< HTMLElement >( '[data-copy-status]' ) ??
			document.querySelector< HTMLElement >( '[data-copy-status]' );
		if ( status !== null ) {
			status.textContent = copyMessage;
		}
	};

	return createElement(
		'section',
		{ className: 'wp-rag-ai-admin-publish', 'data-publish-test': true },
		createElement(
			'p',
			{
				role: 'status',
				'aria-live': 'polite',
				className: 'wp-rag-ai-admin-status',
			},
			readiness.publishable_bot_present
				? 'This chatbot is ready to publish.'
				: 'Finish setup before publishing this chatbot.'
		),
		createElement(
			'div',
			{
				'aria-live': 'polite',
				className: 'screen-reader-text',
				'data-copy-status': true,
				role: 'status',
			},
			copyMessage
		),
		createElement(
			'div',
			{
				className:
					'wp-rag-ai-admin-card-grid wp-rag-ai-admin-publish-grid',
			},
			...cards.map( ( card ) =>
				createElement(
					'article',
					{
						className:
							'wp-rag-ai-admin-card wp-rag-ai-admin-publish-card',
						'data-publish-card': card.key,
						key: card.key,
					},
					createElement( 'h2', null, card.title ),
					createElement( 'p', null, card.description ),
					createElement(
						'code',
						{ 'data-publish-snippet': card.key },
						card.snippet
					),
					createElement(
						'button',
						{
							className: 'button',
							'data-copy-publish': card.key,
							'aria-label': `Copy ${ card.title } snippet`,
							type: 'button',
							onClick: ( event: Event ) => {
								const source = event.currentTarget as Element;
								const clipboard = navigator.clipboard;
								if ( clipboard === undefined ) {
									announce(
										'Copy is unavailable in this browser.',
										source
									);
									return;
								}
								void clipboard.writeText( card.snippet ).then(
									() =>
										announce(
											`Copied ${ card.title }.`,
											source
										),
									() =>
										announce(
											'Copy failed. Select the snippet manually.',
											source
										)
								);
							},
						},
						'Copy'
					)
				)
			)
		)
	);
};

export interface ModernAdminShellProps {
	state: AdminUiState;
	screen: ModernAdminScreen;
	readiness?: AdminReadiness;
	botId?: string;
	legacyContent?: unknown;
}

const pageTitle: Record< ModernAdminScreen, string > = {
	overview: 'Overview',
	knowledge: 'Knowledge',
	bots: 'Chatbots',
	publish: 'Publish/Test',
	providers: 'Provider settings',
	playground: 'Test Chat',
};

const pageDescription: Record< ModernAdminScreen, string > = {
	overview:
		'Set up grounded answers and publish your chatbot from one place.',
	knowledge: 'Add the content your chatbot should know.',
	bots: 'Create a chatbot and connect it to indexed knowledge.',
	publish: 'Test your chatbot and copy a safe publishing option.',
	providers: 'Manage credentials and generation models.',
	playground: 'Ask a question through the production chat path.',
};

const navItems: ReadonlyArray< {
	screen: ModernAdminScreen;
	label: string;
	href: string;
} > = [
	{ screen: 'overview', label: 'Overview', href: '#/overview' },
	{ screen: 'knowledge', label: 'Knowledge', href: '#/knowledge' },
	{ screen: 'bots', label: 'Chatbots', href: '#/bots' },
	{ screen: 'publish', label: 'Publish/Test', href: '#/publish' },
];

export const ModernAdminShell = ( props: ModernAdminShellProps ): unknown => {
	const createElement = create();

	if ( props.state === 'loading' ) {
		return createElement(
			'div',
			{
				role: 'status',
				'aria-live': 'polite',
				'data-admin-ui-state': 'loading',
			},
			'Loading administration data…'
		);
	}
	if ( props.state === 'error' ) {
		return createElement(
			'div',
			{ role: 'alert', 'data-admin-ui-state': 'error' },
			'Administration data could not be loaded.'
		);
	}
	if ( props.state === 'empty' ) {
		return createElement(
			'section',
			{ 'data-admin-ui-state': 'empty' },
			createElement( 'h2', null, 'Set up your chatbot' ),
			createElement( 'p', null, 'Connect Gemini to get started.' )
		);
	}

	const readiness = props.readiness;
	let content =
		props.legacyContent ??
		createElement( 'p', null, 'Select a setup step to continue.' );
	if ( props.screen === 'overview' && readiness !== undefined ) {
		content = ReadinessOverview( { readiness } );
	} else if ( props.screen === 'publish' && readiness !== undefined ) {
		content = PublishTestScreen( { readiness, botId: props.botId } );
	}

	return createElement(
		'div',
		{ className: 'wp-rag-ai-admin-shell', 'data-admin-ui-state': 'ready' },
		createElement(
			'aside',
			{ className: 'wp-rag-ai-admin-rail' },
			createElement(
				'p',
				{ className: 'wp-rag-ai-admin-brand' },
				'WP RAG AI'
			),
			createElement(
				'nav',
				{ 'aria-label': 'Primary' },
				...navItems.map( ( item ) => {
					const linkProps: Record< string, unknown > = {
						href: item.href,
						key: item.screen,
					};
					if (
						item.screen === props.screen ||
						( item.screen === 'overview' &&
							props.screen === 'providers' ) ||
						( item.screen === 'publish' &&
							props.screen === 'playground' )
					) {
						linkProps[ 'aria-current' ] = 'page';
					}
					return createElement( 'a', linkProps, item.label );
				} )
			)
		),
		createElement(
			'main',
			{ className: 'wp-rag-ai-admin-main' },
			createElement(
				'header',
				{ className: 'wp-rag-ai-admin-header' },
				createElement(
					'div',
					null,
					createElement(
						'p',
						{ className: 'wp-rag-ai-admin-eyebrow' },
						'SETUP'
					),
					createElement( 'h1', null, pageTitle[ props.screen ] ),
					createElement( 'p', null, pageDescription[ props.screen ] )
				),
				readiness === undefined
					? undefined
					: statusBadge(
							readiness.publishable_bot_present
								? 'Ready'
								: 'In progress',
							readiness.publishable_bot_present
					  )
			),
			content
		)
	);
};

export const AdminUi = ModernAdminShell;
