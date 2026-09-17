import {
	handleWidgetEvent,
	mountWidgets,
	type WidgetBootstrapConfig,
} from './widget-runtime';
import { mountStarterSuggestions } from './widget-starters';

type WidgetConfigWindow = Window & {
	wpRagAiChatbotWidgetConfigs?: WidgetBootstrapConfig[];
	wpRagAiChatbotWidgetEventGuardBound?: boolean;
};

const widgetWindow = window as WidgetConfigWindow;

if ( ! Array.isArray( widgetWindow.wpRagAiChatbotWidgetConfigs ) ) {
	widgetWindow.wpRagAiChatbotWidgetConfigs = [];
}

if ( ! widgetWindow.wpRagAiChatbotWidgetEventGuardBound ) {
	widgetWindow.wpRagAiChatbotWidgetEventGuardBound = true;
	window.addEventListener( 'click', handleWidgetEvent, true );
	window.addEventListener( 'pointerdown', handleWidgetEvent, true );
	window.addEventListener( 'submit', handleWidgetEvent, true );
}

const mountAllWidgets = (): void => {
	mountWidgets( document, widgetWindow.wpRagAiChatbotWidgetConfigs ?? [] );
	mountStarterSuggestions(
		document,
		widgetWindow.wpRagAiChatbotWidgetConfigs ?? []
	);
};

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', mountAllWidgets, {
		once: true,
	} );
} else {
	mountAllWidgets();
}
