import { mountWidgets, type WidgetBootstrapConfig } from './widget-runtime';
import { mountStarterSuggestions } from './widget-starters';

type WidgetConfigWindow = Window & {
	wpRagAiChatbotWidgetConfigs?: WidgetBootstrapConfig[];
};

const widgetWindow = window as WidgetConfigWindow;

if ( ! Array.isArray( widgetWindow.wpRagAiChatbotWidgetConfigs ) ) {
	widgetWindow.wpRagAiChatbotWidgetConfigs = [];
}

mountWidgets( document, widgetWindow.wpRagAiChatbotWidgetConfigs );
mountStarterSuggestions( document, widgetWindow.wpRagAiChatbotWidgetConfigs );
