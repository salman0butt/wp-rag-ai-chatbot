export type WidgetBootstrapConfig = {
	botId: string;
	restBase: string;
	config: {
		bot_id: string;
		name: string;
		appearance: Record< string, unknown >;
	};
};

type PublicCitation = {
	id: string;
	title: string;
	url: string | null;
};

type PublicChatSuccess = {
	answer: string;
	conversation_id: string | null;
	citations: PublicCitation[];
};

type PublicChatError = {
	code: string;
};

const MOUNT_SELECTOR = '.wp-rag-ai-chatbot-widget[data-wp-rag-ai-chatbot-bot]';
const MOUNTED_DATA_KEY = 'wpRagAiChatbotMounted';
const MAX_CITATIONS = 8;
const MAX_RENDERED_MESSAGES = 40;
const TYPING_INTERVAL_MS = 24;
const MAX_TYPING_TICKS = 48;

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

const readCitation = ( value: unknown ): PublicCitation | null => {
	if ( typeof value !== 'object' || value === null ) {
		return null;
	}

	const candidate = value as Record< string, unknown >;
	if (
		typeof candidate.id !== 'string' ||
		typeof candidate.title !== 'string'
	) {
		return null;
	}

	return {
		id: candidate.id,
		title: candidate.title,
		url: typeof candidate.url === 'string' ? candidate.url : null,
	};
};

const readSuccess = ( value: unknown ): PublicChatSuccess | null => {
	if ( typeof value !== 'object' || value === null ) {
		return null;
	}

	const candidate = value as Record< string, unknown >;
	const conversationId = candidate.conversation_id;

	if (
		typeof candidate.answer !== 'string' ||
		( typeof conversationId !== 'string' && conversationId !== null )
	) {
		return null;
	}

	const citations = Array.isArray( candidate.citations )
		? candidate.citations
				.slice( 0, MAX_CITATIONS )
				.map( readCitation )
				.filter(
					( citation ): citation is PublicCitation =>
						citation !== null
				)
		: [];

	return {
		answer: candidate.answer,
		conversation_id: conversationId,
		citations,
	};
};

const readError = ( value: unknown ): PublicChatError | null => {
	if ( typeof value !== 'object' || value === null ) {
		return null;
	}

	const error = ( value as Record< string, unknown > ).error;
	if ( typeof error !== 'object' || error === null ) {
		return null;
	}

	const code = ( error as Record< string, unknown > ).code;

	return typeof code === 'string' ? { code } : null;
};

const publicErrorMessage = ( code: string | null ): string => {
	if ( code === 'rate_limited' ) {
		return 'Too many requests. Please try again shortly.';
	}

	if ( code === 'chat_unavailable' ) {
		return 'Chat is temporarily unavailable. Please try again.';
	}

	return "We couldn't send your message. Please try again.";
};

const safeHttpUrl = ( value: string | null ): string | null => {
	if ( value === null ) {
		return null;
	}

	try {
		const url = new URL( value );
		return url.protocol === 'http:' || url.protocol === 'https:'
			? url.href
			: null;
	} catch {
		return null;
	}
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

			const retry = documentRoot.createElement( 'button' );
			retry.type = 'button';
			retry.textContent = 'Retry';
			retry.dataset.wpRagAiChatbotRetry = '';
			retry.hidden = true;

			const status = documentRoot.createElement( 'p' );
			status.dataset.wpRagAiChatbotStatus = '';
			status.setAttribute( 'role', 'status' );
			status.setAttribute( 'aria-live', 'polite' );
			form.append( question, send, retry, status );

			let requestInFlight = false;
			let conversationId: string | null = null;
			let retryQuestion: string | null = null;

			const trimMessageHistory = (): void => {
				while ( messages.childElementCount > MAX_RENDERED_MESSAGES ) {
					messages.firstElementChild?.remove();
				}
			};

			const appendMessage = (
				role: 'user' | 'assistant',
				text: string
			): HTMLElement => {
				const message = documentRoot.createElement( 'p' );
				message.dataset.wpRagAiChatbotMessage = role;
				message.textContent = text;
				messages.append( message );
				trimMessageHistory();
				return message;
			};

			const finishRequest = (): void => {
				requestInFlight = false;
				send.disabled = false;
			};

			const appendAssistantMessage = (
				text: string,
				citations: readonly PublicCitation[]
			): void => {
				const wrapper = documentRoot.createElement( 'article' );
				wrapper.dataset.wpRagAiChatbotMessageContainer = 'assistant';

				const message = documentRoot.createElement( 'p' );
				message.dataset.wpRagAiChatbotMessage = 'assistant';
				message.setAttribute( 'aria-live', 'off' );
				wrapper.append( message );
				messages.append( wrapper );
				trimMessageHistory();

				const appendCompletionControls = (): void => {
					const copy = documentRoot.createElement( 'button' );
					copy.type = 'button';
					copy.textContent = 'Copy';
					copy.dataset.wpRagAiChatbotCopy = '';
					copy.setAttribute( 'aria-label', 'Copy assistant message' );
					copy.addEventListener( 'click', () => {
						const clipboard =
							documentRoot.defaultView?.navigator.clipboard;
						if ( clipboard ) {
							void clipboard
								.writeText( text )
								.catch( () => undefined );
						}
					} );
					wrapper.append( copy );

					if ( citations.length > 0 ) {
						const details = documentRoot.createElement( 'details' );
						details.dataset.wpRagAiChatbotSources = '';
						const summary = documentRoot.createElement( 'summary' );
						summary.textContent = 'Sources';
						const list = documentRoot.createElement( 'ul' );
						details.append( summary, list );

						for ( const citation of citations ) {
							const item = documentRoot.createElement( 'li' );
							const safeUrl = safeHttpUrl( citation.url );
							if ( safeUrl === null ) {
								item.textContent = citation.title;
							} else {
								const link = documentRoot.createElement( 'a' );
								link.href = safeUrl;
								link.target = '_blank';
								link.rel = 'noopener noreferrer';
								link.textContent = citation.title;
								item.append( link );
							}
							list.append( item );
						}

						wrapper.append( details );
					}
				};

				const chunkSize = Math.max(
					1,
					Math.ceil( text.length / MAX_TYPING_TICKS )
				);
				let revealedLength = 0;

				const revealNextChunk = (): void => {
					let nextLength = Math.min(
						text.length,
						revealedLength + chunkSize
					);

					if ( nextLength < text.length ) {
						const previousCodeUnit = text.charCodeAt(
							nextLength - 1
						);
						const nextCodeUnit = text.charCodeAt( nextLength );
						const splitsSurrogatePair =
							previousCodeUnit >= 0xd800 &&
							previousCodeUnit <= 0xdbff &&
							nextCodeUnit >= 0xdc00 &&
							nextCodeUnit <= 0xdfff;

						if ( splitsSurrogatePair ) {
							nextLength += 1;
						}
					}

					if ( nextLength === text.length ) {
						message.setAttribute( 'aria-live', 'polite' );
					}

					revealedLength = nextLength;
					message.textContent = text.slice( 0, revealedLength );

					if ( revealedLength < text.length ) {
						setTimeout( revealNextChunk, TYPING_INTERVAL_MS );
						return;
					}

					appendCompletionControls();
					finishRequest();
					status.textContent = '';
				};

				status.textContent = 'Assistant is typing…';
				revealNextChunk();
			};

			const closePanel = (): void => {
				launcher.setAttribute( 'aria-expanded', 'false' );
				panel.hidden = true;
				launcher.focus();
			};

			const showError = ( code: string | null, value: string ): void => {
				finishRequest();
				retryQuestion = value;
				status.textContent = publicErrorMessage( code );
				retry.hidden = false;
			};

			const sendQuestion = (
				value: string,
				appendUser: boolean
			): void => {
				if ( requestInFlight ) {
					return;
				}

				requestInFlight = true;
				send.disabled = true;
				retry.hidden = true;
				status.textContent = 'Sending…';

				if ( appendUser ) {
					appendMessage( 'user', value );
				}

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
					.then( async ( response ) => ( {
						ok: response.ok,
						payload: ( await response.json() ) as unknown,
					} ) )
					.then(
						( result ) => {
							if ( ! result.ok ) {
								showError(
									readError( result.payload )?.code ?? null,
									value
								);
								return;
							}

							const success = readSuccess( result.payload );
							if ( success === null ) {
								showError( null, value );
								return;
							}

							conversationId = success.conversation_id;
							retryQuestion = null;
							appendAssistantMessage(
								success.answer,
								success.citations
							);
						},
						() => showError( null, value )
					);
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

				if ( value === '' ) {
					return;
				}

				sendQuestion( value, true );
			} );
			retry.addEventListener( 'click', () => {
				if ( retryQuestion !== null ) {
					sendQuestion( retryQuestion, false );
				}
			} );

			panel.append( close, messages, form );
			mount.append( launcher, panel );
			mount.dataset[ MOUNTED_DATA_KEY ] = 'true';
			mounted += 1;
		} );

	return mounted;
};
