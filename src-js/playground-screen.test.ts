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

		element.setAttribute(
			key === 'htmlFor' ? 'for' : key,
			String( value )
		);
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
	answer: 'The support policy allows returns within 30 days.',
	no_answer: false,
	citations: [
		{
			id: 'C1',
			chunk_id: 'chunk-17',
			document_id: 'document-4',
			source_id: 9,
			title: 'Returns policy',
			canonical_url: 'https://example.test/returns',
		},
	],
	model_id: 'gpt-test',
	latency_ms: 42,
	usage: {
		input_tokens: 120,
		output_tokens: 24,
		total_tokens: 144,
	},
	debug_trace: {
		query: {
			hash: '0123456789abcdef',
			bytes: 31,
		},
		channels: {
			counts: { semantic: 1, lexical: 1 },
			failures: {},
		},
		rerank_status: 'not_requested',
		candidates: [
			{
				chunk_id: 'chunk-17',
				document_id: 'document-4',
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
				content: 'Returns are accepted within 30 days.',
				content_truncated: false,
			},
		],
	},
};

describe( 'PlaygroundScreen', () => {
	it( 'renders the bounded Task 6 result as structured diagnostics', () => {
		configureTestRuntime();
		const root = document.createElement( 'div' );
		root.append( PlaygroundScreen( { result: fixture } ) as Node );

		expect( root.querySelector( 'h2' )?.textContent ).toBe( 'Playground' );
		expect(
			root.querySelector( '[data-playground-answer]' )?.textContent
		).toContain( '30 days' );
		expect(
			root.querySelector( '[data-playground-candidates]' )?.textContent
		).toContain( 'chunk-17' );
		expect(
			root.querySelector( '[data-playground-candidates]' )?.textContent
		).toContain( 'semantic' );
		expect(
			root.querySelector( '[data-playground-citations]' )?.textContent
		).toContain( 'Returns policy' );
		expect(
			root.querySelector( '[data-playground-model]' )?.textContent
		).toContain( 'gpt-test' );
		expect(
			root.querySelector( '[data-playground-usage]' )?.textContent
		).toContain( '144' );
	} );

	it.each( [
		[ 'retrieval_unavailable', 'Retrieval is temporarily unavailable.' ],
		[
			'playground_failed',
			'The Playground request could not be completed.',
		],
	] )(
		'maps %s to repository-owned safe copy',
		( errorCode, expectedCopy ) => {
			configureTestRuntime();
			const root = document.createElement( 'div' );
			root.append( PlaygroundScreen( { errorCode } ) as Node );

			expect( root.querySelector( '[role="alert"]' )?.textContent ).toBe(
				expectedCopy
			);
		}
	);

	it( 'uses generic safe copy for unknown error codes', () => {
		configureTestRuntime();
		const root = document.createElement( 'div' );
		root.append(
			PlaygroundScreen( {
				errorCode: 'UPSTREAM_SECRET_SENTINEL',
			} ) as Node
		);

		expect( root.textContent ).not.toContain( 'UPSTREAM_SECRET_SENTINEL' );
		expect( root.querySelector( '[role="alert"]' )?.textContent ).toBe(
			'The Playground request could not be completed.'
		);
	} );
} );
