type WidgetConfigWindow = Window & {
	wpRagAiChatbotWidgetConfigs?: unknown[];
};

const widgetWindow = window as WidgetConfigWindow;

if ( ! Array.isArray( widgetWindow.wpRagAiChatbotWidgetConfigs ) ) {
	widgetWindow.wpRagAiChatbotWidgetConfigs = [];
}
