import * as plugin from './admin-entry/index';

type TestElementProps = Record< string, unknown > | null;

const createTestElement = (
	tagName: string,
	props: TestElementProps,
	...children: Array< Node | string | undefined >
): HTMLElement => {
	const element = document.createElement( tagName );

	for ( const [ key, value ] of Object.entries( props ?? {} ) ) {
		if ( value === undefined || key === 'key' ) {
			continue;
		}
		if ( key === 'className' ) {
			element.className = String( value );
			continue;
		}
		if ( key === 'htmlFor' ) {
			element.setAttribute( 'for', String( value ) );
			continue;
		}
		if ( key.startsWith( 'on' ) && typeof value === 'function' ) {
			continue;
		}
		element.setAttribute( key, String( value ) );
	}

	for ( const child of children ) {
		if ( child !== undefined ) {
			element.append( child );
		}
	}

	return element;
};

const installElementFactory = (): void => {
	Object.defineProperty( window, 'wp', {
		configurable: true,
		value: {
			element: {
				createElement: createTestElement,
			},
		},
	} );
};

describe( 'provider-first admin UX', () => {
	it( 'uses a compact WordPress tab navigation without a separate onboarding tab', () => {
		installElementFactory();
		const AdminShell = ( plugin as unknown as Record< string, unknown > )
			.AdminShell as ( props: Record< string, unknown > ) => Node;
		const root = document.createElement( 'div' );

		root.append( AdminShell( { state: 'ready', screen: 'providers' } ) );

		const nav = root.querySelector( 'nav[aria-label="Administration"]' );
		const links = Array.from( nav?.querySelectorAll( 'a' ) ?? [] );
		expect( nav?.classList.contains( 'nav-tab-wrapper' ) ).toBe( true );
		expect( links.map( ( link ) => link.textContent ) ).toEqual( [
			'Providers',
			'Chatbots',
			'Knowledge',
			'Test Chat',
		] );
		expect(
			links.every( ( link ) => link.classList.contains( 'nav-tab' ) )
		).toBe( true );
		expect(
			nav
				?.querySelector( 'a[aria-current="page"]' )
				?.classList.contains( 'nav-tab-active' )
		).toBe( true );
	} );

	it( 'shows the provider choices on the providers root instead of an empty heading', () => {
		installElementFactory();
		const AdminShell = ( plugin as unknown as Record< string, unknown > )
			.AdminShell as ( props: Record< string, unknown > ) => Node;
		const root = document.createElement( 'div' );

		root.append(
			AdminShell( {
				state: 'ready',
				screen: 'providers',
				providers: [
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
						status: 'configured',
						credential_source: 'option',
						capabilities: [ 'generation', 'model_catalog' ],
					},
				],
			} )
		);

		const catalog = root.querySelector( '[data-provider-catalog]' );
		expect( catalog ).not.toBeNull();
		expect( catalog?.textContent ).toContain( 'OpenAI' );
		expect( catalog?.textContent ).toContain( 'Google Gemini' );
		expect( catalog?.textContent ).toContain( 'Groq' );
		expect(
			catalog?.querySelector( 'a[href="#/providers/openai_direct"]' )
		).not.toBeNull();
		expect(
			catalog?.querySelector( 'a[href="#/providers/gemini_direct"]' )
		).not.toBeNull();
		expect(
			catalog?.querySelector( 'a[href="#/providers/groq_direct"]' )
		).not.toBeNull();
	} );

	it( 'does not replace navigation text nodes when enhancement runs again', () => {
		installElementFactory();
		const pluginExports = plugin as unknown as Record< string, unknown >;
		const AdminShell = pluginExports.AdminShell as (
			props: Record< string, unknown >
		) => Node;
		const enhanceAdminDom = pluginExports.enhanceAdminDom as (
			root: Element
		) => void;
		const root = document.createElement( 'div' );

		root.append( AdminShell( { state: 'ready', screen: 'providers' } ) );
		const links = Array.from(
			root.querySelectorAll< HTMLAnchorElement >(
				'nav[aria-label="Administration"] a'
			)
		);
		const firstTextNodes = links.map( ( link ) => link.firstChild );

		enhanceAdminDom( root );

		links.forEach( ( link, index ) => {
			expect( link.firstChild ).toBe( firstTextNodes[ index ] );
		} );
	} );
} );
