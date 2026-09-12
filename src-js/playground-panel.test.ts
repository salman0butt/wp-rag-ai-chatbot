import type { PlaygroundControllerState } from './playground-controller';
import type {
	PlaygroundRequestDraft,
	PlaygroundResult,
} from './playground-screen';

type PlaygroundPanelComponent = ( props: {
	state: PlaygroundControllerState;
	onSubmit?: ( request: PlaygroundRequestDraft ) => void;
} ) => Node;

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

		if ( key.startsWith( 'on' ) && typeof value === 'function' ) {
			element.addEventListener(
				key.slice( 2 ).toLowerCase(),
				value as EventListener
			);
			continue;
		}

		if ( typeof value === 'boolean' ) {
			if ( value ) {
				element.setAttribute( key, '' );
			}
			continue;
		}

		let attribute = key;
		if ( key === 'htmlFor' ) {
			attribute = 'for';
		} else if ( key === 'className' ) {
			attribute = 'class';
		}
		element.setAttribute( attribute, String( value ) );
	}

	for ( const child of children ) {
		if ( child !== undefined ) {
			element.append( child );
		}
	}

	return element;
};

const loadPanel = (): PlaygroundPanelComponent | undefined => {
	Object.defineProperty( window, 'wp', {
		configurable: true,
		value: {
			element: {
				createElement: createTestElement,
			},
		},
	} );

	try {
		const module = jest.requireActual( './playground-panel' ) as Record<
			string,
			unknown
		>;
		return module.PlaygroundPanel as PlaygroundPanelComponent | undefined;
	} catch {
		return undefined;
	}
};

const result = {
	ok: true,
	answer: 'Returns are accepted within 30 days.',
	no_answer: false,
	citations: [],
	model_id: 'gpt-test',
	latency_ms: 42,
	usage: {
		input_tokens: 10,
		output_tokens: 5,
		total_tokens: 15,
	},
	debug_trace: {
		query: { hash: 'abc', bytes: 27 },
		channels: { counts: {}, failures: {} },
		rerank_status: 'not_requested',
		candidates: [],
	},
} satisfies PlaygroundResult;

describe( 'PlaygroundPanel', () => {
	it( 'announces the pending production request through a polite live status', () => {
		const PlaygroundPanel = loadPanel();
		expect( typeof PlaygroundPanel ).toBe( 'function' );
		const root = document.createElement( 'div' );

		root.append(
			PlaygroundPanel!( {
				state: { status: 'loading' },
			} )
		);

		const status = root.querySelector(
			'[data-playground-status="loading"]'
		);
		expect( status?.getAttribute( 'role' ) ).toBe( 'status' );
		expect( status?.getAttribute( 'aria-live' ) ).toBe( 'polite' );
		expect( status?.textContent ).toBe(
			'Running the production retrieval pipeline…'
		);
	} );

	it( 'projects only the bounded result or repository-owned safe error copy', () => {
		const PlaygroundPanel = loadPanel();
		expect( typeof PlaygroundPanel ).toBe( 'function' );

		const successRoot = document.createElement( 'div' );
		successRoot.append(
			PlaygroundPanel!( {
				state: { status: 'success', result },
			} )
		);
		expect( successRoot.textContent ).toContain( result.answer );

		const errorRoot = document.createElement( 'div' );
		errorRoot.append(
			PlaygroundPanel!( {
				state: {
					status: 'error',
					errorCode: 'retrieval_unavailable',
				},
			} )
		);
		expect( errorRoot.textContent ).toContain(
			'Retrieval is temporarily unavailable.'
		);
	} );
} );
