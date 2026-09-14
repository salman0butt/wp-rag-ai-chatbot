import { evaluateDisplayRules, normalizeDisplayRules } from './display-rules';
import type { WidgetBootstrapConfig } from './widget-runtime';

const MOUNT_SELECTOR = '.wp-rag-ai-chatbot-widget[data-wp-rag-ai-chatbot-bot]';
const MAX_STARTERS = 4;

const findConfig = (
	botId: string,
	configs: readonly WidgetBootstrapConfig[]
): WidgetBootstrapConfig | undefined =>
	configs.find(
		( config ) => config.botId === botId && config.config.bot_id === botId
	);

export const mountStarterSuggestions = (
	documentRoot: Document,
	configs: readonly WidgetBootstrapConfig[]
): number => {
	let mounted = 0;

	documentRoot
		.querySelectorAll< HTMLElement >( MOUNT_SELECTOR )
		.forEach( ( mount ) => {
			if ( mount.dataset.wpRagAiChatbotMounted !== 'true' ) {
				return;
			}

			const botId = mount.dataset.wpRagAiChatbotBot ?? '';
			const config = findConfig( botId, configs );
			const form = mount.querySelector< HTMLFormElement >(
				'[data-wp-rag-ai-chatbot-form]'
			);
			const question = mount.querySelector< HTMLTextAreaElement >(
				'[data-wp-rag-ai-chatbot-question]'
			);

			if ( config === undefined || form === null || question === null ) {
				return;
			}

			const starters = evaluateDisplayRules(
				normalizeDisplayRules( config.config.display_rules ),
				config.facts
			).starters.slice( 0, MAX_STARTERS );

			if ( starters.length === 0 ) {
				return;
			}

			const container = documentRoot.createElement( 'div' );
			container.dataset.wpRagAiChatbotStarters = '';
			container.setAttribute( 'role', 'group' );
			container.setAttribute( 'aria-label', 'Suggested questions' );

			for ( const prompt of starters ) {
				const starter = documentRoot.createElement( 'button' );
				starter.type = 'button';
				starter.dataset.wpRagAiChatbotStarter = '';
				starter.textContent = prompt;
				starter.addEventListener( 'click', () => {
					question.value = prompt;
					form.requestSubmit();
				} );
				container.append( starter );
			}

			form.before( container );
			mounted += 1;
		} );

	return mounted;
};
