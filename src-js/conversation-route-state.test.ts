import * as plugin from './index';

type ConversationRouteState = {
	page: number;
	selectedConversationId?: string;
};

type ConversationRouteStateResolver = ( hash: string ) => ConversationRouteState;

describe( 'M16 conversation admin route state', () => {
	it( 'parses bounded page state and an optional decoded conversation selection', () => {
		const exports = plugin as unknown as Record< string, unknown >;
		const resolveConversationRouteState = exports.resolveConversationRouteState;

		expect( typeof resolveConversationRouteState ).toBe( 'function' );
		if ( typeof resolveConversationRouteState !== 'function' ) {
			return;
		}

		const resolve =
			resolveConversationRouteState as ConversationRouteStateResolver;

		expect( resolve( '#/conversations' ) ).toEqual( { page: 1 } );
		expect( resolve( '#/conversations?page=3' ) ).toEqual( { page: 3 } );
		expect(
			resolve( '#/conversations/conversation%2Fone?page=2' )
		).toEqual( {
			page: 2,
			selectedConversationId: 'conversation/one',
		} );
		expect( resolve( '#/conversations/conversation-1?page=0' ) ).toEqual( {
			page: 1,
			selectedConversationId: 'conversation-1',
		} );
		expect( resolve( '#/conversations/%E0%A4%A?page=4' ) ).toEqual( {
			page: 4,
	} );
	} );
} );
