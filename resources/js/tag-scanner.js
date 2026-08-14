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
        snapshot: '',

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

        /** Grab the current camera frame as a JPEG data URL for the confirmation thumbnail. */
        snapshotFrom() {
            try {
                const video = document.getElementById(viewerId)?.querySelector('video');
                if (! video || ! video.videoWidth) {
                    return '';
                }
                const canvas = document.createElement('canvas');
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                return canvas.toDataURL('image/jpeg', 0.7);
            } catch {
                return '';
            }
        },

        /** Frame-filling, landscape scan region (mirrors qr-scanner.js#scanBox). */
        scanBox(viewfinderWidth, viewfinderHeight) {
            const vw = viewfinderWidth || 300;
            const vh = viewfinderHeight || 300;
            const width = Math.max(50, Math.floor(vw * 0.85));
            const height = Math.max(50, Math.floor(Math.min(vh * 0.7, width)));
            return { width, height };
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
                const { Html5Qrcode, Html5QrcodeSupportedFormats } = await import('html5-qrcode');

                this.scanner = new Html5Qrcode(viewerId, {
                    verbose: false,
                    // Prefer the native BarcodeDetector where available — far more reliable for
                    // 1D barcodes (Code 128) than the JS/ZXing fallback.
                    experimentalFeatures: { useBarCodeDetectorIfSupported: true },
                    formatsToSupport: [
                        Html5QrcodeSupportedFormats.QR_CODE,
                        Html5QrcodeSupportedFormats.CODE_128,
                        Html5QrcodeSupportedFormats.CODE_39,
                        Html5QrcodeSupportedFormats.EAN_13,
                    ],
                });

                // Reveal the viewfinder and flush the DOM BEFORE start() — starting into a
                // display:none (x-show="active") box yields a zero-size, invisible video.
                this.active = true;
                await this.$nextTick();

                await this.scanner.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: this.scanBox },
                    (decoded) => this.onDecode(decoded),
                    () => {}
                );
            } catch (error) {
                this.active = false;
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

            // Freeze the decoded frame for the confirmation thumbnail before releasing the camera.
            this.snapshot = this.snapshotFrom();

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
