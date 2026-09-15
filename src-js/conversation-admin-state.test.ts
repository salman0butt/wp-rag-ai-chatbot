import { buildConversationListPath } from './conversation-admin-state';

describe( 'M16 conversation admin list state', () => {
	it( 'builds a bounded list request from pagination and active filters', () => {
		expect( buildConversationListPath( { page: 1 } ) ).toBe(
			'/admin/conversations?page=1&per_page=25'
		);

		expect(
			buildConversationListPath( {
				page: 3,
				search: ' refund & status ',
				botId: 'bot/one',
				dateFrom: '2026-09-01 00:00:00',
				dateTo: '2026-09-14 23:59:59',
			} )
		).toBe(
			'/admin/conversations?page=3&per_page=25&bot_id=bot%2Fone&date_from=2026-09-01+00%3A00%3A00&date_to=2026-09-14+23%3A59%3A59&search=refund+%26+status'
		);

		expect(
			buildConversationListPath( {
				page: 2,
				botId: 'ignored-when-unassigned',
				unassignedOnly: true,
			} )
		).toBe(
			'/admin/conversations?page=2&per_page=25&unassigned_only=true'
		);
	} );
} );
