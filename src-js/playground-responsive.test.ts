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

const fixture: PlaygroundResult = {
	ok: true,
	answer: 'Answer',
	no_answer: false,
	citations: [],
	model_id: 'model',
	latency_ms: 42,
	usage: {
		input_tokens: 1,
		output_tokens: 1,
		total_tokens: 2,
	},
	debug_trace: {
		query: {
			hash: 'hash',
			bytes: 4,
		},
		channels: {
			counts: {},
			failures: {},
		},
		rerank_status: 'not_requested',
		candidates: [],
	},
};

describe( 'Playground responsive structure', () => {
	it( 'provides the responsive root hook for result content', () => {
		configureTestRuntime();
		const root = document.createElement( 'div' );
		root.append( PlaygroundScreen( { result: fixture } ) as Node );

		expect(
			root.querySelector( '[data-playground-screen="result"]' )?.classList
		).toContain( 'wp-rag-ai-chatbot-playground' );
	} );

	it( 'provides the responsive root hook for the empty state', () => {
		configureTestRuntime();
		const root = document.createElement( 'div' );
		root.append( PlaygroundScreen( {} ) as Node );

		expect(
			root.querySelector( '[data-playground-screen="empty"]' )?.classList
		).toContain( 'wp-rag-ai-chatbot-playground' );
	} );

	it( 'preserves long bounded diagnostics behind native disclosure controls', () => {
		configureTestRuntime();
		const longChunk = `chunk-${ 'x'.repeat( 256 ) }`;
		const longDocument = `document-${ 'y'.repeat( 256 ) }`;
		const longContent = `content-${ 'z'.repeat( 1200 ) }`;
		const longUrl = `https://example.test/${ 'path'.repeat( 80 ) }`;
		const result: PlaygroundResult = {
			...fixture,
			citations: [
				{
					id: 'C1',
					chunk_id: longChunk,
					document_id: longDocument,
					source_id: 9,
					title: 'Long diagnostic citation',
					canonical_url: longUrl,
				},
			],
			debug_trace: {
				...fixture.debug_trace,
				candidates: [
					{
						chunk_id: longChunk,
						document_id: longDocument,
						source_id: 9,
						language: 'en',
						visibility: 'public',
						fused_score: 0.91,
						rerank_score: null,
						channel_evidence: [],
						content: longContent,
						content_truncated: false,
					},
				],
			},
		};
		const root = document.createElement( 'div' );
		root.append( PlaygroundScreen( { result } ) as Node );

		const candidate = root.querySelector< HTMLDetailsElement >(
			'[data-playground-candidate]'
		);
		const summary = candidate?.querySelector( 'summary' );

		expect( root.textContent ).toContain( longContent );
		expect( root.textContent ).toContain( longUrl );
		expect( candidate?.tagName ).toBe( 'DETAILS' );
		expect( summary?.tagName ).toBe( 'SUMMARY' );
		expect( summary?.textContent ).toContain( longChunk );
	} );
} );
