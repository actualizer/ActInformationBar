import template from './act-information-bar-detail.html.twig';
import './act-information-bar-detail.scss';

const { Criteria } = Shopware.Data;
const { Mixin } = Shopware;

Shopware.Component.register('act-information-bar-detail', {
    template,

    inject: ['repositoryFactory', 'acl', 'informationBarDefaultsService'],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            bar: null,
            isLoading: false,
            isSaving: false,
            timezone: 'UTC',
        };
    },

    computed: {
        barRepository() {
            return this.repositoryFactory.create('act_information_bar');
        },

        isCreateMode() {
            return this.$route.name === 'act.information.bar.create';
        },

        buttonTargetOptions() {
            return [
                { id: 1, value: '_self', label: this.$tc('actInformationBar.detail.buttonTargetSelfOption') },
                { id: 2, value: '_blank', label: this.$tc('actInformationBar.detail.buttonTargetBlankOption') },
            ];
        },

        // Mirrors the list page's defaultsButtonTooltip pattern: the save action itself is
        // privilege-gated, so the button must be disabled with the same tooltip, not just hidden.
        saveButtonTooltip() {
            return {
                message: this.$t('sw-privileges.tooltip.warning'),
                disabled: this.acl.can('act_information_bar.editor'),
                showOnDisabledElements: true,
                position: 'bottom',
            };
        },
    },

    created() {
        this.loadData();
    },

    methods: {
        loadData() {
            this.isLoading = true;

            return Promise.all([
                this.loadBar().catch(() => {
                    this.createNotificationError({ message: this.$tc('actInformationBar.detail.loadTextsError') });
                }),
                this.informationBarDefaultsService.getTimezone()
                    .then((timezone) => {
                        this.timezone = timezone;
                    })
                    .catch(() => {
                        this.createNotificationError({ message: this.$tc('actInformationBar.detail.loadConfigError') });
                    }),
            ]).finally(() => {
                this.isLoading = false;
            });
        },

        loadBar() {
            if (this.isCreateMode) {
                return this.informationBarDefaultsService.getDefaults(null).then((defaults) => {
                    const bar = this.barRepository.create(Shopware.Context.api);
                    bar.active = true;
                    bar.name = '';
                    // Copied, not inherited: the values stay visible in the form and later
                    // changes to the defaults must not repaint existing bars.
                    Object.entries(defaults).forEach(([key, value]) => {
                        if (value !== null && value !== undefined) {
                            bar[key] = value;
                        }
                    });
                    this.bar = bar;
                });
            }

            const criteria = new Criteria();
            criteria.addAssociation('translations');

            return this.barRepository.get(this.$route.params.id, Shopware.Context.api, criteria)
                .then((bar) => {
                    this.bar = bar;
                });
        },

        onLanguageChanged() {
            this.loadData();
        },

        // Routes every field edit through a named handler instead of binding v-model straight
        // into the nested entity (see plugin_dev_rules.md).
        onFieldInput(field, value) {
            this.bar[field] = value;
        },

        onSave() {
            if (!this.bar) {
                return Promise.resolve();
            }

            this.isSaving = true;

            // Warnings only, never blocking: the bar is saved either way.
            this.checkInvalidWindow();
            this.checkDstTransition();

            return this.checkOverlap()
                .then(() => this.barRepository.save(this.bar, Shopware.Context.api))
                .then(() => {
                    this.createNotificationSuccess({ message: this.$tc('actInformationBar.detail.saveSuccess') });

                    // Deliberately not returned/awaited: once the save itself succeeded and the
                    // success notification fired, a navigation or reload failure here must not
                    // also trigger the save-error catch below for an operation that did work.
                    if (this.isCreateMode) {
                        this.$router.push({ name: 'act.information.bar.detail', params: { id: this.bar.id } }).catch(() => {});
                    } else {
                        this.loadData();
                    }
                })
                .catch(() => {
                    this.createNotificationError({ message: this.$tc('actInformationBar.detail.saveTextsError') });
                })
                .finally(() => {
                    this.isSaving = false;
                });
        },

        // Overlap is normal (an evergreen bar overlaps every scheduled one) — only two
        // scheduled bars of the same sales channel are worth warning about.
        checkOverlap() {
            if (!this.bar.startDate && !this.bar.endDate) {
                return Promise.resolve();
            }

            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('salesChannelId', this.bar.salesChannelId ?? null));
            criteria.addFilter(Criteria.not('AND', [Criteria.equals('id', this.bar.id)]));

            return this.barRepository.search(criteria, Shopware.Context.api).then((result) => {
                const clash = result.find((other) => {
                    if (!other.startDate && !other.endDate) {
                        return false;
                    }

                    const aStart = this.bar.startDate ? new Date(this.bar.startDate) : new Date(-8640000000000000);
                    const aEnd = this.bar.endDate ? new Date(this.bar.endDate) : new Date(8640000000000000);
                    const bStart = other.startDate ? new Date(other.startDate) : new Date(-8640000000000000);
                    const bEnd = other.endDate ? new Date(other.endDate) : new Date(8640000000000000);

                    return aStart <= bEnd && bStart <= aEnd;
                });

                if (clash) {
                    this.createNotificationWarning({
                        // $t, not $tc: the pluralisation API drops named values, which would
                        // leave the placeholder in this message empty.
                        message: this.$t('actInformationBar.detail.overlapWarning', { name: clash.name }),
                    });
                }
            });
        },

        // Catches both the plain typo (end before start) and the rarer DST spring-forward
        // collapse, where two distinct entered times normalize to the same real instant.
        checkInvalidWindow() {
            if (!this.bar.startDate || !this.bar.endDate) {
                return;
            }

            if (new Date(this.bar.endDate) <= new Date(this.bar.startDate)) {
                this.createNotificationWarning({
                    message: this.$tc('actInformationBar.detail.invalidWindowWarning'),
                });
            }
        },

        // Warns when start or end date falls on a day the shop timezone shifts its clock, since
        // an hour is then either missing or ambiguous there. Silently skipped while the
        // timezone is not yet known, rather than guessing with the browser's own zone.
        checkDstTransition() {
            if (!this.timezone) {
                return;
            }

            const dates = [this.bar.startDate, this.bar.endDate]
                .filter((value) => !!value)
                .map((value) => new Date(value));

            const warnedDays = new Set();

            dates.forEach((date) => {
                const transition = this.getDstTransition(date, this.timezone);
                if (!transition) {
                    return;
                }

                const dayKey = `${transition.civilDate.year}-${transition.civilDate.month}-${transition.civilDate.day}`;
                if (warnedDays.has(dayKey)) {
                    return;
                }
                warnedDays.add(dayKey);

                const { day, month, year } = transition.civilDate;
                const formattedDate = `${String(day).padStart(2, '0')}.${String(month).padStart(2, '0')}.${year}`;

                this.createNotificationWarning({
                    // $t, not $tc: see the overlap warning above.
                    message: this.$t(transition.missingHour
                        ? 'actInformationBar.detail.dstMissingHourWarning'
                        : 'actInformationBar.detail.dstDuplicateHourWarning', {
                        date: formattedDate,
                        timezone: this.timezone,
                    }),
                });
            });
        },

        // Small formatToParts wrapper shared by the offset and civil-date lookups below;
        // returns null (never throws) so callers can skip the check on an unresolvable zone.
        formatDateTimeParts(instant, timeZone, options) {
            try {
                return new Intl.DateTimeFormat('en-US', { timeZone, ...options })
                    .formatToParts(instant)
                    .reduce((acc, part) => {
                        if (part.type !== 'literal') {
                            acc[part.type] = part.value;
                        }
                        return acc;
                    }, {});
            } catch (e) {
                return null;
            }
        },

        // UTC offset (in minutes) the given zone has at the given instant, derived by re-reading
        // the instant's wall-clock time in that zone and diffing it against the real UTC value.
        getUtcOffsetMinutes(instant, timeZone) {
            const parts = this.formatDateTimeParts(instant, timeZone, {
                hourCycle: 'h23',
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
            });

            if (!parts) {
                return null;
            }

            const asUtc = Date.UTC(
                Number(parts.year), Number(parts.month) - 1, Number(parts.day),
                Number(parts.hour), Number(parts.minute), Number(parts.second),
            );

            return (asUtc - instant.getTime()) / 60000;
        },

        // A calendar day (taken from the instant's date in the given timezone, not the
        // browser's) has a DST transition when the zone's offset differs between the day's
        // start and its end; whether the offset grows or shrinks tells missing from duplicate hour.
        getDstTransition(instant, timeZone) {
            const civil = this.formatDateTimeParts(instant, timeZone, {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
            });

            if (!civil) {
                return null;
            }

            const civilDate = { year: Number(civil.year), month: Number(civil.month), day: Number(civil.day) };
            const dayStart = new Date(Date.UTC(civilDate.year, civilDate.month - 1, civilDate.day, 0, 0, 0));
            const dayEnd = new Date(Date.UTC(civilDate.year, civilDate.month - 1, civilDate.day, 23, 59, 59));

            const startOffset = this.getUtcOffsetMinutes(dayStart, timeZone);
            const endOffset = this.getUtcOffsetMinutes(dayEnd, timeZone);

            if (startOffset === null || endOffset === null || startOffset === endOffset) {
                return null;
            }

            return { civilDate, missingHour: endOffset > startOffset };
        },
    },
});
