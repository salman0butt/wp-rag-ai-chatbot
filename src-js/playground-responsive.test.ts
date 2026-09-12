import { PlaygroundScreen, type PlaygroundResult } from './playground-screen';

type TestElementProps = Record< string, unknown > | null;

const createTestElement = (
	tagName: string,
	props: TestElementProps,
	...children: Array< Node | string | number | undefined | null >
): HTMLElement => {
	const element = document.createElement( tagName );

	for ( const [ key, value ] of Object.entries( props ?? {} ) ) {
		if ( key === 'key' || value === undefined || value === null ) {
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
		if ( child === undefined || child === null ) {
			continue;
		}

		if ( child instanceof Node ) {
			element.append( child );
		} else {
			element.append( String( child ) );
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

const longValue = ( prefix: string ): string => `${ prefix }-${ 'x'.repeat( 320 ) }`;

const fixture: PlaygroundResult = {
	ok: true,
	answer: longValue( 'answer' ),
	no_answer: false,
	citations: [
		{
			id: 'C1',
			chunk_id: longValue( 'citation-chunk' ),
			document_id: longValue( 'citation-document' ),
			source_id: 9,
			title: longValue( 'citation-title' ),
			canonical_url: `https://example.test/${ 'path'.repeat( 100 ) }`,
		},
	],
	model_id: longValue( 'model' ),
	latency_ms: 42,
	usage: {
		input_tokens: 120,
		output_tokens: 24,
		total_tokens: 144,
	},
	debug_trace: {
		query: {
			hash: longValue( 'hash' ),
			bytes: 31,
		},
		channels: {
			counts: { semantic: 1 },
			failures: {},
		},
		rerank_status: 'not_requested',
		candidates: [
			{
				chunk_id: longValue( 'candidate-chunk' ),
				document_id: longValue( 'candidate-document' ),
				source_id: 9,
				language: 'en',
				visibility: 'public',
				fused_score: 0.91,
				rerank_score: null,
				channel_evidence: [
					{
						channel: 'semantic',
						native_score: 0.87,
						rank: 1,
						weight: 1,
						rrf_contribution: 0.016,
					},
				],
				content: longValue( 'candidate-content' ),
				content_truncated: false,
			},
		],
	},
};

describe( 'Playground responsive and keyboard structure', () => {
	it( 'provides the responsive root hook while preserving long diagnostics behind native disclosure controls', () => {
		configureTestRuntime();
		const root = document.createElement( 'div' );
		root.append( PlaygroundScreen( { result: fixture } ) as Node );

		const screen = root.querySelector( '[data-playground-screen="result"]' );
		const candidate = root.querySelector< HTMLDetailsElement >(
			'[data-playground-candidate]'
		);
		const summary = candidate?.querySelector( 'summary' );

		expect( screen?.classList ).toContain( 'wp-rag-ai-chatbot-playground' );
		expect( root.textContent ).toContain( fixture.answer );
		expect( root.textContent ).toContain(
			fixture.debug_trace.candidates[ 0 ].content
		);
		expect( candidate?.tagName ).toBe( 'DETAILS' );
		expect( summary?.tagName ).toBe( 'SUMMARY' );
		expect( summary?.textContent ).toContain(
			fixture.debug_trace.candidates[ 0 ].chunk_id
		);
	} );

	it( 'keeps the responsive root hook for the empty state', () => {
		configureTestRuntime();
		const root = document.createElement( 'div' );
		root.append( PlaygroundScreen( {} ) as Node );

		expect(
			root.querySelector( '[data-playground-screen="empty"]' )?.classList
		).toContain( 'wp-rag-ai-chatbot-playground' );
	} );
} );
