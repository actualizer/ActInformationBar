const Plugin = window.PluginBaseClass;

export default class ActInformationBar extends Plugin {
    static options = {
        messageContainerSelector: '.act-info-message-container',
        lineClass: 'act-info-message',
        activeClass: 'is-active',
        // Keep in sync with $act-information-bar-fade-duration in base.scss
        fadeDuration: 500
    };

    init() {
        this.messageContainer = this.el.querySelector(this.options.messageContainerSelector);
        if (!this.messageContainer) {
            return;
        }

        this.message = this.messageContainer.dataset.message || '';
        this.duration = parseInt(this.messageContainer.dataset.duration || '3') * 1000;
        this.lines = this.message.split('\n').filter(line => line.trim() !== '');
        this.currentLineIndex = 0;

        if (this.lines.length === 0) {
            return;
        }

        this.renderLines();

        if (this.lines.length > 1) {
            this.timeout = setTimeout(() => this.showNextLine(), this.duration);
        }
    }

    /**
     * Render every line once. They are stacked on top of each other by CSS, so
     * the container keeps the width of the longest line and neighbouring
     * elements (the button) do not move while the lines rotate.
     */
    renderLines() {
        this.messageContainer.innerHTML = '';

        this.lineElements = this.lines.map((line, index) => {
            const lineElement = document.createElement('div');
            lineElement.className = this.options.lineClass;
            lineElement.textContent = line;
            this.messageContainer.appendChild(lineElement);
            this.setLineActive(lineElement, index === 0);

            return lineElement;
        });
    }

    setLineActive(lineElement, active) {
        lineElement.classList.toggle(this.options.activeClass, active);
        // Hidden lines stay in the DOM for the width, but screen readers should
        // only ever announce the line that is currently visible.
        lineElement.setAttribute('aria-hidden', active ? 'false' : 'true');
    }

    showNextLine() {
        this.setLineActive(this.lineElements[this.currentLineIndex], false);

        this.timeout = setTimeout(() => {
            this.currentLineIndex = (this.currentLineIndex + 1) % this.lines.length;
            this.setLineActive(this.lineElements[this.currentLineIndex], true);

            this.timeout = setTimeout(() => this.showNextLine(), this.duration);
        }, this.options.fadeDuration);
    }
}
