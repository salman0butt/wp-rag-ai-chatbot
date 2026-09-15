export interface ConversationListState {
	page: number;
	search?: string;
	botId?: string;
	unassignedOnly?: boolean;
	dateFrom?: string;
	dateTo?: string;
}

const CONVERSATION_PAGE_SIZE = 25;

const normalizedOptionalValue = (
	value: string | undefined
): string | undefined => {
	const normalized = value?.trim();

	return normalized === undefined || normalized === ''
		? undefined
		: normalized;
};

export const buildConversationListPath = (
	state: ConversationListState
): string => {
	const params = new URLSearchParams();
	const page =
		Number.isSafeInteger( state.page ) && state.page >= 1 ? state.page : 1;

	params.set( 'page', String( page ) );
	params.set( 'per_page', String( CONVERSATION_PAGE_SIZE ) );

	if ( state.unassignedOnly === true ) {
		params.set( 'unassigned_only', 'true' );
	} else {
		const botId = normalizedOptionalValue( state.botId );
		if ( botId !== undefined ) {
			params.set( 'bot_id', botId );
		}
	}

	const dateFrom = normalizedOptionalValue( state.dateFrom );
	if ( dateFrom !== undefined ) {
		params.set( 'date_from', dateFrom );
	}

	const dateTo = normalizedOptionalValue( state.dateTo );
	if ( dateTo !== undefined ) {
		params.set( 'date_to', dateTo );
	}

	const search = normalizedOptionalValue( state.search );
	if ( search !== undefined ) {
		params.set( 'search', search );
	}

	return `/admin/conversations?${ params.toString() }`;
};
