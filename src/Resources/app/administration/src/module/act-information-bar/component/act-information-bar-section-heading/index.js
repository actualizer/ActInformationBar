import template from './act-information-bar-section-heading.html.twig';

const { Component } = Shopware;

/**
 * Section headings for the plugin configuration page.
 *
 * The core system config only accepts input fields and components inside a card, so a
 * heading has to be a component. Passing the text as a <label> in config.xml does not
 * work: the core strips `config.label` from every element without map inheritance
 * support before it reaches the component. Each heading is therefore registered as its
 * own component with a fixed snippet key.
 *
 * The keys are the same ones the maintenance page uses for its group titles, so both
 * places always show identical wording.
 */
const sections = {
    'act-information-bar-section-status': 'actInformationBar.detail.appearanceStatusGroupTitle',
    'act-information-bar-section-colors': 'actInformationBar.detail.appearanceColorsGroupTitle',
    'act-information-bar-section-button': 'actInformationBar.detail.appearanceButtonGroupTitle',
};

Object.entries(sections).forEach(([componentName, snippetKey]) => {
    Component.register(componentName, {
        template,

        computed: {
            headingSnippetKey() {
                return snippetKey;
            },
        },
    });
});
