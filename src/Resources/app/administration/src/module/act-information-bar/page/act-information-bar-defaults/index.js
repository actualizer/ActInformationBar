import template from './act-information-bar-defaults.html.twig';
import './act-information-bar-defaults.scss';

const { Mixin } = Shopware;

Shopware.Component.register('act-information-bar-defaults', {
    template,

    inject: ['informationBarDefaultsService', 'acl'],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            defaults: {},
            timezone: 'UTC',
            // Set once the timezone has been fetched for the first time. Unlike "defaults",
            // the timezone is global (BarDefaultsProvider::getTimezone() takes no sales
            // channel), so it must never be re-applied from a later per-channel response —
            // that would silently discard an unsaved edit or resurrect a stale value.
            timezoneLoaded: false,
            timezoneOptions: [],
            salesChannelId: null,
            isLoading: false,
            isSaving: false,
            loaded: false,
            // Loaded (and possibly edited-but-unsaved) defaults per sales channel, keyed by
            // salesChannelId ("" for the global entry) — mirrors sw-system-config's
            // actualConfigData so switching channels never silently discards unsaved edits.
            // Timezone is intentionally NOT part of this cache; see "timezone" above.
            loadedConfigs: {},
        };
    },

    computed: {
        buttonTargetOptions() {
            return [
                { id: 1, value: '_self', label: this.$tc('actInformationBar.detail.buttonTargetSelfOption') },
                { id: 2, value: '_blank', label: this.$tc('actInformationBar.detail.buttonTargetBlankOption') },
            ];
        },
    },

    created() {
        this.timezoneOptions = this.buildTimezoneOptions();
        this.loadData();
    },

    methods: {
        // Intl.supportedValuesOf is missing on some older engines; without it the timezone
        // select falls back to just the browser's own timezone plus UTC.
        buildTimezoneOptions() {
            const values = typeof Intl.supportedValuesOf === 'function'
                ? Intl.supportedValuesOf('timeZone')
                : [...new Set([Intl.DateTimeFormat().resolvedOptions().timeZone, 'UTC'])];

            return values.map((tz, index) => ({ id: index, value: tz, label: tz }));
        },

        configCacheKey(salesChannelId) {
            return salesChannelId ?? '';
        },

        // onLoadError, when given, runs only on a failed (re)load — used by
        // onSalesChannelChanged() to undo its optimistic salesChannelId switch.
        loadData(onLoadError) {
            const key = this.configCacheKey(this.salesChannelId);

            // Load this channel only once per page visit; a cache hit re-shows whatever is
            // currently held for it, including edits the user made but hasn't saved yet.
            if (Object.prototype.hasOwnProperty.call(this.loadedConfigs, key)) {
                this.defaults = this.loadedConfigs[key];
                this.loaded = true;

                return Promise.resolve();
            }

            this.isLoading = true;

            return this.informationBarDefaultsService.load(this.salesChannelId)
                .then((data) => {
                    const defaults = data.defaults ?? {};

                    this.loadedConfigs = { ...this.loadedConfigs, [key]: defaults };
                    this.defaults = defaults;

                    if (!this.timezoneLoaded) {
                        this.timezone = data.timezone ?? 'UTC';
                        this.timezoneLoaded = true;
                    }

                    this.loaded = true;
                })
                .catch(() => {
                    this.createNotificationError({ message: this.$tc('actInformationBar.defaults.loadError') });

                    if (typeof onLoadError === 'function') {
                        onLoadError();
                    }
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onSalesChannelChanged(salesChannelId) {
            const previousSalesChannelId = this.salesChannelId;
            this.salesChannelId = salesChannelId;

            // A failed reload must not leave the selector pointing at a channel whose defaults
            // were never fetched — saving would then silently persist the old channel's values
            // under the new channel's id. Revert the selection instead of trusting stale data.
            return this.loadData(() => {
                this.salesChannelId = previousSalesChannelId;
            });
        },

        // Routes every field edit through a named handler instead of binding v-model straight
        // into the nested object (see plugin_dev_rules.md). Spreading into a new object (rather
        // than assigning the key in place) is required here so newly touched keys stay reactive.
        onFieldInput(field, value) {
            this.defaults = { ...this.defaults, [field]: value };
            this.syncCurrentIntoCache();
        },

        // Timezone is global and lives outside the per-channel cache (see "timezone" in
        // data()), so an edit here needs no cache sync — it must survive a channel switch.
        onTimezoneInput(value) {
            this.timezone = value;
        },

        // Keeps the per-channel defaults cache in step with in-progress edits, so a later
        // switch back to this channel restores the edited-but-unsaved state instead of the
        // loaded one.
        syncCurrentIntoCache() {
            const key = this.configCacheKey(this.salesChannelId);

            this.loadedConfigs = { ...this.loadedConfigs, [key]: this.defaults };
        },

        onSave() {
            this.isSaving = true;

            return this.informationBarDefaultsService.save(this.defaults, this.timezone, this.salesChannelId)
                .then(() => {
                    this.createNotificationSuccess({ message: this.$tc('actInformationBar.defaults.saveSuccess') });
                })
                .catch(() => {
                    this.createNotificationError({ message: this.$tc('actInformationBar.defaults.saveError') });
                })
                .finally(() => {
                    this.isSaving = false;
                });
        },
    },
});
