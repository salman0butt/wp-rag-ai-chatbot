import type { ConversationSummary } from './conversation-inbox-screen';
import {
	buildConversationListPath,
	type ConversationListState,
} from './conversation-admin-state';
import { createConversationRequestGenerationGuard } from './conversation-request-generation';

interface ConversationAdminApi {
	request: ( path: string ) => Promise< unknown >;
}

export interface ConversationListResponse {
	items: ConversationSummary[];
	page: number;
	per_page: number;
}

export interface ConversationDetailMessage {
	role: string;
	content: string;
	created_at: string;
}

export interface ConversationDetail {
	conversation_id: string;
	bot_id: string | null;
	started_at: string;
	messages: ConversationDetailMessage[];
}

export interface ConversationDetailResponse {
	conversation: ConversationDetail;
}

export interface ConversationAdminLoader {
	loadList: (
		state: ConversationListState,
		apply: ( response: ConversationListResponse ) => void
	) => Promise< void >;
	loadDetail: (
		conversationId: string,
		apply: ( detail: ConversationDetail ) => void
	) => Promise< void >;
	invalidateDetail: () => void;
}

export const createConversationAdminLoader = (
	api: ConversationAdminApi
): ConversationAdminLoader => {
	const generations = createConversationRequestGenerationGuard();

	return {
		async loadList( state, apply ) {
			const generation = generations.begin( 'list' );
			const response = ( await api.request(
				buildConversationListPath( state )
			) ) as ConversationListResponse;

			if ( generations.isCurrent( generation ) ) {
				apply( response );
			}
		},
		async loadDetail( conversationId, apply ) {
			const generation = generations.begin( 'detail' );
			const response = ( await api.request(
				`/admin/conversations/${ encodeURIComponent(
					conversationId
				) }?message_limit=100`
			) ) as ConversationDetailResponse;

			if ( generations.isCurrent( generation ) ) {
				apply( response.conversation );
			}
		},
		invalidateDetail() {
			generations.invalidate( 'detail' );
		},
	};
};
