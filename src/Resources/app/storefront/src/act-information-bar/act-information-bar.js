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

        this.duration = parseInt(this.messageContainer.dataset.duration || '3') * 1000;
        this.lineElements = Array.from(this.messageContainer.querySelectorAll('.' + this.options.lineClass));
        this.currentLineIndex = 0;

        if (this.lineElements.length > 1) {
            this.timeout = setTimeout(() => this.showNextLine(), this.duration);
        }
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
            this.currentLineIndex = (this.currentLineIndex + 1) % this.lineElements.length;
            this.setLineActive(this.lineElements[this.currentLineIndex], true);

            this.timeout = setTimeout(() => this.showNextLine(), this.duration);
        }, this.options.fadeDuration);
    }
}
