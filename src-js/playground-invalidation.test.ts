import type { PlaygroundApi } from './playground-api';
import { createPlaygroundController } from './playground-controller';
import type {
	PlaygroundRequestDraft,
	PlaygroundResult,
} from './playground-screen';

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

describe( 'Playground request invalidation', () => {
	it( 'suppresses an in-flight completion after the controller is invalidated', async () => {
		let resolveRun: ( value: PlaygroundResult ) => void = () => undefined;
		const api: PlaygroundApi = {
			run: jest.fn().mockReturnValue(
				new Promise< PlaygroundResult >( ( resolve ) => {
					resolveRun = resolve;
				} )
			),
		};
		const onChange = jest.fn();
		const controller = createPlaygroundController( api, onChange );
		const pending = controller.submit( draft );
		const invalidate = (
			controller as unknown as { invalidate?: () => void }
		).invalidate;

		expect( typeof invalidate ).toBe( 'function' );
		invalidate?.();
		resolveRun( result );
		await pending;

		expect( onChange ).toHaveBeenCalledTimes( 1 );
		expect( onChange ).toHaveBeenCalledWith( { status: 'loading' } );
		expect( onChange ).not.toHaveBeenCalledWith( {
			status: 'success',
			result,
		} );
	} );
} );
