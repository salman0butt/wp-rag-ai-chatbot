export type ConversationRequestScope = 'list' | 'detail';

export interface ConversationRequestGeneration {
	readonly scope: ConversationRequestScope;
	readonly generation: number;
}

export interface ConversationRequestGenerationGuard {
	begin: ( scope: ConversationRequestScope ) => ConversationRequestGeneration;
	invalidate: ( scope: ConversationRequestScope ) => void;
	isCurrent: ( request: ConversationRequestGeneration ) => boolean;
}

export const createConversationRequestGenerationGuard = (): ConversationRequestGenerationGuard => {
	const generations: Record< ConversationRequestScope, number > = {
		list: 0,
		detail: 0,
	};

	const advance = ( scope: ConversationRequestScope ): number => {
		generations[ scope ] += 1;
		return generations[ scope ];
	};

	return {
		begin( scope ) {
			return Object.freeze( {
				scope,
				generation: advance( scope ),
			} );
		},
		invalidate( scope ) {
			advance( scope );
		},
		isCurrent( request ) {
			return generations[ request.scope ] === request.generation;
		},
	};
};
