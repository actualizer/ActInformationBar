import './component/act-information-bar-info';
import './component/act-information-bar-section-heading';
import './page/act-information-bar-detail';

import './act-information-bar-config.scss';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('act-information-bar', {
    type: 'plugin',
    name: 'act-information-bar',
    title: 'actInformationBar.general.mainMenuItemGeneral',
    description: 'actInformationBar.general.descriptionTextModule',
    color: '#ff3d58',
    icon: 'regular-megaphone',

    // eslint-disable-next-line shopware-admin/no-snippet-import
    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            component: 'act-information-bar-detail',
            path: 'index'
        }
    },

    navigation: [{
        label: 'actInformationBar.general.mainMenuItemGeneral',
        color: '#ff3d58',
        path: 'act.information.bar.index',
        icon: 'regular-megaphone',
        parent: 'sw-content',
        position: 100
    }]
});
