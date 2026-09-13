import {
	evaluateDisplayRules,
	normalizeDisplayRules,
} from './display-rules';
import { mountWidgets, type WidgetBootstrapConfig } from './widget-runtime';

type WidgetConfigWindow = Window & {
	wpRagAiChatbotWidgetConfigs?: WidgetBootstrapConfig[];
};

const widgetWindow = window as WidgetConfigWindow;

if ( ! Array.isArray( widgetWindow.wpRagAiChatbotWidgetConfigs ) ) {
	widgetWindow.wpRagAiChatbotWidgetConfigs = [];
}

const eligibleConfigs = widgetWindow.wpRagAiChatbotWidgetConfigs.filter(
	( config ) => {
		const publicConfig = config.config as unknown as Record< string, unknown >;

		return evaluateDisplayRules(
			normalizeDisplayRules( publicConfig.display_rules )
		).visible;
	}
);

mountWidgets( document, eligibleConfigs );
