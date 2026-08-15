import template from './act-information-bar-detail.html.twig';
import './act-information-bar-detail.scss';

const { Criteria } = Shopware.Data;
const { Mixin } = Shopware;

Shopware.Component.register('act-information-bar-detail', {
    template,

    inject: ['repositoryFactory', 'systemConfigApiService'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            isLoading: false,
            isSaving: false,
            salesChannelId: null,
            bar: null,
            config: {},
            // Gates the appearance card. A failed reload keeps the flag set, so the
            // card shows the previous values next to the error notification instead
            // of emptying itself.
            configLoaded: false
        };
    },

    computed: {
        barRepository() {
            return this.repositoryFactory.create('act_information_bar');
        },

        barCriteria() {
            const criteria = new Criteria(1, 1);
            criteria.addFilter(
                this.salesChannelId
                    ? Criteria.equals('salesChannelId', this.salesChannelId)
                    : Criteria.equals('salesChannelId', null)
            );

            return criteria;
        },

        // Options for the button-target select field, mirrors config.xml's
        // buttonTarget input-field options (_self / _blank).
        buttonTargetOptions() {
            return [
                {
                    id: 1,
                    value: '_self',
                    label: this.$tc('actInformationBar.detail.buttonTargetSelfOption')
                },
                {
                    id: 2,
                    value: '_blank',
                    label: this.$tc('actInformationBar.detail.buttonTargetBlankOption')
                }
            ];
        }
    },

    created() {
        this.loadData();
    },

    methods: {
        // Loads the entity (texts) and the system_config values (appearance) in
        // parallel: both depend on the same sales channel and must be ready
        // before the page can be edited or saved. Each source catches its own
        // load failure and shows a distinct notification instead of leaving the
        // user with silently empty fields (Promise.all never rejects here).
        loadData() {
            this.isLoading = true;

            return Promise.all([
                this.barRepository.search(this.barCriteria, Shopware.Context.api)
                    .then((result) => {
                        this.bar = result.first() ?? this.createEmptyBar();
                    })
                    .catch(() => {
                        this.createNotificationError({
                            message: this.$tc('actInformationBar.detail.loadTextsError')
                        });
                    }),
                this.loadConfig()
                    .catch(() => {
                        this.createNotificationError({
                            message: this.$tc('actInformationBar.detail.loadConfigError')
                        });
                    })
            ])
                .finally(() => {
                    this.isLoading = false;
                });
        },

        loadConfig() {
            return this.systemConfigApiService
                .getValues('ActInformationBar.config', this.salesChannelId)
                .then((values) => {
                    this.config = values;
                    this.configLoaded = true;
                });
        },

        createEmptyBar() {
            const bar = this.barRepository.create(Shopware.Context.api);
            bar.salesChannelId = this.salesChannelId;

            return bar;
        },

        onSalesChannelChanged(salesChannelId) {
            this.salesChannelId = salesChannelId;
            this.loadData();
        },

        onLanguageChanged() {
            this.loadData();
        },

        // Routes every field edit through a named handler instead of binding
        // v-model straight into the nested "bar" entity (see plugin_dev_rules.md).
        onFieldInput(field, value) {
            this.bar[field] = value;
        },

        // Same one-way-binding + named-handler pattern as onFieldInput, but writes
        // into the flat system_config value map instead of the nested entity.
        onConfigInput(field, value) {
            this.config[`ActInformationBar.config.${field}`] = value;
        },

        // Texts (entity) and appearance (system_config) are two independent save
        // paths. Each gets its own catch so the user learns which one failed
        // instead of a single generic error swallowing that information.
        onSave() {
            if (!this.bar) {
                return Promise.resolve();
            }

            this.isSaving = true;

            return Promise.all([
                this.barRepository.save(this.bar, Shopware.Context.api)
                    .catch((error) => {
                        this.createNotificationError({
                            message: this.$tc('actInformationBar.detail.saveTextsError')
                        });
                        throw error;
                    }),
                this.systemConfigApiService.saveValues(this.config, this.salesChannelId)
                    .catch((error) => {
                        this.createNotificationError({
                            message: this.$tc('actInformationBar.detail.saveConfigError')
                        });
                        throw error;
                    })
            ])
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$tc('actInformationBar.detail.saveSuccess')
                    });

                    return this.loadData();
                })
                .finally(() => {
                    this.isSaving = false;
                })
                // loadData() handles its own load-error notifications internally and
                // never rejects (see loadData()), so this only ever catches the two
                // save rethrows above, which already produced a notification. Swallowed
                // here purely to avoid an unhandled promise rejection, since no caller
                // of onSave() attaches its own .catch().
                .catch(() => {});
        }
    }
});
