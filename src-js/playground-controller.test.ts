import type {
	PlaygroundRequestDraft,
	PlaygroundResult,
} from './playground-screen';

type PlaygroundControllerState =
	| { status: 'idle' }
	| { status: 'loading' }
	| { status: 'success'; result: PlaygroundResult }
	| { status: 'error'; errorCode: string };

type PlaygroundControllerFactory = (
	api: { run: ( request: PlaygroundRequestDraft ) => Promise< PlaygroundResult > },
	onChange: ( state: PlaygroundControllerState ) => void
) => {
	submit: ( request: PlaygroundRequestDraft ) => Promise< void >;
};

const loadFactory = (): unknown => {
	try {
		const module = jest.requireActual( './playground-controller' ) as Record<
			string,
			unknown
		>;
		return module.createPlaygroundController;
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

describe( 'createPlaygroundController', () => {
	it( 'announces loading then publishes the bounded Task 6 result', async () => {
		const factory = loadFactory();
		expect( typeof factory ).toBe( 'function' );

		let resolveRun: ( value: PlaygroundResult ) => void = () => undefined;
		const run = jest.fn().mockReturnValue(
			new Promise< PlaygroundResult >( ( resolve ) => {
				resolveRun = resolve;
			} )
		);
		const onChange = jest.fn();
		const controller = ( factory as PlaygroundControllerFactory )(
			{ run },
			onChange
		);
		const pending = controller.submit( draft );

		expect( run ).toHaveBeenCalledTimes( 1 );
		expect( run ).toHaveBeenCalledWith( draft );
		expect( onChange ).toHaveBeenNthCalledWith( 1, { status: 'loading' } );

		resolveRun( result );
		await pending;

		expect( onChange ).toHaveBeenNthCalledWith( 2, {
			status: 'success',
			result,
		} );
	} );

	it( 'projects only a stable error code and never arbitrary backend messages', async () => {
		const factory = loadFactory();
		expect( typeof factory ).toBe( 'function' );

		const run = jest.fn().mockRejectedValue( {
			code: 'retrieval_unavailable',
			message: 'UPSTREAM_SECRET_SENTINEL',
		} );
		const onChange = jest.fn();
		const controller = ( factory as PlaygroundControllerFactory )(
			{ run },
			onChange
		);

		await controller.submit( draft );

		expect( onChange ).toHaveBeenLastCalledWith( {
			status: 'error',
			errorCode: 'retrieval_unavailable',
		} );
		expect( JSON.stringify( onChange.mock.calls ) ).not.toContain(
			'UPSTREAM_SECRET_SENTINEL'
		);
	} );
} );
