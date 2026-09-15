import { createConversationRequestGenerationGuard } from './conversation-request-generation';

describe( 'M16 conversation request generation guard', () => {
	it( 'keeps only the newest request authoritative within each request scope', () => {
		const guard = createConversationRequestGenerationGuard();
		const firstList = guard.begin( 'list' );
		const firstDetail = guard.begin( 'detail' );
		const secondList = guard.begin( 'list' );

		expect( guard.isCurrent( firstList ) ).toBe( false );
		expect( guard.isCurrent( secondList ) ).toBe( true );
		expect( guard.isCurrent( firstDetail ) ).toBe( true );
	} );

	it( 'can invalidate a pending scope when navigation no longer needs it', () => {
		const guard = createConversationRequestGenerationGuard();
		const pendingDetail = guard.begin( 'detail' );

		guard.invalidate( 'detail' );

		expect( guard.isCurrent( pendingDetail ) ).toBe( false );
	} );
} );
