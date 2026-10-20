import template from './act-information-bar-preview.html.twig';
import './act-information-bar-preview.scss';

const { Component } = Shopware;

const FONT_STYLE_ID = 'act-information-bar-preview-fonts';
const FADE_DURATION = 500;
// Keep in sync with the @font-face block in views/storefront/layout/header/act-information-bar-countdown.html.twig.
const LATIN = 'U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD';
const LATIN_EXT = 'U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF';

// Entity field -> custom property; same set as InformationBarSubscriber::buildStyle().
const STYLE_FIELDS = {
    '--act-bar-text': 'textColor',
    '--act-bar-bg': 'backgroundColor',
    '--act-bar-padding-top': 'paddingTop',
    '--act-bar-padding-bottom': 'paddingBottom',
    '--act-bar-font-size': 'fontSize',
    '--act-bar-btn-text': 'buttonTextColor',
    '--act-bar-btn-text-hover': 'buttonTextColorHover',
    '--act-bar-btn-border': 'buttonBorderColor',
    '--act-bar-btn-border-hover': 'buttonBorderColorHover',
    '--act-bar-btn-border-width': 'buttonBorderWidth',
    '--act-bar-btn-bg': 'buttonBackgroundColor',
    '--act-bar-btn-bg-hover': 'buttonBackgroundColorHover',
    '--act-bar-btn-radius': 'buttonBorderRadius',
    '--act-bar-countdown-color': 'countdownColor',
    '--act-bar-countdown-label-color': 'countdownLabelColor',
};

// Same inputs as PHP is_numeric() (sign, .5, 5., exponent, surrounding whitespace), so the
// storefront and the preview append "px" for exactly the same values.
const PHP_NUMERIC = /^[ \t\n\r\v\f]*[+-]?(\d+(\.\d*)?|\.\d+)([eE][+-]?\d+)?[ \t\n\r\v\f]*$/;

const emptyWarnings = () => ({
    messageEmpty: false,
    message: { removedOnSave: [], invisibleRemoved: 0 },
    expiredText: { removedOnSave: [], invisibleRemoved: 0 },
});

const pickWarnings = (warnings) => ({
    removedOnSave: warnings?.removedOnSave ?? [],
    invisibleRemoved: warnings?.invisibleRemoved ?? 0,
});

Component.register('act-information-bar-preview', {
    template,

    inject: ['informationBarPreviewService'],

    props: {
        bar: {
            type: Object,
            required: false,
            default: null,
        },
    },

    emits: ['update:warnings'],

    data() {
        return {
            // Both are filtered server-side by BarTextFilter and are the only values allowed in v-html.
            lines: [],
            expiredHtml: null,
            activeIndex: 0,
            now: Date.now(),
            isScrollable: false,
        };
    },

    computed: {
        styleVars() {
            const style = {};
            let hasLabelColor = false;

            Object.entries(STYLE_FIELDS).forEach(([property, field]) => {
                let value = this.bar?.[field];

                if (value === null || value === undefined || value === '') {
                    return;
                }

                value = String(value);

                // Same rejection as the storefront: no way to close the declaration list.
                if (value.includes(';') || value.includes('}')) {
                    return;
                }

                // Bare numbers need an explicit unit.
                if (PHP_NUMERIC.test(value)) {
                    value += 'px';
                }

                style[property] = value;
                hasLabelColor = hasLabelColor || property === '--act-bar-countdown-label-color';
            });

            if (hasLabelColor) {
                style['--act-bar-countdown-label-opacity'] = '1';
            }

            return style;
        },

        targetTime() {
            const time = this.bar?.countdownTarget ? Date.parse(this.bar.countdownTarget) : NaN;

            return Number.isNaN(time) ? null : time;
        },

        isExpired() {
            return this.targetTime !== null && Math.ceil((this.targetTime - this.now) / 1000) <= 0;
        },

        // Expired without text: the storefront renders no countdown block at all.
        showCountdown() {
            return this.targetTime !== null && (!this.isExpired || this.expiredHtml !== null);
        },

        useMontserrat() {
            return this.bar?.countdownFont === 'montserrat';
        },

        cells() {
            const remaining = this.targetTime === null
                ? 0
                : Math.max(0, Math.ceil((this.targetTime - this.now) / 1000));
            const pad = (value) => String(value).padStart(2, '0');

            return {
                d: pad(Math.floor(remaining / 86400)),
                h: pad(Math.floor((remaining % 86400) / 3600)),
                m: pad(Math.floor((remaining % 3600) / 60)),
                s: pad(remaining % 60),
                daysHidden: remaining < 86400,
            };
        },

        showButton() {
            return !!this.bar?.showButton && !!this.bar?.buttonText && !!this.bar?.buttonUrl;
        },

        rotationKey() {
            return `${this.lines.length}|${this.bar?.displayDuration}`;
        },

        contentKey() {
            return `${this.bar?.message}\u0000${this.bar?.countdownExpiredText}`;
        },
    },

    watch: {
        contentKey: {
            immediate: true,
            handler(key, oldKey) {
                if (oldKey === undefined) {
                    this.fetchPreview();
                } else {
                    this.debouncedFetch();
                }
            },
        },

        rotationKey: {
            immediate: true,
            handler() {
                this.restartRotation();
            },
        },

        targetTime: {
            immediate: true,
            handler() {
                this.restartCountdown();
            },
        },

        useMontserrat: {
            immediate: true,
            handler(use) {
                if (use) {
                    this.ensureFont();
                }
            },
        },
    },

    created() {
        this.debouncedFetch = Shopware.Utils.debounce(() => {
            this.fetchPreview();
        }, 300);
    },

    mounted() {
        // The page scrolls inside sw-card-view__content; without this, a field focused by Shift+Tab
        // scrolls to the very top edge, hidden under the sticky preview.
        this.scrollContainer = this.$el.closest('.sw-card-view__content');
        this.resizeObserver = new ResizeObserver(() => {
            this.isScrollable = this.$el.scrollHeight > this.$el.clientHeight;
            this.scrollContainer?.style.setProperty(
                'scroll-padding-top',
                `${this.$el.getBoundingClientRect().height + 20}px`,
            );
        });
        this.resizeObserver.observe(this.$el);
    },

    beforeUnmount() {
        this.resizeObserver?.disconnect();
        this.scrollContainer?.style.removeProperty('scroll-padding-top');
        this.debouncedFetch.cancel();
        clearTimeout(this.rotationTimeout);
        clearTimeout(this.countdownTimeout);
        this.destroyed = true;
        document.getElementById(FONT_STYLE_ID)?.remove();
    },

    methods: {
        fetchPreview() {
            if (!this.bar) {
                this.lines = [];
                this.expiredHtml = null;
                this.$emit('update:warnings', emptyWarnings());

                return Promise.resolve();
            }

            const requestedFor = this.contentKey;
            // A newer edit already superseded this answer, or the page is gone.
            const isStale = () => this.destroyed || requestedFor !== this.contentKey;

            return this.informationBarPreviewService
                .previewText(this.bar.message ?? '', this.bar.countdownExpiredText ?? null)
                .then((result) => {
                    if (isStale()) {
                        return;
                    }

                    this.lines = result?.lines ?? [];
                    this.expiredHtml = result?.expiredHtml ?? null;
                    this.$emit('update:warnings', {
                        // True only for an answered preview without any line, never for a failed one.
                        messageEmpty: this.lines.length === 0,
                        message: pickWarnings(result?.messageWarnings),
                        expiredText: pickWarnings(result?.expiredTextWarnings),
                    });
                })
                .catch(() => {
                    // Advisory only; a failed preview must not disturb editing.
                    if (!isStale()) {
                        this.lines = [];
                        this.expiredHtml = null;
                        this.$emit('update:warnings', emptyWarnings());
                    }
                });
        },

        restartRotation() {
            clearTimeout(this.rotationTimeout);
            this.activeIndex = 0;

            if (this.lines.length < 2) {
                return;
            }

            const duration = Math.min(10, Math.max(1, Number(this.bar?.displayDuration) || 3)) * 1000;
            // Like the storefront: fade the active line out first, then fade the next one in.
            const step = () => {
                this.rotationTimeout = setTimeout(() => {
                    const next = (this.activeIndex + 1) % this.lines.length;
                    this.activeIndex = -1;
                    this.rotationTimeout = setTimeout(() => {
                        this.activeIndex = next;
                        step();
                    }, FADE_DURATION);
                }, duration);
            };

            step();
        },

        restartCountdown() {
            clearTimeout(this.countdownTimeout);
            this.now = Date.now();

            if (this.targetTime === null) {
                return;
            }

            const tick = () => {
                this.now = Date.now();

                if (this.targetTime - this.now <= 0) {
                    return;
                }

                this.countdownTimeout = setTimeout(tick, 1000 - (Date.now() % 1000));
            };

            tick();
        },

        ensureFont() {
            if (document.getElementById(FONT_STYLE_ID)) {
                return;
            }

            const asset = Shopware.Filter.getByName('asset');
            // assetsPath already ends in the bundles folder, so the path starts at the bundle name.
            const rules = [];

            [600, 800].forEach((weight) => {
                [['latin-ext', LATIN_EXT], ['latin', LATIN]].forEach(([subset, range]) => {
                    const url = asset(`actinformationbar/fonts/montserrat-${subset}-${weight}-normal.woff2`);
                    rules.push(`@font-face { font-family: 'ActInformationBarMontserrat'; font-style: normal; font-display: swap; font-weight: ${weight}; src: url('${url}') format('woff2'); unicode-range: ${range}; }`);
                });
            });

            const style = document.createElement('style');
            style.id = FONT_STYLE_ID;
            style.textContent = rules.join('\n');
            document.head.appendChild(style);
        },
    },
});
