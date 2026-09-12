type PlaygroundRequestDraft = {
	bot_id: string;
	source_id: number;
	collection_id: string;
	question: string;
};

type PlaygroundResult = {
	ok: true;
	answer: string;
};

type PlaygroundApiFactory = ( client: {
	request: < T >(
		path: string,
		options?: { method?: string; body?: unknown }
	) => Promise< T >;
} ) => {
	run: ( request: PlaygroundRequestDraft ) => Promise< PlaygroundResult >;
};

const loadPlaygroundApiFactory = (): unknown => {
	try {
		const module = jest.requireActual( './playground-api' ) as Record<
			string,
			unknown
		>;
		return module.createPlaygroundApi;
	} catch {
		return undefined;
	}
};

describe( 'createPlaygroundApi', () => {
	it( 'posts only the bounded Task 6 request to the protected Playground route', async () => {
		const factory = loadPlaygroundApiFactory();
		expect( typeof factory ).toBe( 'function' );

		const result: PlaygroundResult = {
			ok: true,
			answer: 'Returns are accepted within 30 days.',
		};
		const request = jest.fn().mockResolvedValue( result );
		const api = ( factory as PlaygroundApiFactory )( { request } );
		const draft: PlaygroundRequestDraft = {
			bot_id: 'support-bot',
			source_id: 9,
			collection_id: 'support-docs',
			question: 'What is the return window?',
		};

		await expect( api.run( draft ) ).resolves.toBe( result );
		expect( request ).toHaveBeenCalledTimes( 1 );
		expect( request ).toHaveBeenCalledWith( '/admin/debug/playground', {
			method: 'POST',
			body: draft,
		} );
		expect( Object.keys( request.mock.calls[ 0 ][ 1 ].body ) ).toEqual( [
			'bot_id',
			'source_id',
			'collection_id',
			'question',
		] );
	} );
} );
