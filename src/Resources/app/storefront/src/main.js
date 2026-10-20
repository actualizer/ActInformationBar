// Import all necessary Storefront plugins and utils
import ActInformationBar from './act-information-bar/act-information-bar';
import ActInformationBarCountdown from './act-information-bar-countdown/act-information-bar-countdown';

// Register them via the existing PluginManager
const PluginManager = window.PluginManager;
PluginManager.register('ActInformationBar', ActInformationBar, '[data-act-information-bar]');
PluginManager.register('ActInformationBarCountdown', ActInformationBarCountdown, '[data-act-information-bar-countdown]');
