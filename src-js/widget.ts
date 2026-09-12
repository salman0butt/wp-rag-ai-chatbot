import {
	mountWidgets,
	type WidgetBootstrapConfig,
} from './widget-runtime';

type WidgetConfigWindow = Window & {
	wpRagAiChatbotWidgetConfigs?: WidgetBootstrapConfig[];
};

const widgetWindow = window as WidgetConfigWindow;

if ( ! Array.isArray( widgetWindow.wpRagAiChatbotWidgetConfigs ) ) {
	widgetWindow.wpRagAiChatbotWidgetConfigs = [];
}

mountWidgets( document, widgetWindow.wpRagAiChatbotWidgetConfigs );
