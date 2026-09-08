import * as plugin from './index';

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

type BotManagementComponent = ( props: { page: BotPage } ) => Node;
type BotEditorComponent = ( props: { mode: 'create' | 'edit' } ) => Node;

type TestElementProps = Record< string, unknown > | null;

const createTestElement = (
	tagName: string,
	props: TestElementProps,
	...children: Array< Node | string | undefined >
): HTMLElement => {
	const element = document.createElement( tagName );

	for ( const [ key, value ] of Object.entries( props ?? {} ) ) {
		if ( key === 'key' || value === undefined ) {
			continue;
		}

		if ( typeof value === 'boolean' ) {
			if ( value ) {
				element.setAttribute( key, '' );
			}
			continue;
		}

		element.setAttribute( key === 'htmlFor' ? 'for' : key, String( value ) );
	}

	for ( const child of children ) {
		if ( child !== undefined ) {
			element.append( child );
		}
	}

	return element;
};

const configureTestRuntime = (): void => {
	Object.defineProperty( window, 'wp', {
		configurable: true,
		value: {
			element: {
				createElement: createTestElement,
			},
		},
	} );
};

const renderBotManagement = ( page: BotPage ): HTMLElement => {
	configureTestRuntime();

	const exports = plugin as unknown as Record< string, unknown >;
	const BotManagementScreen = exports.BotManagementScreen;

	expect( typeof BotManagementScreen ).toBe( 'function' );

	const root = document.createElement( 'div' );
	root.append(
		( BotManagementScreen as BotManagementComponent )( {
			page,
		} )
	);

	return root;
};

const renderBotEditor = (): HTMLElement => {
	configureTestRuntime();

	const exports = plugin as unknown as Record< string, unknown >;
	const BotEditorScreen = exports.BotEditorScreen;

	expect( typeof BotEditorScreen ).toBe( 'function' );

	const root = document.createElement( 'div' );
	root.append(
		( BotEditorScreen as BotEditorComponent )( {
			mode: 'create',
		} )
	);

	return root;
};

const bot = ( id: string, name: string ): BotListItem => ( {
	id,
	name,
	enabled: true,
	provider_id: 'openai',
	model_id: 'gpt-5-mini',
	version: 1,
	created_at: '2026-09-08T00:00:00+00:00',
	updated_at: '2026-09-08T00:00:00+00:00',
} );

describe( 'BotManagementScreen', () => {
	it( 'renders an explicit empty state from an empty Task 3 bot page', () => {
		const root = renderBotManagement( {
			items: [],
			total: 0,
			page: 1,
			per_page: 20,
		} );

		expect(
			root.querySelector( '[data-bot-list-empty]' )?.textContent
		).toBe( 'No bots configured yet.' );
	} );

	it( 'renders bot rows and deterministic pagination from the Task 3 list contract', () => {
		const root = renderBotManagement( {
			items: [
				bot( 'bot-alpha', 'Support Bot' ),
				bot( 'bot-beta', 'Sales Bot' ),
			],
			total: 3,
			page: 1,
			per_page: 2,
		} );
		const rows = Array.from( root.querySelectorAll( '[data-bot-id]' ) );

		expect(
			rows.map( ( row ) => row.getAttribute( 'data-bot-id' ) )
		).toEqual( [ 'bot-alpha', 'bot-beta' ] );
		expect( rows.map( ( row ) => row.textContent ) ).toEqual( [
			'Support Bot',
			'Sales Bot',
		] );
		expect(
			root.querySelector( '[aria-label="Bot list pagination"]' )
				?.textContent
		).toContain( 'Page 1 of 2' );
	} );
} );

describe( 'BotEditorScreen', () => {
	it( 'renders accessible required create fields for the Task 3 bot contract', () => {
		const root = renderBotEditor();
		const form = root.querySelector( 'form[data-bot-editor="create"]' );
		const name = root.querySelector( 'input[name="name"]' );
		const provider = root.querySelector( 'input[name="provider_id"]' );
		const model = root.querySelector( 'input[name="model_id"]' );

		expect( form ).not.toBeNull();
		expect( name?.getAttribute( 'required' ) ).not.toBeNull();
		expect( provider?.getAttribute( 'required' ) ).not.toBeNull();
		expect( model?.getAttribute( 'required' ) ).not.toBeNull();
		expect(
			root.querySelector( 'label[for="bot-name"]' )?.textContent
		).toBe( 'Bot name' );
		expect(
			root.querySelector( 'label[for="bot-provider"]' )?.textContent
		).toBe( 'Provider' );
		expect(
			root.querySelector( 'label[for="bot-model"]' )?.textContent
		).toBe( 'Model' );
		expect(
			root.querySelector( 'button[type="submit"]' )?.textContent
		).toBe( 'Create bot' );
	} );
} );
