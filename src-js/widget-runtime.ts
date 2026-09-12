export type WidgetBootstrapConfig = {
	botId: string;
	restBase: string;
	config: {
		bot_id: string;
		name: string;
		appearance: Record< string, unknown >;
	};
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
			form.append( question, send );

			const closePanel = (): void => {
				launcher.setAttribute( 'aria-expanded', 'false' );
				panel.hidden = true;
				launcher.focus();
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

				void fetch( chatUrl( config.restBase ), {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
					},
					body: JSON.stringify( {
						bot_id: config.config.bot_id,
						question: value,
					} ),
				} );
			} );

			panel.append( close, form );
			mount.append( launcher, panel );
			mount.dataset[ MOUNTED_DATA_KEY ] = 'true';
			mounted += 1;
		} );

	return mounted;
};
