// Import all necessary Storefront plugins and utils
import ActInformationBar from './act-information-bar/act-information-bar';

// Register them via the existing PluginManager
const PluginManager = window.PluginManager;
PluginManager.register('ActInformationBar', ActInformationBar, '[data-act-information-bar]');
