export type WidgetBootstrapConfig = {
	botId: string;
	restBase: string;
	config: {
		bot_id: string;
		name: string;
		appearance: Record< string, unknown >;
	};
};

type PublicChatSuccess = {
	answer: string;
	conversation_id: string;
};

const MOUNT_SELECTOR = '.wp-rag-ai-chatbot-widget[data-wp-rag-ai-chatbot-bot]';
const MOUNTED_DATA_KEY = 'wpRagAiChatbotMounted';

const COLOR_MODES = [ 'light', 'dark', 'system' ] as const;
const POSITIONS = [ 'bottom-left', 'bottom-right' ] as const;
const LAUNCHER_STYLES = [ 'bubble', 'icon', 'text' ] as const;
const PANEL_SIZES = [ 'small', 'medium', 'large' ] as const;
const FONT_FAMILIES = [ 'system', 'sans', 'serif', 'mono' ] as const;

const readChoice = < T extends string >(
	appearance: Record< string, unknown >,
	key: string,
	allowed: readonly T[],
	fallback: T
): T => {
	const value = appearance[ key ];

	return typeof value === 'string' && allowed.includes( value as T )
		? ( value as T )
		: fallback;
};

const readPrimaryColor = ( appearance: Record< string, unknown > ): string => {
	const value = appearance.primary_color;

	return typeof value === 'string' && /^#[0-9a-f]{6}$/.test( value )
		? value
		: '#2563eb';
};

const readRadius = ( appearance: Record< string, unknown > ): number => {
	const value = appearance.radius_px;

	return typeof value === 'number' &&
		Number.isInteger( value ) &&
		value >= 0 &&
		value <= 32
		? value
		: 16;
};

const applyAppearance = (
	mount: HTMLElement,
	appearance: Record< string, unknown >
): void => {
	mount.dataset.wpRagAiChatbotPosition = readChoice(
		appearance,
		'position',
		POSITIONS,
		'bottom-right'
	);
	mount.dataset.wpRagAiChatbotColorMode = readChoice(
		appearance,
		'color_mode',
		COLOR_MODES,
		'system'
	);
	mount.dataset.wpRagAiChatbotLauncherStyle = readChoice(
		appearance,
		'launcher_style',
		LAUNCHER_STYLES,
		'bubble'
	);
	mount.dataset.wpRagAiChatbotPanelSize = readChoice(
		appearance,
		'panel_size',
		PANEL_SIZES,
		'medium'
	);
	mount.dataset.wpRagAiChatbotFontFamily = readChoice(
		appearance,
		'font_family',
		FONT_FAMILIES,
		'system'
	);
	mount.style.setProperty(
		'--wp-rag-ai-chatbot-primary-color',
		readPrimaryColor( appearance )
	);
	mount.style.setProperty(
		'--wp-rag-ai-chatbot-radius',
		`${ readRadius( appearance ) }px`
	);
};

const findConfig = (
	botId: string,
	configs: readonly WidgetBootstrapConfig[]
): WidgetBootstrapConfig | undefined =>
	configs.find(
		( config ) => config.botId === botId && config.config.bot_id === botId
	);

const chatUrl = ( restBase: string ): string =>
	`${ restBase.replace( /\/+$/, '' ) }/chat`;

const readSuccess = ( value: unknown ): PublicChatSuccess | null => {
	if ( typeof value !== 'object' || value === null ) {
		return null;
	}

	const candidate = value as Record< string, unknown >;

	if (
		typeof candidate.answer !== 'string' ||
		typeof candidate.conversation_id !== 'string'
	) {
		return null;
	}

	return {
		answer: candidate.answer,
		conversation_id: candidate.conversation_id,
	};
};

export const mountWidgets = (
	documentRoot: Document,
	configs: readonly WidgetBootstrapConfig[]
): number => {
	let mounted = 0;

	documentRoot
		.querySelectorAll< HTMLElement >( MOUNT_SELECTOR )
		.forEach( ( mount ) => {
			if ( mount.dataset[ MOUNTED_DATA_KEY ] === 'true' ) {
				return;
			}

			const botId = mount.dataset.wpRagAiChatbotBot ?? '';
			const config = findConfig( botId, configs );

			if ( ! config ) {
				return;
			}

			applyAppearance( mount, config.config.appearance );

			const launcher = documentRoot.createElement( 'button' );
			launcher.type = 'button';
			launcher.textContent = 'Chat';
			launcher.dataset.wpRagAiChatbotLauncher = '';
			launcher.setAttribute(
				'aria-label',
				`Open ${ config.config.name } chat`
			);
			launcher.setAttribute( 'aria-expanded', 'false' );

			const panel = documentRoot.createElement( 'section' );
			panel.dataset.wpRagAiChatbotPanel = '';
			panel.hidden = true;
			panel.setAttribute( 'role', 'dialog' );
			panel.setAttribute( 'aria-label', `${ config.config.name } chat` );

			const close = documentRoot.createElement( 'button' );
			close.type = 'button';
			close.textContent = 'Close';
			close.dataset.wpRagAiChatbotClose = '';
			close.setAttribute(
				'aria-label',
				`Close ${ config.config.name } chat`
			);

			const messages = documentRoot.createElement( 'div' );
			messages.dataset.wpRagAiChatbotMessages = '';
			messages.setAttribute( 'aria-live', 'polite' );

			const form = documentRoot.createElement( 'form' );
			form.dataset.wpRagAiChatbotForm = '';

			const question = documentRoot.createElement( 'textarea' );
			question.dataset.wpRagAiChatbotQuestion = '';
			question.setAttribute( 'aria-label', 'Message' );

			const send = documentRoot.createElement( 'button' );
			send.type = 'submit';
			send.textContent = 'Send';
			send.dataset.wpRagAiChatbotSend = '';
			send.setAttribute( 'aria-label', 'Send message' );

			const status = documentRoot.createElement( 'p' );
			status.dataset.wpRagAiChatbotStatus = '';
			status.setAttribute( 'role', 'status' );
			status.setAttribute( 'aria-live', 'polite' );
			form.append( question, send, status );

			let requestInFlight = false;
			let conversationId: string | null = null;

			const appendMessage = (
				role: 'user' | 'assistant',
				text: string
			): void => {
				const message = documentRoot.createElement( 'p' );
				message.dataset.wpRagAiChatbotMessage = role;
				message.textContent = text;
				messages.append( message );
			};

			const closePanel = (): void => {
				launcher.setAttribute( 'aria-expanded', 'false' );
				panel.hidden = true;
				launcher.focus();
			};

			const finishRequest = (): void => {
				requestInFlight = false;
				send.disabled = false;
				status.textContent = '';
			};

			launcher.addEventListener( 'click', () => {
				launcher.setAttribute( 'aria-expanded', 'true' );
				panel.hidden = false;
				close.focus();
			} );

			close.addEventListener( 'click', closePanel );
			panel.addEventListener( 'keydown', ( event ) => {
				if ( event.key === 'Escape' && ! panel.hidden ) {
					closePanel();
				}
			} );
			form.addEventListener( 'submit', ( event ) => {
				event.preventDefault();
				const value = question.value.trim();

				if ( value === '' || requestInFlight ) {
					return;
				}

				requestInFlight = true;
				send.disabled = true;
				status.textContent = 'Sending…';
				appendMessage( 'user', value );

				const requestBody: Record< string, string > = {
					bot_id: config.config.bot_id,
					question: value,
				};

				if ( conversationId !== null ) {
					requestBody.conversation_id = conversationId;
				}

				void fetch( chatUrl( config.restBase ), {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
					},
					body: JSON.stringify( requestBody ),
				} )
					.then( ( response ) => response.json() )
					.then( ( payload: unknown ) => {
						const success = readSuccess( payload );
						if ( success !== null ) {
							conversationId = success.conversation_id;
							appendMessage( 'assistant', success.answer );
						}
					} )
					.then( finishRequest, finishRequest );
			} );

			panel.append( close, messages, form );
			mount.append( launcher, panel );
			mount.dataset[ MOUNTED_DATA_KEY ] = 'true';
			mounted += 1;
		} );

	return mounted;
};
