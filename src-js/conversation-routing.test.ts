import { resolveAdminScreen } from './index';

describe( 'M16 conversation admin routing', () => {
	it( 'resolves the conversation inbox and detail hashes to the conversations screen', () => {
		expect( String( resolveAdminScreen( '#/conversations' ) ) ).toBe(
			'conversations'
		);
		expect(
			String(
				resolveAdminScreen( '#/conversations/conversation-1?page=2' )
			)
		).toBe( 'conversations' );
	} );
} );
