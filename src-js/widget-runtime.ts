import {
	evaluateDisplayRules,
	normalizeDisplayRules,
	type DisplayRuleFacts,
} from './display-rules';
import {
	createProactiveDelayCoordinator,
	readProactiveDelayConfig,
} from './widget-proactive';
import { resolveWidgetMessage, type WidgetMessageKey } from './widget-messages';

export type WidgetSurface = 'floating' | 'embedded' | 'fullscreen';

export type WidgetBootstrapConfig = {
	botId: string;
	restBase: string;
	surface?: WidgetSurface;
	facts?: DisplayRuleFacts;
	config: {
		bot_id: string;
		name: string;
		appearance: Record< string, unknown >;
		display_rules?: unknown;
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
export const WIDGET_EVENT_HANDLER_KEY = '__wpRagAiChatbotHandleEvent' as const;
const MAX_CITATIONS = 8;
const MAX_RENDERED_MESSAGES = 40;
const TYPING_INTERVAL_MS = 24;
const MAX_TYPING_TICKS = 48;
const widgetEventHandlers = new Map< string, ( event: Event ) => void >();

const COLOR_MODES = [ 'light', 'dark', 'system' ] as const;
const POSITIONS = [ 'bottom-left', 'bottom-right' ] as const;
const LAUNCHER_STYLES = [ 'bubble', 'icon', 'text' ] as const;
const PANEL_SIZES = [ 'small', 'medium', 'large' ] as const;
const FONT_FAMILIES = [ 'system', 'sans', 'serif', 'mono' ] as const;
const WIDGET_SURFACES = [ 'floating', 'embedded', 'fullscreen' ] as const;

type WidgetEventHandlerMount = HTMLElement & {
	[ WIDGET_EVENT_HANDLER_KEY ]?: ( event: Event ) => void;
};
type WidgetEventHandlerTarget = WidgetEventHandlerMount & {
	getAttribute?: ( name: string ) => string | null;
};
type WidgetEventHandlerWindow = Window & {
	wpRagAiChatbotWidgetConfigs?: WidgetBootstrapConfig[];
};

const eventPath = ( event: Event ): unknown[] => [
	event.target,
	...( typeof event.composedPath === 'function' ? event.composedPath() : [] ),
];

const closestEventTarget = (
	event: Event,
	selector: string
): unknown | null => {
	const attributeSelector = /^\[([^=\]]+)\]$/.exec( selector )?.[ 1 ];

	for ( const value of eventPath( event ) ) {
		const target = value as {
			matches?: ( value: string ) => boolean;
			closest?: ( value: string ) => unknown;
			getAttribute?: ( value: string ) => string | null;
		};

		if (
			typeof target.matches === 'function' &&
			target.matches( selector )
		) {
			return value;
		}

		if (
			attributeSelector !== undefined &&
			typeof target.getAttribute === 'function' &&
			target.getAttribute( attributeSelector ) !== null
		) {
			return value;
		}

		if ( typeof target.closest === 'function' ) {
			const match = target.closest( selector );
			if ( match !== null ) {
				return match;
			}
		}
	}

	return null;
};

const eventTargetMatches = ( event: Event, selector: string ): boolean => {
	return closestEventTarget( event, selector ) !== null;
};

const findCurrentMount = (
	botId: string | null | undefined
): WidgetEventHandlerMount | null => {
	if ( botId === null || botId === undefined ) {
		return null;
	}

	return (
		Array.from(
			document.querySelectorAll< WidgetEventHandlerMount >(
				MOUNT_SELECTOR
			)
		).find(
			( candidate ) =>
				candidate.getAttribute( 'data-wp-rag-ai-chatbot-bot' ) === botId
		) ?? null
	);
};

export const handleWidgetEvent = ( event: Event ): void => {
	const mount = closestEventTarget(
		event,
		MOUNT_SELECTOR
	) as WidgetEventHandlerTarget | null;
	const botId =
		mount && typeof mount.getAttribute === 'function'
			? mount.getAttribute( 'data-wp-rag-ai-chatbot-bot' )
			: null;
	let currentMount = findCurrentMount( botId );
	if (
		currentMount &&
		typeof currentMount[ WIDGET_EVENT_HANDLER_KEY ] !== 'function'
	) {
		const configs = ( window as WidgetEventHandlerWindow )
			.wpRagAiChatbotWidgetConfigs;
		if ( Array.isArray( configs ) ) {
			mountWidgets( document, configs );
			currentMount = findCurrentMount( botId );
		}
	}
	const handler =
		currentMount?.[ WIDGET_EVENT_HANDLER_KEY ] ??
		mount?.[ WIDGET_EVENT_HANDLER_KEY ] ??
		( botId === null || botId === undefined
			? undefined
			: widgetEventHandlers.get( botId ) );
	handler?.( event );
};

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

const readSurface = ( value: unknown ): WidgetSurface =>
	typeof value === 'string' &&
	WIDGET_SURFACES.includes( value as WidgetSurface )
		? ( value as WidgetSurface )
		: 'floating';

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
	surface: WidgetSurface | null,
	configs: readonly WidgetBootstrapConfig[]
): WidgetBootstrapConfig | undefined =>
	configs.find(
		( config ) =>
			config.botId === botId &&
			config.config.bot_id === botId &&
			( surface === null || readSurface( config.surface ) === surface )
	);

const chatUrl = ( restBase: string ): string =>
	`${ restBase.replace( /\/+$/, '' ) }/chat`;

const stripCitationMarkers = ( answer: string ): string =>
	answer.replace( /\s*\[C\d+\]/gi, '' );

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
		answer: stripCitationMarkers( candidate.answer ),
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

const publicErrorMessageKey = ( code: string | null ): WidgetMessageKey => {
	if ( code === 'rate_limited' ) {
		return 'rate_limited';
	}

	if ( code === 'chat_unavailable' ) {
		return 'chat_unavailable';
	}

	return 'send_failed';
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
			const eventMount = mount as WidgetEventHandlerMount;
			if (
				mount.dataset[ MOUNTED_DATA_KEY ] === 'true' &&
				typeof eventMount[ WIDGET_EVENT_HANDLER_KEY ] === 'function'
			) {
				return;
			}
			if ( mount.dataset[ MOUNTED_DATA_KEY ] === 'true' ) {
				mount
					.querySelectorAll(
						'[data-wp-rag-ai-chatbot-launcher], [data-wp-rag-ai-chatbot-panel]'
					)
					.forEach( ( element ) => element.remove() );
				delete mount.dataset[ MOUNTED_DATA_KEY ];
			}

			const botId = mount.dataset.wpRagAiChatbotBot ?? '';
			const requestedSurface = mount.dataset.wpRagAiChatbotSurface;
			const config = findConfig(
				botId,
				requestedSurface === undefined
					? null
					: readSurface( requestedSurface ),
				configs
			);

			if ( ! config ) {
				return;
			}

			const displayDecision = evaluateDisplayRules(
				normalizeDisplayRules( config.config.display_rules ),
				config.facts
			);
			if ( ! displayDecision.visible ) {
				return;
			}

			mount.lang = displayDecision.locale;
			mount.dir = displayDecision.direction;

			const widgetMessage = (
				key: WidgetMessageKey,
				params: { botName?: string } = {}
			): string =>
				resolveWidgetMessage( displayDecision.locale, key, params );
			const botName = { botName: config.config.name };
			const surface = readSurface( requestedSurface ?? config.surface );
			const isFloating = surface === 'floating';
			mount.dataset.wpRagAiChatbotSurface = surface;
			applyAppearance( mount, config.config.appearance );

			const launcher = documentRoot.createElement( 'button' );
			launcher.type = 'button';
			launcher.textContent = widgetMessage( 'chat' );
			launcher.dataset.wpRagAiChatbotLauncher = '';
			launcher.setAttribute(
				'aria-label',
				widgetMessage( 'open_chat', botName )
			);
			launcher.setAttribute( 'aria-expanded', 'false' );

			const panel = documentRoot.createElement( 'section' );
			panel.dataset.wpRagAiChatbotPanel = '';
			panel.hidden = isFloating;
			panel.setAttribute( 'role', 'dialog' );
			panel.setAttribute(
				'aria-label',
				widgetMessage( 'chat_label', botName )
			);

			const header = documentRoot.createElement( 'header' );
			header.dataset.wpRagAiChatbotHeader = '';

			const avatar = documentRoot.createElement( 'span' );
			avatar.dataset.wpRagAiChatbotAvatar = '';
			avatar.setAttribute( 'aria-hidden', 'true' );
			avatar.textContent = '✦';

			const identity = documentRoot.createElement( 'div' );
			identity.dataset.wpRagAiChatbotIdentity = '';

			const eyebrow = documentRoot.createElement( 'span' );
			eyebrow.dataset.wpRagAiChatbotEyebrow = '';
			eyebrow.textContent = widgetMessage( 'assistant_label' );

			const title = documentRoot.createElement( 'h2' );
			title.dataset.wpRagAiChatbotTitle = '';
			title.textContent = config.config.name;

			const subtitle = documentRoot.createElement( 'p' );
			subtitle.dataset.wpRagAiChatbotSubtitle = '';
			subtitle.textContent = widgetMessage( 'welcome_body' );
			identity.append( eyebrow, title, subtitle );

			const online = documentRoot.createElement( 'span' );
			online.dataset.wpRagAiChatbotOnline = '';
			online.textContent = widgetMessage( 'online' );
			header.append( avatar, identity, online );

			const close = documentRoot.createElement( 'button' );
			close.type = 'button';
			close.textContent = widgetMessage( 'close' );
			close.dataset.wpRagAiChatbotClose = '';
			close.setAttribute(
				'aria-label',
				widgetMessage( 'close_chat', botName )
			);

			const messages = documentRoot.createElement( 'div' );
			messages.dataset.wpRagAiChatbotMessages = '';
			messages.setAttribute( 'aria-live', 'polite' );

			const emptyState = documentRoot.createElement( 'div' );
			emptyState.dataset.wpRagAiChatbotEmptyState = '';
			const emptyIcon = documentRoot.createElement( 'span' );
			emptyIcon.dataset.wpRagAiChatbotEmptyIcon = '';
			emptyIcon.setAttribute( 'aria-hidden', 'true' );
			emptyIcon.textContent = '✦';
			const emptyTitle = documentRoot.createElement( 'h3' );
			emptyTitle.textContent = widgetMessage( 'welcome_title' );
			const emptyBody = documentRoot.createElement( 'p' );
			emptyBody.textContent = widgetMessage( 'welcome_body' );
			emptyState.append( emptyIcon, emptyTitle, emptyBody );
			messages.append( emptyState );

			const form = documentRoot.createElement( 'form' );
			form.dataset.wpRagAiChatbotForm = '';

			const question = documentRoot.createElement( 'textarea' );
			question.dataset.wpRagAiChatbotQuestion = '';
			question.setAttribute( 'aria-label', widgetMessage( 'message' ) );

			const send = documentRoot.createElement( 'button' );
			send.type = 'submit';
			const sendLabel = widgetMessage( 'send' );
			const sendMessageLabel = widgetMessage( 'send_message' );
			send.textContent = sendLabel;
			send.dataset.wpRagAiChatbotSend = '';
			send.setAttribute( 'aria-label', sendMessageLabel );

			const retry = documentRoot.createElement( 'button' );
			retry.type = 'button';
			retry.textContent = widgetMessage( 'retry' );
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
			let cancelPresentation: ( () => void ) | null = null;

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
				send.textContent = sendLabel;
				send.setAttribute( 'aria-label', sendMessageLabel );
			};

			const cancelActivePresentation = (): void => {
				const cancel = cancelPresentation;
				cancelPresentation = null;
				cancel?.();
			};

			const appendAssistantMessage = (
				text: string,
				citations: readonly PublicCitation[]
			): void => {
				cancelActivePresentation();

				const wrapper = documentRoot.createElement( 'article' );
				wrapper.dataset.wpRagAiChatbotMessageContainer = 'assistant';

				const message = documentRoot.createElement( 'p' );
				message.dataset.wpRagAiChatbotMessage = 'assistant';
				message.setAttribute( 'aria-live', 'off' );
				wrapper.append( message );
				messages.append( wrapper );
				trimMessageHistory();

				const appendCompletionControls = (): void => {
					if ( citations.length > 0 ) {
						const details = documentRoot.createElement( 'details' );
						details.dataset.wpRagAiChatbotSources = '';
						const summary = documentRoot.createElement( 'summary' );
						summary.textContent = widgetMessage( 'sources' );
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

				const reducedMotion =
					documentRoot.defaultView?.matchMedia?.(
						'(prefers-reduced-motion: reduce)'
					).matches ?? false;
				if ( reducedMotion ) {
					message.textContent = text;
					message.setAttribute( 'aria-live', 'polite' );
					appendCompletionControls();
					finishRequest();
					status.textContent = '';
					return;
				}

				const chunkSize = Math.max(
					1,
					Math.ceil( text.length / MAX_TYPING_TICKS )
				);
				let revealedLength = 0;
				let cancelled = false;
				let timer: ReturnType< typeof setTimeout > | null = null;

				cancelPresentation = () => {
					cancelled = true;
					if ( timer !== null ) {
						clearTimeout( timer );
						timer = null;
					}
					finishRequest();
					status.textContent = '';
				};

				const revealNextChunk = (): void => {
					if ( cancelled ) {
						return;
					}

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
						timer = setTimeout(
							revealNextChunk,
							TYPING_INTERVAL_MS
						);
						return;
					}

					timer = null;
					cancelPresentation = null;
					appendCompletionControls();
					finishRequest();
					status.textContent = '';
				};

				status.textContent = widgetMessage( 'assistant_typing' );
				revealNextChunk();
			};

			const closePanel = (): void => {
				cancelActivePresentation();
				launcher.setAttribute( 'aria-expanded', 'false' );
				panel.hidden = true;
				launcher.focus();
			};

			const openPanel = ( moveFocus: boolean ): void => {
				launcher.setAttribute( 'aria-expanded', 'true' );
				panel.hidden = false;
				if ( moveFocus ) {
					close.focus();
				}
			};

			const proactiveDelay = createProactiveDelayCoordinator(
				readProactiveDelayConfig( config.config.display_rules ),
				() => openPanel( false )
			);

			const showError = ( code: string | null, value: string ): void => {
				finishRequest();
				status.dataset.wpRagAiChatbotStatusState = 'error';
				if ( question.value.trim() === '' ) {
					question.value = value;
				}
				retryQuestion = value;
				status.textContent = widgetMessage(
					publicErrorMessageKey( code )
				);
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
				send.textContent = widgetMessage( 'sending' );
				send.setAttribute( 'aria-label', widgetMessage( 'sending' ) );
				retry.hidden = true;
				delete status.dataset.wpRagAiChatbotStatusState;
				status.textContent = widgetMessage( 'sending' );

				if ( appendUser ) {
					question.value = '';
					emptyState.remove();
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
							if (
								! appendUser &&
								question.value.trim() === value
							) {
								question.value = '';
							}
							retryQuestion = null;
							appendAssistantMessage(
								success.answer,
								success.citations
							);
						},
						() => showError( null, value )
					);
			};

			const handleWidgetClick = ( event: Event ): void => {
				if ( ! isFloating ) {
					return;
				}

				const launcherMatch = eventTargetMatches(
					event,
					'[data-wp-rag-ai-chatbot-launcher]'
				);

				if ( launcherMatch ) {
					event.preventDefault();
					event.stopImmediatePropagation();
					proactiveDelay.cancel();
					openPanel( true );
				} else if (
					eventTargetMatches(
						event,
						'[data-wp-rag-ai-chatbot-close]'
					)
				) {
					event.preventDefault();
					event.stopImmediatePropagation();
					closePanel();
				}
			};

			const handleWidgetSubmit = ( event: Event ): void => {
				if (
					! eventTargetMatches(
						event,
						'[data-wp-rag-ai-chatbot-form]'
					)
				) {
					return;
				}

				event.preventDefault();
				event.stopImmediatePropagation();
				const value = question.value.trim();

				if ( value === '' ) {
					return;
				}

				sendQuestion( value, true );
			};

			const handleWidgetPointerDown = ( event: Event ): void => {
				if (
					! isFloating ||
					! eventTargetMatches(
						event,
						'[data-wp-rag-ai-chatbot-launcher]'
					)
				) {
					return;
				}

				event.preventDefault();
				event.stopImmediatePropagation();
				proactiveDelay.cancel();
				openPanel( true );
			};

			const handleWidgetEventForMount = ( event: Event ): void => {
				if ( event.type === 'click' ) {
					handleWidgetClick( event );
				} else if ( event.type === 'pointerdown' ) {
					handleWidgetPointerDown( event );
				} else if ( event.type === 'submit' ) {
					handleWidgetSubmit( event );
				}
			};
			( mount as WidgetEventHandlerMount )[ WIDGET_EVENT_HANDLER_KEY ] =
				handleWidgetEventForMount;
			widgetEventHandlers.set( botId, handleWidgetEventForMount );

			mount.addEventListener( 'click', handleWidgetClick, true );
			mount.addEventListener(
				'pointerdown',
				handleWidgetPointerDown,
				true
			);
			mount.addEventListener( 'submit', handleWidgetSubmit, true );

			if ( isFloating ) {
				panel.addEventListener( 'keydown', ( event ) => {
					if ( event.key === 'Escape' && ! panel.hidden ) {
						closePanel();
					}
				} );
			}
			retry.addEventListener( 'click', () => {
				if ( retryQuestion !== null ) {
					sendQuestion( retryQuestion, false );
				}
			} );

			if ( isFloating ) {
				header.append( close );
				panel.append( header, messages, form );
				mount.append( launcher, panel );
				proactiveDelay.start();
			} else {
				panel.append( header, messages, form );
				mount.append( panel );
			}
			mount.dataset[ MOUNTED_DATA_KEY ] = 'true';
			mounted += 1;
		} );

	return mounted;
};
