export type WidgetMessageKey =
	| 'chat'
	| 'open_chat'
	| 'chat_label'
	| 'assistant_label'
	| 'welcome_title'
	| 'welcome_body'
	| 'online'
	| 'close'
	| 'close_chat'
	| 'message'
	| 'send'
	| 'send_message'
	| 'retry'
	| 'copy'
	| 'copy_assistant_message'
	| 'sources'
	| 'assistant_typing'
	| 'sending'
	| 'rate_limited'
	| 'chat_unavailable'
	| 'send_failed';

type WidgetLocale = 'en' | 'ur';
type WidgetMessageParams = { botName?: string };
type WidgetMessageCatalog = Record< WidgetMessageKey, string >;

const ENGLISH_MESSAGES: WidgetMessageCatalog = {
	chat: 'Chat',
	open_chat: 'Open {botName} chat',
	chat_label: '{botName} chat',
	assistant_label: 'AI assistant',
	welcome_title: 'How can I help?',
	welcome_body: 'Ask a question and I’ll search the knowledge base for you.',
	online: 'Online',
	close: 'Close',
	close_chat: 'Close {botName} chat',
	message: 'Message',
	send: 'Send',
	send_message: 'Send message',
	retry: 'Retry',
	copy: 'Copy',
	copy_assistant_message: 'Copy assistant message',
	sources: 'Sources',
	assistant_typing: 'Assistant is typing…',
	sending: 'Sending…',
	rate_limited: 'Too many requests. Please try again shortly.',
	chat_unavailable: 'Chat is temporarily unavailable. Please try again.',
	send_failed: "We couldn't send your message. Please try again.",
};

const URDU_MESSAGES: WidgetMessageCatalog = {
	chat: 'چیٹ',
	open_chat: '{botName} چیٹ کھولیں',
	chat_label: '{botName} چیٹ',
	assistant_label: 'اے آئی معاون',
	welcome_title: 'میں کیسے مدد کر سکتا ہوں؟',
	welcome_body: 'سوال پوچھیں، میں آپ کے لیے معلومات تلاش کروں گا۔',
	online: 'آن لائن',
	close: 'بند کریں',
	close_chat: '{botName} چیٹ بند کریں',
	message: 'پیغام',
	send: 'بھیجیں',
	send_message: 'پیغام بھیجیں',
	retry: 'دوبارہ کوشش کریں',
	copy: 'کاپی کریں',
	copy_assistant_message: 'معاون کا پیغام کاپی کریں',
	sources: 'ذرائع',
	assistant_typing: 'معاون لکھ رہا ہے…',
	sending: 'بھیجا جا رہا ہے…',
	rate_limited: 'بہت زیادہ درخواستیں ہیں۔ تھوڑی دیر بعد دوبارہ کوشش کریں۔',
	chat_unavailable: 'چیٹ عارضی طور پر دستیاب نہیں ہے۔ دوبارہ کوشش کریں۔',
	send_failed: 'آپ کا پیغام نہیں بھیجا جا سکا۔ دوبارہ کوشش کریں۔',
};

const CATALOGS: Record< WidgetLocale, WidgetMessageCatalog > = {
	en: ENGLISH_MESSAGES,
	ur: URDU_MESSAGES,
};

const readLocale = ( locale: string | undefined ): WidgetLocale => {
	if ( typeof locale !== 'string' ) {
		return 'en';
	}

	const primary = locale
		.trim()
		.toLowerCase()
		.replace( '_', '-' )
		.split( '-' )[ 0 ];
	return primary === 'ur' ? 'ur' : 'en';
};

const plainTextParameter = ( value: string | undefined ): string =>
	( value ?? '' ).replace( /[<>]/g, '' ).trim();

export const resolveWidgetMessage = (
	locale: string | undefined,
	key: WidgetMessageKey,
	params: WidgetMessageParams = {}
): string => {
	const template = CATALOGS[ readLocale( locale ) ][ key ];
	const botName = plainTextParameter( params.botName );

	return template.replace( '{botName}', botName );
};
