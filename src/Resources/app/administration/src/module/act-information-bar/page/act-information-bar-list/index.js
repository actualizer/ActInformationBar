import template from './act-information-bar-list.html.twig';
import './act-information-bar-list.scss';

const { Criteria } = Shopware.Data;
const { Mixin } = Shopware;

Shopware.Component.register('act-information-bar-list', {
    template,

    inject: ['repositoryFactory', 'acl', 'informationBarDefaultsService'],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification')
    ],

    data() {
        return {
            isLoading: true,
            bars: null,
            total: 0,
            sortBy: 'name',
            sortDirection: 'ASC',
            // Stays null until the defaults API answers; periodOf then keeps the platform
            // default rather than guessing a zone.
            timezone: null
        };
    },

    computed: {
        barRepository() {
            return this.repositoryFactory.create('act_information_bar');
        },

        barCriteria() {
            const criteria = new Criteria(this.page, this.limit);
            criteria.addAssociation('salesChannel');
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));

            return criteria;
        },

        // Matches the "add bar" button's tooltip pattern: the defaults route itself is
        // privilege-gated (act_information_bar.editor), so the button must be disabled too.
        defaultsButtonTooltip() {
            return {
                message: this.$t('sw-privileges.tooltip.warning'),
                disabled: this.acl.can('act_information_bar.editor'),
                showOnDisabledElements: true,
                position: 'bottom'
            };
        },

        // Same pattern as defaultsButtonTooltip: the "Duplicate" context menu entry is
        // gated on act_information_bar.creator, same as the "add bar" button above.
        duplicateTooltip() {
            return {
                message: this.$t('sw-privileges.tooltip.warning'),
                disabled: this.acl.can('act_information_bar.creator'),
                showOnDisabledElements: true,
                position: 'bottom'
            };
        },

        columns() {
            return [
                {
                    property: 'name',
                    label: this.$tc('actInformationBar.list.columnName'),
                    routerLink: 'act.information.bar.detail',
                    primary: true
                },
                {
                    // Sorted by the displayed channel name rather than the raw id, which would
                    // order by UUID and look arbitrary. Bars without a channel sort as NULL.
                    property: 'salesChannelId',
                    dataIndex: 'salesChannel.name',
                    label: this.$tc('actInformationBar.list.columnSalesChannel')
                },
                {
                    // Virtual column combining startDate/endDate. It is sortable through
                    // dataIndex, which points the sorting at the two real fields behind it.
                    property: 'period',
                    dataIndex: 'startDate,endDate',
                    label: this.$tc('actInformationBar.list.columnPeriod')
                },
                {
                    // Derived from active/startDate/endDate *and the current time*, so unlike
                    // "period" above there is no set of DAL fields that reproduces the shown
                    // order. Sorting stays off rather than offering a misleading one.
                    property: 'status',
                    label: this.$tc('actInformationBar.list.columnStatus'),
                    sortable: false
                }
            ];
        }
    },

    methods: {
        getList() {
            this.isLoading = true;

            // The timezone is awaited alongside the search so the first render already has it;
            // otherwise the rows would briefly show times in a different zone.
            return Promise.all([
                this.barRepository.search(this.barCriteria, Shopware.Context.api),
                this.loadTimezone()
            ])
                .then(([result]) => {
                    this.bars = result;
                    this.total = result.total;

                    return this.bars;
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('actInformationBar.list.loadError')
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        // sw-entity-listing re-searches on its own for pagination/sorting (it carries its
        // own criteria on the loaded result) and only reports the new total back here.
        updateTotal({ total }) {
            this.total = total;
        },

        // Loaded once per visit. A failure is swallowed on purpose: the list must still show
        // its bars, and periodOf then falls back to the platform's own formatting.
        loadTimezone() {
            if (this.timezone) {
                return Promise.resolve(this.timezone);
            }

            return this.informationBarDefaultsService.getTimezone()
                .then((timezone) => {
                    this.timezone = timezone;

                    return timezone;
                })
                .catch(() => null);
        },

        // sw-entity-listing swallows delete errors and only reports them through these two
        // events, so without a handler a failed delete leaves the row in place with no
        // feedback at all.
        onDeleteFailed() {
            this.createNotificationError({
                message: this.$tc('actInformationBar.list.deleteError')
            });
        },

        onDeleteItemsFailed() {
            this.createNotificationError({
                message: this.$tc('actInformationBar.list.deleteMultipleError')
            });
        },

        // Mirrors BarScheduleResolver, evergreen-if-both-null included. bar.startDate carries
        // an explicit offset and JS Date comparisons are instant-based, so the browser's
        // timezone is not the cause of any disagreement - client/server clock drift would be.
        statusOf(bar) {
            if (!bar.active) {
                return {
                    icon: 'regular-pause',
                    colorClass: 'is--inactive',
                    label: this.$tc('actInformationBar.list.statusInactive')
                };
            }

            if (!bar.startDate && !bar.endDate) {
                return {
                    icon: 'regular-check-circle',
                    colorClass: 'is--running',
                    label: this.$tc('actInformationBar.list.statusEvergreen')
                };
            }

            const now = new Date();

            if (bar.startDate && now < new Date(bar.startDate)) {
                return {
                    icon: 'regular-clock',
                    colorClass: 'is--planned',
                    label: this.$tc('actInformationBar.list.statusPlanned')
                };
            }

            if (bar.endDate && now > new Date(bar.endDate)) {
                return {
                    icon: 'regular-times-circle',
                    colorClass: 'is--expired',
                    label: this.$tc('actInformationBar.list.statusExpired')
                };
            }

            return {
                icon: 'regular-check-circle',
                colorClass: 'is--running',
                label: this.$tc('actInformationBar.list.statusRunning')
            };
        },

        periodOf(bar) {
            const filter = Shopware.Filter.getByName('date');
            // Formatted in the shop timezone the detail page edits against. Without it the
            // platform formats in the editing user's own profile timezone, so the same bar
            // would show two different times depending on who is looking.
            const options = this.timezone ? { timeZone: this.timezone } : {};
            const dateFilter = (value) => filter(value, options);

            if (!bar.startDate && !bar.endDate) {
                return this.$tc('actInformationBar.list.periodEvergreen');
            }

            // $t, not $tc: the pluralisation API drops named values, which would render the
            // period without any dates at all.
            if (bar.startDate && !bar.endDate) {
                return this.$t('actInformationBar.list.periodFrom', { date: dateFilter(bar.startDate) });
            }

            if (!bar.startDate && bar.endDate) {
                return this.$t('actInformationBar.list.periodUntil', { date: dateFilter(bar.endDate) });
            }

            return this.$t('actInformationBar.list.periodRange', {
                from: dateFilter(bar.startDate),
                until: dateFilter(bar.endDate)
            });
        },

        salesChannelOf(bar) {
            if (!bar.salesChannelId) {
                return this.$tc('actInformationBar.list.allSalesChannels');
            }

            return bar.salesChannel?.translated?.name ?? bar.salesChannel?.name ?? '';
        },

        // Clears the schedule so the clone does not immediately compete with its
        // template for the same window (see BarScheduleResolver's overlap warning).
        onDuplicate(id) {
            return this.barRepository.clone(id, Shopware.Context.api, {
                overwrites: { startDate: null, endDate: null },
            })
                .then((clone) => this.$router.push({ name: 'act.information.bar.detail', params: { id: clone.id } }))
                .catch(() => {
                    this.createNotificationError({ message: this.$tc('actInformationBar.list.duplicateError') });
                });
        }
    }
});
