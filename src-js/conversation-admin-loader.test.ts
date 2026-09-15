import { createConversationAdminLoader } from './conversation-admin-loader';

const deferred = < T >() => {
	let resolve!: ( value: T ) => void;
	const promise = new Promise< T >( ( resolver ) => {
		resolve = resolver;
	} );

	return { promise, resolve };
};

describe( 'createConversationAdminLoader', () => {
	it( 'applies only the newest list response', async () => {
		const first = deferred< {
			items: [];
			page: number;
			per_page: number;
		} >();
		const second = deferred< {
			items: [];
			page: number;
			per_page: number;
		} >();
		const request = jest
			.fn()
			.mockReturnValueOnce( first.promise )
			.mockReturnValueOnce( second.promise );
		const loader = createConversationAdminLoader( { request } );
		const apply = jest.fn();

		const firstLoad = loader.loadList( { page: 1 }, apply );
		const secondLoad = loader.loadList( { page: 2 }, apply );

		second.resolve( { items: [], page: 2, per_page: 25 } );
		await secondLoad;
		first.resolve( { items: [], page: 1, per_page: 25 } );
		await firstLoad;

		expect( request ).toHaveBeenNthCalledWith(
			1,
			'/admin/conversations?page=1&per_page=25'
		);
		expect( request ).toHaveBeenNthCalledWith(
			2,
			'/admin/conversations?page=2&per_page=25'
		);
		expect( apply ).toHaveBeenCalledTimes( 1 );
		expect( apply ).toHaveBeenCalledWith( {
			items: [],
			page: 2,
			per_page: 25,
		} );
	} );

	it( 'invalidates an obsolete detail response and encodes its identifier', async () => {
		const detail = deferred< {
			conversation: {
				conversation_id: string;
				bot_id: null;
				started_at: string;
				messages: [];
			};
		} >();
		const request = jest.fn().mockReturnValue( detail.promise );
		const loader = createConversationAdminLoader( { request } );
		const apply = jest.fn();

		const pending = loader.loadDetail( 'conversation/1', apply );
		loader.invalidateDetail();
		detail.resolve( {
			conversation: {
				conversation_id: 'conversation/1',
				bot_id: null,
				started_at: '2026-09-14 12:00:00',
				messages: [],
			},
		} );
		await pending;

		expect( request ).toHaveBeenCalledWith(
			'/admin/conversations/conversation%2F1?message_limit=100'
		);
		expect( apply ).not.toHaveBeenCalled();
	} );
} );
