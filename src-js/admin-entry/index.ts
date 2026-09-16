import {
	AdminShell as CoreAdminShell,
	createAdminApiClient,
	type AdminShellProps,
} from '../index';

export type ProviderCatalogStatus =
	| 'configured'
	| 'unconfigured'
	| 'unavailable'
	| 'available';

export interface ProviderCatalogItem {
	provider_id: string;
	display_name: string;
	status: ProviderCatalogStatus;
	credential_source: string;
	capabilities: ReadonlyArray< string >;
}

export interface AdminEntryShellProps extends AdminShellProps {
	providers?: ReadonlyArray< ProviderCatalogItem >;
}

const DEFAULT_PROVIDERS: ReadonlyArray< ProviderCatalogItem > = [
	{
		provider_id: 'openai_direct',
		display_name: 'OpenAI',
		status: 'unconfigured',
		credential_source: 'none',
		capabilities: [ 'generation', 'model_catalog' ],
	},
	{
		provider_id: 'gemini_direct',
		display_name: 'Google Gemini',
		status: 'unconfigured',
		credential_source: 'none',
		capabilities: [ 'generation', 'model_catalog' ],
	},
	{
		provider_id: 'groq_direct',
		display_name: 'Groq',
		status: 'unconfigured',
		credential_source: 'none',
		capabilities: [ 'generation', 'model_catalog' ],
	},
	{
		provider_id: 'openrouter_direct',
		display_name: 'OpenRouter',
		status: 'unconfigured',
		credential_source: 'none',
		capabilities: [ 'generation', 'model_catalog' ],
	},
	{
		provider_id: 'wordpress_ai_client',
		display_name: 'WordPress AI Client',
		status: 'available',
		credential_source: 'core',
		capabilities: [ 'generation' ],
	},
];

const providerStatusLabel = ( provider: ProviderCatalogItem ): string => {
	if ( provider.provider_id === 'wordpress_ai_client' ) {
		return 'Built in';
	}

	return provider.status === 'configured' ? 'Connected' : 'Not connected';
};

const providerDescription = ( providerId: string ): string => {
	return (
		(
			{
				openai_direct: 'Use your OpenAI API key.',
				gemini_direct: 'Use your Google Gemini API key.',
				groq_direct: 'Use your Groq API key for fast inference.',
				openrouter_direct: 'Use one key to access OpenRouter models.',
				wordpress_ai_client:
					'Use the AI provider configured by WordPress.',
			} as Record< string, string >
		 )[ providerId ] ?? 'Connect this AI provider.'
	);
};

const selectedProviderIdFromHash = ( hash: string ): string | undefined => {
	const match = hash.match( /^#\/providers\/([^/?#]+)/ );
	return match?.[ 1 ] === undefined
		? undefined
		: decodeURIComponent( match[ 1 ] );
};

const screenFromHash = ( hash: string ): AdminShellProps[ 'screen' ] => {
	const path = hash.replace( /^#\/?/, '' ).split( /[/?#]/, 1 )[ 0 ];

	if (
		path === 'overview' ||
		path === 'bots' ||
		path === 'knowledge' ||
		path === 'playground' ||
		path === 'publish'
	) {
		return path;
	}

	return 'providers';
};

const simplifyNavigation = ( root: Element ): void => {
	const nav = root.querySelector< HTMLElement >(
		'nav[aria-label="Administration"]'
	);

	if ( nav === null ) {
		return;
	}

	nav.classList.add( 'nav-tab-wrapper', 'wp-rag-ai-chatbot-tabs' );
	const links = Array.from(
		nav.querySelectorAll< HTMLAnchorElement >( 'a' )
	);
	const onboarding = links.find(
		( link ) => link.getAttribute( 'href' ) === '#/onboarding'
	);
	onboarding?.remove();

	const labels: Record< string, string > = {
		'#/providers': 'Providers',
		'#/bots': 'Chatbots',
		'#/knowledge': 'Knowledge',
		'#/playground': 'Test Chat',
	};
	const desiredOrder = [
		'#/providers',
		'#/bots',
		'#/knowledge',
		'#/playground',
	];
	const remaining = Array.from(
		nav.querySelectorAll< HTMLAnchorElement >( 'a' )
	).filter(
		( link ) => labels[ link.getAttribute( 'href' ) ?? '' ] !== undefined
	);

	for ( const link of remaining ) {
		const href = link.getAttribute( 'href' ) ?? '';
		const label = labels[ href ];
		if ( link.textContent !== label ) {
			link.textContent = label;
		}
		link.classList.add( 'nav-tab' );
		link.classList.toggle(
			'nav-tab-active',
			link.getAttribute( 'aria-current' ) === 'page'
		);
	}

	const currentOrder = remaining.map( ( link ) =>
		link.getAttribute( 'href' )
	);
	if ( currentOrder.join( '|' ) !== desiredOrder.join( '|' ) ) {
		for ( const href of desiredOrder ) {
			const link = remaining.find(
				( item ) => item.getAttribute( 'href' ) === href
			);
			if ( link !== undefined ) {
				nav.append( link );
			}
		}
	}
};

const renderProviderCatalog = (
	root: Element,
	providers: ReadonlyArray< ProviderCatalogItem >
): void => {
	const main = root.querySelector( 'main' );
	if (
		main === null ||
		main.querySelector( '[data-provider-catalog]' ) !== null
	) {
		return;
	}

	const documentRef = root.ownerDocument;
	const section = documentRef.createElement( 'section' );
	section.setAttribute( 'data-provider-catalog', '' );
	section.className = 'wp-rag-ai-chatbot-provider-catalog';

	const intro = documentRef.createElement( 'p' );
	intro.textContent =
		'Choose a provider, add its API key, then select a model. That is all you need to get started.';
	section.append( intro );

	const grid = documentRef.createElement( 'div' );
	grid.className = 'wp-rag-ai-chatbot-provider-grid';

	for ( const provider of providers ) {
		const card = documentRef.createElement( 'article' );
		card.className = 'wp-rag-ai-chatbot-provider-card';
		card.setAttribute( 'data-provider-id', provider.provider_id );

		const header = documentRef.createElement( 'div' );
		header.className = 'wp-rag-ai-chatbot-provider-card__header';
		const name = documentRef.createElement( 'h2' );
		name.textContent = provider.display_name;
		const status = documentRef.createElement( 'span' );
		status.className = `wp-rag-ai-chatbot-provider-status wp-rag-ai-chatbot-provider-status--${ provider.status }`;
		status.textContent = providerStatusLabel( provider );
		header.append( name, status );

		const description = documentRef.createElement( 'p' );
		description.textContent = providerDescription( provider.provider_id );
		card.append( header, description );

		if ( provider.provider_id !== 'wordpress_ai_client' ) {
			const action = documentRef.createElement( 'a' );
			action.className = 'button button-primary';
			action.href = `#/providers/${ encodeURIComponent(
				provider.provider_id
			) }`;
			action.textContent =
				provider.status === 'configured' ? 'Manage' : 'Configure';
			card.append( action );
		}

		grid.append( card );
	}

	section.append( grid );
	main.append( section );
};

export const enhanceAdminDom = (
	root: Element,
	providers: ReadonlyArray< ProviderCatalogItem > = DEFAULT_PROVIDERS,
	screen: AdminShellProps[ 'screen' ] = 'providers',
	providerId?: string
): void => {
	simplifyNavigation( root );
	const existingCatalog = root.querySelector( '[data-provider-catalog]' );

	if ( screen !== 'providers' || providerId !== undefined ) {
		existingCatalog?.remove();
		return;
	}

	renderProviderCatalog( root, providers );
};

export const AdminShell = ( props: AdminEntryShellProps ): unknown => {
	const rendered = CoreAdminShell( props );

	if ( typeof Element !== 'undefined' && rendered instanceof Element ) {
		enhanceAdminDom(
			rendered,
			props.providers ?? DEFAULT_PROVIDERS,
			props.screen ?? 'providers',
			props.providerId
		);
	}

	return rendered;
};

interface RuntimeAdminConfig {
	restBase: string;
	nonce: string;
}

type RuntimeWindow = typeof window & {
	wpRagAiChatbotAdminConfig?: RuntimeAdminConfig;
};

const bootstrapAdminEnhancements = (): void => {
	const root = document.getElementById( 'wp-rag-ai-chatbot-admin' );
	if ( root === null ) {
		return;
	}

	let providers: ReadonlyArray< ProviderCatalogItem > = [
		...DEFAULT_PROVIDERS,
	];
	const apply = (): void => {
		const hash = window.location.hash;
		if ( hash === '' || hash === '#/onboarding' ) {
			window.location.hash = '#/overview';
			return;
		}

		enhanceAdminDom(
			root,
			providers,
			screenFromHash( hash ),
			selectedProviderIdFromHash( hash )
		);
	};
	const observer = new MutationObserver( apply );
	observer.observe( root, { childList: true, subtree: true } );
	window.addEventListener( 'hashchange', apply );
	apply();

	const config = ( window as RuntimeWindow ).wpRagAiChatbotAdminConfig;
	if ( config === undefined || typeof window.fetch !== 'function' ) {
		return;
	}

	const client = createAdminApiClient( {
		baseUrl: config.restBase,
		nonce: config.nonce,
		fetcher: window.fetch.bind( window ),
	} );
	const directProviders = providers.filter(
		( provider ) => provider.provider_id !== 'wordpress_ai_client'
	);

	void Promise.all(
		directProviders.map( async ( provider ) => {
			try {
				const state = await client.request< {
					configured?: boolean;
					source?: string;
				} >(
					`/admin/providers/${ encodeURIComponent(
						provider.provider_id
					) }/credential`
				);
				return {
					...provider,
					status:
						state.configured === true
							? 'configured'
							: 'unconfigured',
					credential_source:
						typeof state.source === 'string'
							? state.source
							: 'none',
				} as ProviderCatalogItem;
			} catch {
				return {
					...provider,
					status: 'unavailable',
				} as ProviderCatalogItem;
			}
		} )
	).then( ( resolved ) => {
		providers = providers.map( ( provider ) => {
			return (
				resolved.find(
					( item ) => item.provider_id === provider.provider_id
				) ?? provider
			);
		} );
		root.querySelector( '[data-provider-catalog]' )?.remove();
		apply();
	} );
};

bootstrapAdminEnhancements();
