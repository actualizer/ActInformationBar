const Plugin = window.PluginBaseClass;

export default class ActInformationBarCountdown extends Plugin {
    init() {
        this.target = Date.parse(this.el.dataset.target || '');
        if (Number.isNaN(this.target)) {
            // Keep the server-rendered values.
            return;
        }

        this.srElement = this.el.querySelector('.act-info-countdown-sr');
        this.expiredElement = this.el.querySelector('.act-info-countdown-expired');
        this.srStrings = this.parseStrings(this.el.dataset.srStrings);
        this.lastSrMinute = null;
        this.daysHidden = null;
        this.nodes = {};

        ['d', 'h', 'm', 's'].forEach((unit) => {
            const cell = this.el.querySelector(`.act-info-countdown-cell[data-unit="${unit}"]`);
            this.nodes[unit] = cell ? { cell, num: cell.querySelector('.act-info-countdown-num'), text: null } : null;
        });
        this.daysColon = this.el.querySelector('.act-info-countdown-colon[data-colon="d"]');

        this.tick();
    }

    destroy() {
        clearTimeout(this.timeout);
    }

    tick() {
        const remaining = Math.max(0, Math.ceil((this.target - Date.now()) / 1000));

        if (remaining <= 0) {
            this.expire();
            return;
        }

        this.render(remaining);
        this.timeout = setTimeout(() => this.tick(), 1000 - (Date.now() % 1000));
    }

    render(remaining) {
        const days = Math.floor(remaining / 86400);
        const values = {
            d: days,
            h: Math.floor((remaining % 86400) / 3600),
            m: Math.floor((remaining % 3600) / 60),
            s: remaining % 60
        };

        Object.keys(values).forEach((unit) => {
            const node = this.nodes[unit];
            if (!node || !node.num) {
                return;
            }
            const text = String(values[unit]).padStart(2, '0');
            if (node.text !== text && node.num.textContent !== text) {
                node.num.textContent = text;
            }
            node.text = text;
        });

        if (this.daysHidden !== (days === 0)) {
            this.daysHidden = days === 0;
            if (this.nodes.d) {
                this.nodes.d.cell.hidden = this.daysHidden;
            }
            if (this.daysColon) {
                this.daysColon.hidden = this.daysHidden;
            }
        }

        this.updateScreenReaderText(remaining);
    }

    parseStrings(raw) {
        try {
            return JSON.parse(raw || '{}');
        } catch {
            return {};
        }
    }

    unit(name, count) {
        const strings = this.srStrings[name] || {};

        return ((count === 1 ? strings.one : strings.other) || '').replace('{count}', count);
    }

    updateScreenReaderText(remaining) {
        // Rounded up so the last running minute reads "1 minute". Updated at most once per
        // minute and without aria-live on purpose, to keep assistive tech quiet.
        const totalMinutes = Math.ceil(remaining / 60);
        if (!this.srElement || this.lastSrMinute === totalMinutes || !this.srStrings.remaining) {
            return;
        }
        this.lastSrMinute = totalMinutes;

        const days = Math.floor(totalMinutes / 1440);
        const hours = Math.floor((totalMinutes % 1440) / 60);
        const minutes = totalMinutes % 60;
        const parts = [];
        if (days > 0) {
            parts.push(this.unit('day', days));
        }
        if (hours > 0) {
            parts.push(this.unit('hour', hours));
        }
        if (minutes > 0) {
            parts.push(this.unit('minute', minutes));
        }

        const time = parts.length > 1
            ? parts.slice(0, -1).join(', ') + (this.srStrings.and || ', ') + parts[parts.length - 1]
            : parts[0];

        this.srElement.textContent = this.srStrings.remaining.replace('{time}', time);
    }

    expire() {
        // Cells stay in the layout (invisible via CSS) so the block keeps its height and width.
        this.el.classList.add('is-expired');
        if (this.srElement) {
            this.srElement.hidden = true;
            this.srElement.setAttribute('aria-hidden', 'true');
        }

        if (this.expiredElement) {
            this.expiredElement.hidden = false;
        } else {
            this.el.hidden = true;
        }
    }
}
