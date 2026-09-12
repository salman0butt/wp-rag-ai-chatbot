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

const findConfig = (
	botId: string,
	configs: readonly WidgetBootstrapConfig[]
): WidgetBootstrapConfig | undefined =>
	configs.find(
		( config ) => config.botId === botId && config.config.bot_id === botId
	);

export const mountWidgets = (
	documentRoot: Document,
	configs: readonly WidgetBootstrapConfig[]
): number => {
	let mounted = 0;

	documentRoot
		.querySelectorAll< HTMLElement >( MOUNT_SELECTOR )
		.forEach( ( mount ) => {
			const botId = mount.dataset.wpRagAiChatbotBot ?? '';
			const config = findConfig( botId, configs );

			if ( ! config ) {
				return;
			}

			const launcher = documentRoot.createElement( 'button' );
			launcher.type = 'button';
			launcher.dataset.wpRagAiChatbotLauncher = '';
			launcher.setAttribute(
				'aria-label',
				`Open ${ config.config.name } chat`
			);
			launcher.setAttribute( 'aria-expanded', 'false' );

			const panel = documentRoot.createElement( 'section' );
			panel.dataset.wpRagAiChatbotPanel = '';
			panel.hidden = true;

			const close = documentRoot.createElement( 'button' );
			close.type = 'button';
			close.dataset.wpRagAiChatbotClose = '';
			close.setAttribute(
				'aria-label',
				`Close ${ config.config.name } chat`
			);

			launcher.addEventListener( 'click', () => {
				launcher.setAttribute( 'aria-expanded', 'true' );
				panel.hidden = false;
				close.focus();
			} );

			close.addEventListener( 'click', () => {
				launcher.setAttribute( 'aria-expanded', 'false' );
				panel.hidden = true;
				launcher.focus();
			} );

			panel.append( close );
			mount.append( launcher, panel );
			mounted += 1;
		} );

	return mounted;
};
