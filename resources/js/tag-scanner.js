/**
 * Alpine component behind the "Scan with camera" affordance in the asset-create form's
 * Barcode / Tag section. Registered in app.js as Alpine.data('tagScanner').
 *
 * Unlike <x-qr-scanner> (qr-scanner.js), which navigates to /scan/{tag} to resolve a tag,
 * this one writes the decoded tag number into an existing form <input> so the tag is assigned
 * as part of the normal Create Asset submit. Hardware barcode scanners need no JS at all —
 * they type into the same input as keyboard wedges; this only covers the phone-camera case.
 *
 * html5-qrcode (~300 kB) is lazy-imported inside start() so it only loads when the user taps
 * the button — the same reason it lives in the bundle rather than inline in a Blade attribute.
 */
export default function tagScanner({ viewerId, inputId }) {
    return {
        scanner: null,
        active: false,
        starting: false,
        error: '',
        captured: '',

        /**
         * A QR tag's payload is a full url("/scan/{tag}"); a barcode carries the bare tag
         * number. Accept either and reduce to the tag number (mirrors qr-scanner.js#normalise).
         */
        normalise(text) {
            const raw = (text || '').trim();
            if (! raw) {
                return '';
            }
            const match = raw.match(/\/scan\/([^/?#]+)/);
            return match ? decodeURIComponent(match[1]) : raw;
        },

        async toggle() {
            return this.active ? this.stop() : this.start();
        },

        async start() {
            if (this.active || this.starting) {
                return;
            }

            this.starting = true;
            this.error = '';

            try {
                const { Html5Qrcode } = await import('html5-qrcode');

                this.scanner = new Html5Qrcode(viewerId, { verbose: false });

                await this.scanner.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: { width: 240, height: 240 } },
                    (decoded) => this.onDecode(decoded),
                    () => {}
                );

                this.active = true;
            } catch (error) {
                this.error = this.describe(error);
                this.scanner = null;
            } finally {
                this.starting = false;
            }
        },

        async stop() {
            if (! this.scanner) {
                return;
            }

            try {
                if (this.active) {
                    await this.scanner.stop();
                }
                this.scanner.clear();
            } catch {
                // Already stopped or the element is gone — nothing to recover.
            }

            this.scanner = null;
            this.active = false;
        },

        async onDecode(decoded) {
            const tag = this.normalise(decoded);
            if (! tag) {
                return;
            }

            // Release the camera before we do anything else, or on some Android browsers the
            // stream stays held and a second scan fails to acquire it.
            await this.stop();

            const input = document.getElementById(inputId);
            if (input) {
                input.value = tag;
                // Let any listeners (and Alpine, if this input is ever x-model'd) react.
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
            this.captured = tag;
        },

        describe(error) {
            const name = error?.name ?? '';
            const message = typeof error === 'string' ? error : (error?.message ?? '');

            if (name === 'NotAllowedError' || /permission/i.test(message)) {
                return 'Camera access was denied. Allow it in your browser settings, or type the tag number instead.';
            }
            if (name === 'NotFoundError' || /no camera|not found/i.test(message)) {
                return 'No camera found on this device. Type the tag number instead.';
            }
            if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
                return 'The camera needs a secure connection (HTTPS). Type the tag number instead.';
            }
            return message || 'Could not start the camera. Type the tag number instead.';
        },

        destroy() {
            this.stop();
        },
    };
}
