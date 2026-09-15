import type { ConversationDetail } from './conversation-admin-loader';

type ElementFactory = (
	tagName: string,
	props: Record< string, unknown > | null,
	...children: Array< unknown >
) => unknown;

export interface ConversationDetailScreenProps {
	conversation: ConversationDetail;
	deleteConfirmationOpen: boolean;
	onRequestDelete: () => void;
	onCancelDelete: () => void;
	onConfirmDelete: () => void;
}

export const ConversationDetailScreen = (
	props: ConversationDetailScreenProps
): unknown => {
	const createElement = window.wp.element.createElement as ElementFactory;
	const { conversation } = props;
	const transcript = conversation.messages.map( ( message, index ) =>
		createElement(
			'li',
			{
				key: `${ message.created_at }-${ index }`,
				'data-conversation-message': true,
				'data-message-role': message.role,
			},
			createElement(
				'article',
				null,
				createElement( 'strong', null, message.role ),
				createElement( 'p', null, message.content ),
				createElement(
					'time',
					{ dateTime: message.created_at },
					message.created_at
				)
			)
		)
	);

	let deleteControl: unknown;
	if ( props.deleteConfirmationOpen ) {
		deleteControl = createElement(
			'div',
			{
				role: 'alertdialog',
				'aria-modal': true,
				'aria-labelledby': 'conversation-delete-title',
			},
			createElement(
				'h3',
				{ id: 'conversation-delete-title' },
				'Delete conversation?'
			),
			createElement( 'p', null, 'This action cannot be undone.' ),
			createElement(
				'button',
				{
					type: 'button',
					'data-cancel-delete': true,
					onClick: props.onCancelDelete,
				},
				'Cancel'
			),
			createElement(
				'button',
				{
					type: 'button',
					'data-confirm-delete': true,
					onClick: props.onConfirmDelete,
				},
				'Delete conversation'
			)
		);
	} else {
		deleteControl = createElement(
			'button',
			{
				type: 'button',
				'data-request-delete': true,
				onClick: props.onRequestDelete,
			},
			'Delete conversation'
		);
	}

	return createElement(
		'section',
		{
			'data-conversation-detail': true,
			'data-conversation-id': conversation.conversation_id,
		},
		createElement(
			'h2',
			null,
			`Conversation ${ conversation.conversation_id }`
		),
		createElement(
			'p',
			null,
			`Bot: ${ conversation.bot_id ?? 'Unassigned' }`
		),
		createElement( 'p', null, `Started: ${ conversation.started_at }` ),
		conversation.messages.length === 0
			? createElement(
					'p',
					{ 'data-conversation-transcript-empty': true },
					'No messages in this conversation.'
			  )
			: createElement(
					'ol',
					{ 'data-conversation-transcript': true },
					...transcript
			  ),
		deleteControl
	);
};
