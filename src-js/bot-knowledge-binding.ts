type ElementFactory = (
	tagName: string,
	props: Record< string, unknown > | null,
	...children: unknown[]
) => unknown;

export interface KnowledgeSourceChoice {
	id: string | number;
	title: string;
	source_type?: string;
	status?: string;
}

export interface BotRetrievalProjection {
	configured: boolean;
	source_id: number | null;
	source_title: string | null;
	collection_id: string | null;
	collection_ready: boolean;
}

export type BotKnowledgeBindingStatus =
	| 'loading'
	| 'saving'
	| 'ready'
	| 'error';

export type BotKnowledgeSourcesStatus = 'loading' | 'ready' | 'error';

export interface BotKnowledgeBindingProps {
	botId: string;
	sources: ReadonlyArray< KnowledgeSourceChoice >;
	sourcesStatus?: BotKnowledgeSourcesStatus;
	sourcesError?: string;
	retrieval: BotRetrievalProjection;
	status?: BotKnowledgeBindingStatus;
	error?: string;
	onSave?: ( sourceId: number ) => void | Promise< void >;
	onDisconnect?: () => void | Promise< void >;
}

export interface BotKnowledgeBindingApiClient {
	request: < T >(
		path: string,
		options?: { method?: string; body?: unknown }
	) => Promise< T >;
}

export const saveBotKnowledgeBinding = (
	client: BotKnowledgeBindingApiClient,
	botId: string,
	sourceId: number
): Promise< unknown > =>
	client.request( `/admin/bots/${ encodeURIComponent( botId ) }/retrieval`, {
		method: 'PUT',
		body: { source_id: sourceId },
	} );

export const deleteBotKnowledgeBinding = (
	client: BotKnowledgeBindingApiClient,
	botId: string
): Promise< unknown > =>
	client.request( `/admin/bots/${ encodeURIComponent( botId ) }/retrieval`, {
		method: 'DELETE',
	} );

const positiveSourceId = ( source: KnowledgeSourceChoice ): number | null => {
	const id = typeof source.id === 'number' ? source.id : Number( source.id );

	return Number.isSafeInteger( id ) && id > 0 ? id : null;
};

const sourceLabel = ( source: KnowledgeSourceChoice ): string =>
	source.source_type === undefined
		? source.title
		: `${ source.title } (${ source.source_type })`;

export const BotKnowledgeBinding = (
	props: BotKnowledgeBindingProps
): unknown => {
	const createElement = window.wp.element.createElement as ElementFactory;
	const selectedSourceId = props.retrieval.source_id?.toString() ?? '';
	const busy = props.status === 'loading' || props.status === 'saving';
	const sourcesStatus = props.sourcesStatus ?? 'ready';
	const sources = props.sources.flatMap( ( source ) => {
		const id = positiveSourceId( source );
		return id === null
			? []
			: [
					createElement(
						'option',
						{ key: id, value: id },
						sourceLabel( source )
					),
			  ];
	} );
	const statusText = (): string => {
		const messages: string[] = [];
		if ( props.status === 'loading' ) {
			messages.push( 'Loading knowledge connection…' );
		} else if ( props.status === 'saving' ) {
			messages.push( 'Saving knowledge connection…' );
		} else if ( props.error !== undefined ) {
			messages.push( props.error );
		} else if ( ! props.retrieval.configured ) {
			messages.push( 'Not connected' );
		} else {
			messages.push(
				props.retrieval.collection_ready
					? 'Connected and indexed'
					: 'Connected; indexing is not ready'
			);
		}
		if ( sourcesStatus === 'loading' ) {
			messages.push( 'Loading saved sources…' );
		} else if ( props.sourcesError !== undefined ) {
			messages.push( props.sourcesError );
		}

		return messages.join( ' ' );
	};
	let sourcePrompt = 'Choose a saved source';
	if ( sourcesStatus === 'loading' ) {
		sourcePrompt = 'Loading saved sources…';
	} else if ( props.sourcesError !== undefined ) {
		sourcePrompt = 'Saved sources unavailable';
	} else if ( sources.length === 0 ) {
		sourcePrompt = 'No saved sources yet';
	}

	const currentSource = props.retrieval.configured
		? createElement(
				'dl',
				{ className: 'wp-rag-ai-bot-knowledge-binding__details' },
				createElement( 'dt', null, 'Connected source' ),
				createElement(
					'dd',
					null,
					props.retrieval.source_title ?? 'Unknown source'
				),
				createElement( 'dt', null, 'Collection' ),
				createElement(
					'dd',
					null,
					props.retrieval.collection_id ?? 'Unavailable'
				),
				createElement( 'dt', null, 'Index status' ),
				createElement(
					'dd',
					null,
					props.retrieval.collection_ready ? 'Ready' : 'Not ready'
				)
		  )
		: undefined;

	return createElement(
		'section',
		{
			className: 'wp-rag-ai-bot-knowledge-binding',
			'data-bot-knowledge-binding': props.botId,
		},
		createElement( 'h2', null, 'Knowledge' ),
		createElement(
			'p',
			{
				'aria-live': 'polite',
				'aria-atomic': 'true',
				'data-bot-knowledge-status': true,
				role:
					props.error === undefined &&
					props.sourcesError === undefined
						? 'status'
						: 'alert',
			},
			statusText()
		),
		createElement(
			'form',
			{
				'data-bot-knowledge-form': true,
				onSubmit: ( event: Event ) => {
					event.preventDefault();
					const form = event.currentTarget as HTMLFormElement;
					const sourceId = Number(
						(
							form.elements.namedItem(
								'source_id'
							) as HTMLSelectElement
						 )?.value ?? ''
					);
					if ( Number.isSafeInteger( sourceId ) && sourceId > 0 ) {
						void props.onSave?.( sourceId );
					}
				},
			},
			createElement(
				'label',
				{ htmlFor: `bot-${ props.botId }-source` },
				'Knowledge source'
			),
			createElement(
				'select',
				{
					defaultValue: selectedSourceId,
					disabled:
						busy ||
						sourcesStatus !== 'ready' ||
						sources.length === 0,
					id: `bot-${ props.botId }-source`,
					name: 'source_id',
				},
				createElement( 'option', { value: '' }, sourcePrompt ),
				...sources
			),
			createElement(
				'button',
				{
					disabled:
						busy ||
						sourcesStatus !== 'ready' ||
						sources.length === 0,
					type: 'submit',
				},
				props.status === 'saving' ? 'Saving…' : 'Save connection'
			)
		),
		currentSource,
		props.retrieval.configured
			? createElement(
					'button',
					{
						'data-bot-knowledge-disconnect': true,
						disabled: busy ? true : undefined,
						onClick: () => void props.onDisconnect?.(),
						type: 'button',
					},
					'Disconnect knowledge'
			  )
			: undefined
	);
};
