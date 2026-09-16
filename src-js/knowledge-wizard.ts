export type KnowledgeSourceType =
	| 'wordpress_posts'
	| 'manual_text'
	| 'faq'
	| 'woocommerce_product'
	| 'file';

export interface KnowledgeFaqItem {
	question: string;
	answer: string;
}

export interface KnowledgeSourceDraft {
	sourceType: KnowledgeSourceType;
	title: string;
	text: string;
	faqItems: KnowledgeFaqItem[];
	includePrivate: boolean;
	postTypes: string[];
	woocommerceMode: 'catalog' | 'selected';
	productIds: string;
	file: File | null;
}

export interface KnowledgeSourceRequestJson {
	kind: 'json';
	body: {
		source_type: KnowledgeSourceType;
		title?: string;
		config: Record< string, unknown >;
	};
}

export interface KnowledgeSourceRequestFormData {
	kind: 'formData';
	body: FormData;
}

export type KnowledgeSourceRequest =
	| KnowledgeSourceRequestJson
	| KnowledgeSourceRequestFormData;

export interface KnowledgeWizardSource {
	id: string | number;
	title: string;
	source_type: string;
	status: string;
}

export interface KnowledgeWizardJob {
	job_key: string;
	type: string;
	status: string;
	progress_current: number;
	progress_total: number;
	last_error_code: string | null;
	last_error_message: string | null;
}

export interface KnowledgeWizardProps {
	sources?: ReadonlyArray< KnowledgeWizardSource >;
	jobs?: ReadonlyArray< KnowledgeWizardJob >;
	woocommerceAvailable?: boolean;
	submitting?: boolean;
	onCreate?: ( draft: KnowledgeSourceDraft ) => Promise< void >;
	onCancelJob?: ( job: KnowledgeWizardJob ) => Promise< void >;
	onRetryJob?: ( job: KnowledgeWizardJob ) => Promise< void >;
}

export type KnowledgeDraftErrors = Partial<
	Record<
		| 'sourceType'
		| 'title'
		| 'text'
		| 'faqItems'
		| 'woocommerce'
		| 'productIds'
		| 'file',
		string
	>
>;

const SOURCE_TYPES: ReadonlyArray< {
	type: KnowledgeSourceType;
	label: string;
	description: string;
} > = [
	{
		type: 'wordpress_posts',
		label: 'WordPress content',
		description: 'Use public posts and pages with safe defaults.',
	},
	{
		type: 'manual_text',
		label: 'Manual text',
		description: 'Paste a bounded piece of support content.',
	},
	{
		type: 'faq',
		label: 'FAQ',
		description: 'Add repeatable question and answer pairs.',
	},
	{
		type: 'woocommerce_product',
		label: 'WooCommerce catalog',
		description: 'Index public products or selected product IDs.',
	},
	{
		type: 'file',
		label: 'Upload a file',
		description: 'Upload supported text or document content.',
	},
];

const text = ( value: string ): string => value.trim();

export const createKnowledgeDraft = (
	sourceType: KnowledgeSourceType = 'wordpress_posts'
): KnowledgeSourceDraft => ( {
	sourceType,
	title: '',
	text: '',
	faqItems: [ { question: '', answer: '' } ],
	includePrivate: false,
	postTypes: [],
	woocommerceMode: 'catalog',
	productIds: '',
	file: null,
} );

export const validateKnowledgeDraft = (
	draft: KnowledgeSourceDraft,
	woocommerceAvailable = false
): KnowledgeDraftErrors => {
	const errors: KnowledgeDraftErrors = {};

	if ( ! SOURCE_TYPES.some( ( item ) => item.type === draft.sourceType ) ) {
		errors.sourceType = 'Choose a knowledge source type.';
		return errors;
	}

	if ( draft.sourceType === 'manual_text' ) {
		if ( text( draft.title ) === '' ) {
			errors.title = 'Enter a title for this source.';
		}
		if ( text( draft.text ) === '' ) {
			errors.text = 'Enter some text for this source.';
		}
	}

	if ( draft.sourceType === 'faq' ) {
		if ( text( draft.title ) === '' ) {
			errors.title = 'Enter a title for this source.';
		}
		if (
			draft.faqItems.length === 0 ||
			draft.faqItems.some(
				( item ) =>
					text( item.question ) === '' || text( item.answer ) === ''
			)
		) {
			errors.faqItems = 'Complete every FAQ question and answer.';
		}
	}

	if ( draft.sourceType === 'woocommerce_product' ) {
		if ( ! woocommerceAvailable ) {
			errors.woocommerce = 'WooCommerce is not available on this site.';
		} else if ( draft.woocommerceMode === 'selected' ) {
			const ids = draft.productIds
				.split( ',' )
				.map( ( id ) => id.trim() )
				.filter( Boolean );
			if (
				ids.length === 0 ||
				ids.some( ( id ) => ! /^[1-9]\d*$/.test( id ) )
			) {
				errors.productIds = 'Enter one or more valid product IDs.';
			}
		}
	}

	if ( draft.sourceType === 'file' ) {
		if ( draft.file === null ) {
			errors.file = 'Choose a file to upload.';
		} else if (
			draft.file.size < 1 ||
			draft.file.size > 10 * 1024 * 1024
		) {
			errors.file = 'Choose a file smaller than 10 MB.';
		}
	}

	return errors;
};

export const buildKnowledgeSourceRequest = (
	draft: KnowledgeSourceDraft
): KnowledgeSourceRequest => {
	const title = text( draft.title );

	if ( draft.sourceType === 'file' ) {
		const body = new FormData();
		body.set( 'source_type', 'file' );
		if ( title !== '' ) {
			body.set( 'title', title );
		}
		if ( draft.file !== null ) {
			body.set( 'file', draft.file );
		}
		return { kind: 'formData', body };
	}

	let config: Record< string, unknown >;
	switch ( draft.sourceType ) {
		case 'manual_text':
			config = { text: text( draft.text ) };
			break;
		case 'faq':
			config = {
				items: draft.faqItems.map( ( item ) => ( {
					question: text( item.question ),
					answer: text( item.answer ),
				} ) ),
			};
			break;
		case 'woocommerce_product':
			config =
				draft.woocommerceMode === 'catalog'
					? { catalog: true }
					: {
							product_ids: draft.productIds
								.split( ',' )
								.map( ( id ) =>
									Number.parseInt( id.trim(), 10 )
								)
								.filter(
									( id ) =>
										Number.isSafeInteger( id ) && id > 0
								),
					  };
			break;
		case 'wordpress_posts':
		default:
			config = { include_private: draft.includePrivate };
			if ( draft.postTypes.length > 0 ) {
				config.post_types = draft.postTypes;
			}
			break;
	}

	const body: KnowledgeSourceRequestJson[ 'body' ] = {
		source_type: draft.sourceType,
		config,
	};
	if ( title !== '' ) {
		body.title = title;
	}
	return { kind: 'json', body };
};

const sourceTypeLabel = ( sourceType: string ): string =>
	SOURCE_TYPES.find( ( item ) => item.type === sourceType )?.label ??
	sourceType;

const draftFromForm = ( form: HTMLFormElement ): KnowledgeSourceDraft => {
	const sourceType = (
		form.elements.namedItem( 'source_type' ) as HTMLInputElement
	 ).value as KnowledgeSourceType;
	const faqItems = Array.from(
		form.querySelectorAll< HTMLElement >( '[data-knowledge-faq-row]' )
	).map( ( row ) => ( {
		question:
			( row.querySelector( '[name="faq_question"]' ) as HTMLInputElement )
				?.value ?? '',
		answer:
			(
				row.querySelector(
					'[name="faq_answer"]'
				) as HTMLTextAreaElement
			 )?.value ?? '',
	} ) );
	const productIds =
		( form.elements.namedItem( 'product_ids' ) as HTMLInputElement | null )
			?.value ?? '';
	const mode =
		(
			form.elements.namedItem(
				'woocommerce_mode'
			) as HTMLSelectElement | null
		 )?.value ?? 'catalog';
	return {
		sourceType,
		title: ( form.elements.namedItem( 'title' ) as HTMLInputElement ).value,
		text: ( form.elements.namedItem( 'text' ) as HTMLTextAreaElement )
			.value,
		faqItems,
		includePrivate:
			(
				form.elements.namedItem(
					'include_private'
				) as HTMLInputElement | null
			 )?.checked ?? false,
		postTypes: [],
		woocommerceMode: mode === 'selected' ? 'selected' : 'catalog',
		productIds,
		file:
			( form.elements.namedItem( 'file' ) as HTMLInputElement | null )
				?.files?.[ 0 ] ?? null,
	};
};

const appendFaqRow = ( container: HTMLElement ): void => {
	const documentRef = container.ownerDocument;
	const row = documentRef.createElement( 'fieldset' );
	row.setAttribute( 'data-knowledge-faq-row', '' );
	const questionLabel = documentRef.createElement( 'label' );
	questionLabel.textContent = 'Question';
	const question = documentRef.createElement( 'input' );
	question.name = 'faq_question';
	question.type = 'text';
	questionLabel.append( question );
	const answerLabel = documentRef.createElement( 'label' );
	answerLabel.textContent = 'Answer';
	const answer = documentRef.createElement( 'textarea' );
	answer.name = 'faq_answer';
	answerLabel.append( answer );
	row.append( questionLabel, answerLabel );
	container.append( row );
};

export const KnowledgeWizard = (
	props: KnowledgeWizardProps = {}
): unknown => {
	const createElement = window.wp.element.createElement;
	const woocommerceAvailable = props.woocommerceAvailable === true;
	let inFlight = props.submitting === true;

	const error = createElement(
		'p',
		{
			'aria-live': 'assertive',
			'aria-atomic': 'true',
			'aria-hidden': 'true',
			'data-knowledge-wizard-error': '',
			id: 'knowledge-wizard-error',
			role: 'alert',
		},
		''
	);
	const status = createElement(
		'p',
		{
			'aria-live': 'polite',
			'aria-atomic': 'true',
			'data-knowledge-submit-status': '',
			role: 'status',
		},
		props.submitting === true
			? 'Saving source…'
			: 'Choose a source type to add knowledge.'
	);
	const faqRows = createElement(
		'div',
		{ 'data-knowledge-faq-rows': '' },
		createElement(
			'fieldset',
			{ 'data-knowledge-faq-row': '' },
			createElement(
				'label',
				{ htmlFor: 'knowledge-faq-question' },
				'Question'
			),
			createElement( 'input', {
				id: 'knowledge-faq-question',
				name: 'faq_question',
				type: 'text',
			} ),
			createElement(
				'label',
				{ htmlFor: 'knowledge-faq-answer' },
				'Answer'
			),
			createElement( 'textarea', {
				id: 'knowledge-faq-answer',
				name: 'faq_answer',
			} )
		)
	);
	const panels = SOURCE_TYPES.map( ( item ) => {
		const unavailable =
			item.type === 'woocommerce_product' && ! woocommerceAvailable;
		const children: unknown[] = [];
		if ( item.type === 'manual_text' ) {
			children.push(
				createElement( 'label', { htmlFor: 'knowledge-text' }, 'Text' ),
				createElement( 'textarea', {
					id: 'knowledge-text',
					name: 'text',
					'aria-describedby': 'knowledge-wizard-error',
				} )
			);
		}
		if ( item.type === 'faq' ) {
			children.push(
				faqRows,
				createElement(
					'button',
					{
						'data-knowledge-add-faq': '',
						type: 'button',
						onClick: ( event: Event ) => {
							const container = (
								event.currentTarget as HTMLElement
							 ).closest( '[data-knowledge-faq-rows]' );
							if ( container !== null ) {
								appendFaqRow( container as HTMLElement );
							}
						},
					},
					'Add another question'
				)
			);
		}
		if ( item.type === 'wordpress_posts' ) {
			children.push(
				createElement(
					'p',
					null,
					'Public posts and pages are used by default.'
				)
			);
		}
		if ( item.type === 'woocommerce_product' ) {
			children.push(
				createElement(
					'p',
					{ role: unavailable ? 'alert' : undefined },
					unavailable
						? 'WooCommerce is not available on this site.'
						: 'Choose the full public catalog or selected product IDs.'
				),
				createElement(
					'label',
					{ htmlFor: 'knowledge-woocommerce-mode' },
					'Catalog selection'
				),
				createElement(
					'select',
					{
						id: 'knowledge-woocommerce-mode',
						name: 'woocommerce_mode',
					},
					createElement(
						'option',
						{ value: 'catalog' },
						'All public products'
					),
					createElement(
						'option',
						{ value: 'selected' },
						'Selected product IDs'
					)
				),
				createElement(
					'label',
					{ htmlFor: 'knowledge-product-ids' },
					'Product IDs'
				),
				createElement( 'input', {
					id: 'knowledge-product-ids',
					name: 'product_ids',
					placeholder: '12, 19',
					type: 'text',
				} )
			);
		}
		if ( item.type === 'file' ) {
			children.push(
				createElement( 'label', { htmlFor: 'knowledge-file' }, 'File' ),
				createElement( 'input', {
					accept: '.txt,.md,.markdown,.html,.htm,.csv,.json,.xml,.pdf,.docx',
					id: 'knowledge-file',
					name: 'file',
					type: 'file',
				} )
			);
		}
		return createElement(
			'div',
			{
				'data-knowledge-source-panel': item.type,
				hidden: item.type !== 'wordpress_posts',
			},
			...children
		);
	} );

	const form = createElement(
		'form',
		{
			'aria-describedby': 'knowledge-wizard-error',
			'data-knowledge-wizard-form': '',
			onSubmit: ( event: Event ) => {
				event.preventDefault();
				if ( inFlight || props.onCreate === undefined ) {
					return;
				}
				const formElement = event.currentTarget as HTMLFormElement;
				const draft = draftFromForm( formElement );
				const errors = validateKnowledgeDraft(
					draft,
					woocommerceAvailable
				);
				if ( Object.keys( errors ).length > 0 ) {
					const firstError =
						Object.values( errors )[ 0 ] ?? 'Check the form.';
					let fieldSelector = '[name="source_type"]';
					if ( errors.title !== undefined ) {
						fieldSelector = '[name="title"]';
					} else if ( errors.text !== undefined ) {
						fieldSelector = '[name="text"]';
					} else if ( errors.productIds !== undefined ) {
						fieldSelector = '[name="product_ids"]';
					} else if ( errors.file !== undefined ) {
						fieldSelector = '[name="file"]';
					}
					const invalidField =
						formElement.querySelector< HTMLElement >(
							fieldSelector
						);
					invalidField?.setAttribute( 'aria-invalid', 'true' );
					( error as HTMLElement ).textContent = firstError;
					( error as HTMLElement ).removeAttribute( 'aria-hidden' );
					invalidField?.focus();
					return;
				}
				( error as HTMLElement ).textContent = '';
				( error as HTMLElement ).setAttribute( 'aria-hidden', 'true' );
				formElement
					.querySelectorAll< HTMLElement >( '[aria-invalid="true"]' )
					.forEach( ( field ) =>
						field.removeAttribute( 'aria-invalid' )
					);
				inFlight = true;
				const submit = formElement.querySelector< HTMLButtonElement >(
					'[data-knowledge-submit]'
				);
				if ( submit !== null ) {
					submit.disabled = true;
				}
				formElement.setAttribute( 'aria-busy', 'true' );
				( status as HTMLElement ).textContent = 'Saving source…';
				void props
					.onCreate( draft )
					.catch( () => {
						( error as HTMLElement ).textContent =
							'The source could not be saved. Try again.';
						( error as HTMLElement ).removeAttribute(
							'aria-hidden'
						);
					} )
					.finally( () => {
						inFlight = false;
						formElement.removeAttribute( 'aria-busy' );
						if ( submit !== null ) {
							submit.disabled = false;
						}
						( status as HTMLElement ).textContent =
							'Source request finished. Check the server indexing status below.';
					} );
			},
		},
		status,
		error,
		createElement( 'label', { htmlFor: 'knowledge-title' }, 'Title' ),
		createElement( 'input', {
			id: 'knowledge-title',
			name: 'title',
			type: 'text',
		} ),
		createElement(
			'div',
			{ 'data-knowledge-source-types': '' },
			...SOURCE_TYPES.map( ( item ) =>
				createElement(
					'label',
					{
						'data-knowledge-source-type': item.type,
						'aria-disabled':
							item.type === 'woocommerce_product' &&
							! woocommerceAvailable
								? 'true'
								: undefined,
					},
					createElement( 'input', {
						checked: item.type === 'wordpress_posts',
						disabled:
							item.type === 'woocommerce_product' &&
							! woocommerceAvailable,
						name: 'source_type',
						onChange: ( event: Event ) => {
							const input =
								event.currentTarget as HTMLInputElement;
							const root = input.form?.closest(
								'[data-knowledge-wizard]'
							);
							root?.setAttribute(
								'data-selected-source-type',
								item.type
							);
							root
								?.querySelectorAll< HTMLElement >(
									'[data-knowledge-source-panel]'
								)
								.forEach( ( panel ) => {
									panel.hidden =
										panel.getAttribute(
											'data-knowledge-source-panel'
										) !== item.type;
								} );
						},
						type: 'radio',
						value: item.type,
					} ),
					createElement( 'span', null, item.label ),
					createElement( 'small', null, item.description )
				)
			)
		),
		...panels,
		createElement(
			'button',
			{
				'data-knowledge-submit': '',
				disabled: props.submitting === true,
				type: 'submit',
			},
			'Add knowledge'
		)
	);

	const sources = ( props.sources ?? [] ).map( ( source ) =>
		createElement(
			'article',
			{
				'data-knowledge-source-card': String( source.id ),
				key: source.id,
			},
			createElement( 'h3', null, source.title ),
			createElement(
				'p',
				null,
				`Type: ${ sourceTypeLabel( source.source_type ) }`
			),
			createElement( 'p', null, `Status: ${ source.status }` ),
			createElement(
				'a',
				{ href: `#/knowledge/${ encodeURIComponent( source.id ) }` },
				'Inspect documents'
			)
		)
	);
	const jobs = ( props.jobs ?? [] ).map( ( job ) =>
		createElement(
			'li',
			{ 'data-knowledge-wizard-job': job.job_key, key: job.job_key },
			createElement( 'strong', null, job.status ),
			createElement(
				'span',
				null,
				` ${ job.progress_current } of ${ job.progress_total }`
			),
			job.last_error_code === null
				? undefined
				: createElement( 'span', null, ` ${ job.last_error_code }` ),
			job.last_error_message === null
				? undefined
				: createElement( 'p', null, job.last_error_message ),
			job.status === 'queued' || job.status === 'running'
				? createElement(
						'button',
						{
							'data-knowledge-job-action': 'cancel',
							type: 'button',
							onClick: () => void props.onCancelJob?.( job ),
						},
						'Cancel'
				  )
				: undefined,
			job.status === 'failed'
				? createElement(
						'button',
						{
							'data-knowledge-job-action': 'retry',
							type: 'button',
							onClick: () => void props.onRetryJob?.( job ),
						},
						'Retry'
				  )
				: undefined
		)
	);

	return createElement(
		'section',
		{
			className: 'wp-rag-ai-chatbot-knowledge-wizard',
			'data-knowledge-wizard': '',
			'data-selected-source-type': 'wordpress_posts',
		},
		createElement( 'h2', null, 'Add knowledge' ),
		createElement(
			'p',
			null,
			'Choose a source and the server will index it for your chatbot.'
		),
		form,
		createElement(
			'section',
			{ 'data-knowledge-source-cards': '' },
			createElement( 'h2', null, 'Sources' ),
			sources.length > 0
				? sources
				: createElement( 'p', null, 'No knowledge sources yet.' )
		),
		createElement(
			'section',
			{ 'data-knowledge-wizard-jobs': '' },
			createElement( 'h2', null, 'Indexing status' ),
			jobs.length > 0
				? createElement( 'ul', null, ...jobs )
				: createElement( 'p', null, 'No indexing jobs yet.' )
		)
	);
};
