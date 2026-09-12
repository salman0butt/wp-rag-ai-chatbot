import type { PlaygroundControllerState } from './playground-controller';
import type {
	PlaygroundRequestDraft,
	PlaygroundResult,
} from './playground-screen';

type PlaygroundRuntimeFactory = (
	client: {
		request: < T >(
			path: string,
			options?: { method?: string; body?: unknown }
		) => Promise< T >;
	},
	onChange: ( state: PlaygroundControllerState ) => void
) => {
	submit: ( request: PlaygroundRequestDraft ) => Promise< void >;
};

const loadFactory = (): unknown => {
	try {
		const module = jest.requireActual( './playground-runtime' ) as Record<
			string,
			unknown
		>;
		return module.createPlaygroundRuntime;
	} catch {
		return undefined;
	}
};

const draft: PlaygroundRequestDraft = {
	bot_id: 'support-bot',
	source_id: 9,
	collection_id: 'support-docs',
	question: 'What is the return window?',
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

describe( 'createPlaygroundRuntime', () => {
	it( 'composes the existing protected API and async controller exactly once', async () => {
		const factory = loadFactory();
		expect( typeof factory ).toBe( 'function' );

		const request = jest.fn().mockResolvedValue( result );
		const onChange = jest.fn();
		const runtime = ( factory as PlaygroundRuntimeFactory )(
			{ request },
			onChange
		);

		await runtime.submit( draft );

		expect( request ).toHaveBeenCalledTimes( 1 );
		expect( request ).toHaveBeenCalledWith( '/admin/debug/playground', {
			method: 'POST',
			body: draft,
		} );
		expect( onChange ).toHaveBeenNthCalledWith( 1, { status: 'loading' } );
		expect( onChange ).toHaveBeenNthCalledWith( 2, {
			status: 'success',
			result,
		} );
	} );
} );
