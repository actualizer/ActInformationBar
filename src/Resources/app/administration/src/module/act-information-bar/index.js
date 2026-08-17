import './component/act-information-bar-info';
import './page/act-information-bar-defaults';
import './page/act-information-bar-detail';
import './page/act-information-bar-list';

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
            component: 'act-information-bar-list',
            path: 'index',
            meta: { privilege: 'act_information_bar.viewer' }
        },
        detail: {
            component: 'act-information-bar-detail',
            path: 'detail/:id',
            meta: { privilege: 'act_information_bar.viewer', parentPath: 'act.information.bar.index' }
        },
        create: {
            component: 'act-information-bar-detail',
            path: 'create',
            meta: { privilege: 'act_information_bar.creator', parentPath: 'act.information.bar.index' }
        },
        defaults: {
            component: 'act-information-bar-defaults',
            path: 'defaults',
            meta: { privilege: 'act_information_bar.editor', parentPath: 'act.information.bar.index' }
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
