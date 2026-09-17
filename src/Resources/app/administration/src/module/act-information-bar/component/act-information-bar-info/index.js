import template from './act-information-bar-info.html.twig';
import './act-information-bar-info.scss';

const { Component } = Shopware;

const HOW_TO_ITEM_COUNT = 6;

Component.register('act-information-bar-info', {
    template,

    computed: {
        howToItems() {
            return Array.from(
                { length: HOW_TO_ITEM_COUNT },
                (_, index) => `actInformationBar.settings.info.howTo.item${index + 1}`,
            );
        },
    },
});
