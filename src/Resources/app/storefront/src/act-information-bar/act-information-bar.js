import Plugin from 'src/plugin-system/plugin.class';

export default class ActInformationBar extends Plugin {
    static options = {
        messageContainerSelector: '.act-info-message-container',
        fadeInDuration: 500,
        fadeOutDuration: 500
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

        if (this.lines.length === 1) {
            // Nur eine Zeile: dauerhaft anzeigen, keine Animation
            const messageElement = document.createElement('div');
            messageElement.textContent = this.lines[0];
            messageElement.style.opacity = '1';
            this.messageContainer.innerHTML = '';
            this.messageContainer.appendChild(messageElement);
            return;
        }

        if (this.lines.length > 1) {
            this.showNextLine();
        }
    }

    showNextLine() {
        const line = this.lines[this.currentLineIndex];
        const messageElement = document.createElement('div');
        messageElement.textContent = line;
        messageElement.style.opacity = '0';
        messageElement.style.transition = `opacity ${this.options.fadeInDuration}ms`;

        // Clear container and add new line
        this.messageContainer.innerHTML = '';
        this.messageContainer.appendChild(messageElement);

        // Fade in
        setTimeout(() => {
            messageElement.style.opacity = '1';
        }, 50);

        // Start fade out
        setTimeout(() => {
            messageElement.style.opacity = '0';

            // After fade out, show next line
            setTimeout(() => {
                this.currentLineIndex = (this.currentLineIndex + 1) % this.lines.length;
                this.showNextLine();
            }, this.options.fadeOutDuration);

        }, this.duration);
    }
}
