export interface PlaygroundCitation {
	id: string;
	chunk_id: string;
	document_id: string;
	source_id: number;
	title: string | null;
	canonical_url: string | null;
}

export interface PlaygroundChannelEvidence {
	channel: string;
	native_score: number;
	rank: number;
	weight: number;
	rrf_contribution: number;
}

export interface PlaygroundCandidate {
	chunk_id: string;
	document_id: string;
	source_id: number;
	language: string | null;
	visibility: string;
	fused_score: number;
	rerank_score: number | null;
	channel_evidence: PlaygroundChannelEvidence[];
	content: string;
	content_truncated: boolean;
}

export interface PlaygroundResult {
	ok: true;
	answer: string;
	no_answer: boolean;
	citations: PlaygroundCitation[];
	model_id: string;
	latency_ms: number;
	usage: {
		input_tokens: number | null;
		output_tokens: number | null;
		total_tokens: number | null;
	};
	debug_trace: {
		query: {
			hash: string;
			bytes: number;
		};
		channels: {
			counts: Record< string, number >;
			failures: Record< string, string >;
		};
		rerank_status: string;
		candidates: PlaygroundCandidate[];
	};
}

export interface PlaygroundRequestDraft {
	bot_id: string;
	source_id: number;
	collection_id: string;
	question: string;
}

export interface PlaygroundScreenProps {
	result?: PlaygroundResult;
	errorCode?: string;
	onSubmit?: ( request: PlaygroundRequestDraft ) => void;
}

const PLAYGROUND_ERROR_MESSAGES: Record< string, string > = {
	invalid_request:
		'Check the persisted selectors and question, then try again.',
	retrieval_unavailable: 'Retrieval is temporarily unavailable.',
	playground_failed: 'The Playground request could not be completed.',
};

const GENERIC_PLAYGROUND_ERROR =
	'The Playground request could not be completed.';

const tokenValue = ( value: number | null ): string =>
	value === null ? 'Not reported' : String( value );

const formValue = ( form: HTMLFormElement, name: string ): string => {
	const control = form.elements.namedItem( name );

	if (
		control instanceof HTMLInputElement ||
		control instanceof HTMLTextAreaElement
	) {
		return control.value;
	}

	return '';
};

const playgroundForm = (
	createElement: typeof window.wp.element.createElement,
	onSubmit?: ( request: PlaygroundRequestDraft ) => void
): unknown => {
	const handleSubmit = ( event: Event ): void => {
		event.preventDefault();
		const form = event.currentTarget;

		if ( onSubmit === undefined || ! ( form instanceof HTMLFormElement ) ) {
			return;
		}

		onSubmit( {
			bot_id: formValue( form, 'bot_id' ),
			source_id: Number( formValue( form, 'source_id' ) ),
			collection_id: formValue( form, 'collection_id' ),
			question: formValue( form, 'question' ),
		} );
	};

	return createElement(
		'form',
		{ 'data-playground-form': true, onSubmit: handleSubmit },
		createElement( 'label', { htmlFor: 'playground-bot-id' }, 'Bot ID' ),
		createElement( 'input', {
			id: 'playground-bot-id',
			name: 'bot_id',
			type: 'text',
			required: true,
			maxLength: 256,
		} ),
		createElement(
			'label',
			{ htmlFor: 'playground-source-id' },
			'Source ID'
		),
		createElement( 'input', {
			id: 'playground-source-id',
			name: 'source_id',
			type: 'number',
			min: 1,
			step: 1,
			required: true,
		} ),
		createElement(
			'label',
			{ htmlFor: 'playground-collection-id' },
			'Collection ID'
		),
		createElement( 'input', {
			id: 'playground-collection-id',
			name: 'collection_id',
			type: 'text',
			required: true,
			maxLength: 256,
		} ),
		createElement(
			'label',
			{ htmlFor: 'playground-question' },
			'Question'
		),
		createElement( 'textarea', {
			id: 'playground-question',
			name: 'question',
			required: true,
			maxLength: 16384,
		} ),
		createElement( 'button', { type: 'submit' }, 'Run Playground' )
	);
};

export const PlaygroundScreen = ( {
	result,
	errorCode,
	onSubmit,
}: PlaygroundScreenProps ): unknown => {
	const createElement = window.wp.element.createElement;
	const error =
		errorCode === undefined
			? undefined
			: createElement(
					'div',
					{ role: 'alert', 'data-playground-error': true },
					PLAYGROUND_ERROR_MESSAGES[ errorCode ] ??
						GENERIC_PLAYGROUND_ERROR
			  );
	const form = playgroundForm( createElement, onSubmit );

	if ( result === undefined ) {
		return createElement(
			'section',
			{
				className: 'wp-rag-ai-chatbot-playground',
				'data-playground-screen': 'empty',
			},
			createElement( 'h2', null, 'Playground' ),
			form,
			error,
			createElement(
				'p',
				null,
				'Submit a question to inspect the bounded production retrieval trace.'
			)
		);
	}

	const citationItems = result.citations.map( ( citation ) =>
		createElement(
			'li',
			{ key: citation.id },
			createElement( 'strong', null, citation.title ?? citation.id ),
			createElement(
				'p',
				null,
				`Source ${ citation.source_id }; document ${ citation.document_id }; chunk ${ citation.chunk_id }`
			),
			citation.canonical_url === null
				? undefined
				: createElement( 'code', null, citation.canonical_url )
		)
	);
	const candidateItems = result.debug_trace.candidates.map( ( candidate ) => {
		const evidence = candidate.channel_evidence.map( ( item, index ) =>
			createElement(
				'li',
				{ key: `${ item.channel }-${ index }` },
				`${ item.channel }: native ${ item.native_score }; rank ${ item.rank }; weight ${ item.weight }; RRF ${ item.rrf_contribution }`
			)
		);

		return createElement(
			'details',
			{
				key: candidate.chunk_id,
				'data-playground-candidate': candidate.chunk_id,
			},
			createElement(
				'summary',
				null,
				`${ candidate.chunk_id } — fused ${ candidate.fused_score }`
			),
			createElement(
				'p',
				null,
				`Document ${ candidate.document_id }; source ${ candidate.source_id }`
			),
			createElement(
				'p',
				null,
				`Visibility ${ candidate.visibility }; language ${
					candidate.language ?? 'not reported'
				}`
			),
			createElement(
				'p',
				null,
				`Rerank score ${
					candidate.rerank_score === null
						? 'not reported'
						: candidate.rerank_score
				}`
			),
			createElement( 'p', null, candidate.content ),
			candidate.content_truncated
				? createElement( 'p', null, 'Content truncated by the server.' )
				: undefined,
			createElement( 'ul', null, ...evidence )
		);
	} );
	const channelCounts = Object.entries(
		result.debug_trace.channels.counts
	).map( ( [ channel, count ] ) => `${ channel }: ${ count }` );
	const channelFailures = Object.entries(
		result.debug_trace.channels.failures
	).map( ( [ channel, code ] ) => `${ channel }: ${ code }` );

	return createElement(
		'section',
		{
			className: 'wp-rag-ai-chatbot-playground',
			'data-playground-screen': 'result',
		},
		createElement( 'h2', null, 'Playground' ),
		form,
		error,
		createElement(
			'section',
			{ 'data-playground-answer': true },
			createElement( 'h3', null, 'Answer' ),
			createElement( 'p', null, result.answer ),
			result.no_answer
				? createElement(
						'p',
						null,
						'The production graph returned no answer.'
				  )
				: undefined
		),
		createElement(
			'section',
			{ 'data-playground-citations': true },
			createElement( 'h3', null, 'Citations' ),
			result.citations.length === 0
				? createElement( 'p', null, 'No citations returned.' )
				: createElement( 'ol', null, ...citationItems )
		),
		createElement(
			'section',
			{ 'data-playground-model': true },
			createElement( 'h3', null, 'Execution' ),
			createElement( 'p', null, `Model ${ result.model_id }` ),
			createElement( 'p', null, `Latency ${ result.latency_ms } ms` )
		),
		createElement(
			'section',
			{ 'data-playground-usage': true },
			createElement( 'h3', null, 'Usage' ),
			createElement(
				'p',
				null,
				`Input ${ tokenValue(
					result.usage.input_tokens
				) }; output ${ tokenValue(
					result.usage.output_tokens
				) }; total ${ tokenValue( result.usage.total_tokens ) }`
			)
		),
		createElement(
			'section',
			{ 'data-playground-trace': true },
			createElement( 'h3', null, 'Retrieval trace' ),
			createElement(
				'p',
				null,
				`Query hash ${ result.debug_trace.query.hash }; ${ result.debug_trace.query.bytes } bytes`
			),
			createElement(
				'p',
				null,
				`Rerank ${ result.debug_trace.rerank_status }`
			),
			createElement(
				'p',
				null,
				`Channels ${ channelCounts.join( '; ' ) || 'none' }`
			),
			channelFailures.length === 0
				? undefined
				: createElement(
						'p',
						null,
						`Channel failures ${ channelFailures.join( '; ' ) }`
				  )
		),
		createElement(
			'section',
			{ 'data-playground-candidates': true },
			createElement( 'h3', null, 'Candidates' ),
			result.debug_trace.candidates.length === 0
				? createElement( 'p', null, 'No candidates returned.' )
				: createElement( 'div', null, ...candidateItems )
		)
	);
};
