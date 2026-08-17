import template from './act-information-bar-list.html.twig';
import './act-information-bar-list.scss';

const { Criteria } = Shopware.Data;
const { Mixin } = Shopware;

Shopware.Component.register('act-information-bar-list', {
    template,

    inject: ['repositoryFactory', 'acl'],

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
            sortDirection: 'ASC'
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
                    property: 'salesChannelId',
                    label: this.$tc('actInformationBar.list.columnSalesChannel')
                },
                {
                    // Virtual column combining startDate/endDate; not a DAL field, so it must
                    // never be sortable (a click would try to sort by a non-existent field).
                    property: 'period',
                    label: this.$tc('actInformationBar.list.columnPeriod'),
                    sortable: false
                },
                {
                    // Virtual column derived from active/startDate/endDate; same reasoning
                    // as "period" above.
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

            return this.barRepository.search(this.barCriteria, Shopware.Context.api)
                .then((result) => {
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
            const dateFilter = Shopware.Filter.getByName('date');

            if (!bar.startDate && !bar.endDate) {
                return this.$tc('actInformationBar.list.periodEvergreen');
            }

            if (bar.startDate && !bar.endDate) {
                return this.$tc('actInformationBar.list.periodFrom', 0, { date: dateFilter(bar.startDate) });
            }

            if (!bar.startDate && bar.endDate) {
                return this.$tc('actInformationBar.list.periodUntil', 0, { date: dateFilter(bar.endDate) });
            }

            return this.$tc('actInformationBar.list.periodRange', 0, {
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
