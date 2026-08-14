/**
 * Alpine component behind the "scan the replacement tag" affordance on the Replace Tag form.
 * Registered in app.js as Alpine.data('tagReplace').
 *
 * Three input paths converge on the same hidden result — the <select name="new_tag_id"> the
 * form already submits:
 *   1. Hardware barcode reader — types the tag number (or a QR's /scan/{n} URL) into the text
 *      input + Enter, exactly like a keyboard wedge. No camera, no JS beyond resolve().
 *   2. Phone camera — html5-qrcode (lazy-imported) decodes and fills the same input.
 *   3. The existing dropdown — still there as a manual fallback.
 *
 * A scanned tag is matched against the available-pool map the Blade handed us; only pool tags
 * are valid replacements, so an unknown/assigned tag reports "not available" rather than
 * silently doing nothing.
 */
export default function tagReplace({ viewerId, selectId, tags }) {
    return {
        scanner: null,
        active: false,
        starting: false,
        error: '',
        snapshot: '',
        scanInput: '',
        matchStatus: '',      // '' | 'matched' | 'notfound'
        matchedNumber: '',

        /** A QR tag's payload is a full url("/scan/{tag}"); a barcode carries the bare number. */
        normalise(text) {
            const raw = (text || '').trim();
            if (! raw) {
                return '';
            }
            const match = raw.match(/\/scan\/([^/?#]+)/);
            return match ? decodeURIComponent(match[1]) : raw;
        },

        /** Resolve the current input to a pool tag and drive the submitted <select>. */
        resolve() {
            const number = this.normalise(this.scanInput);
            const select = document.getElementById(selectId);

            if (! number) {
                this.matchStatus = '';
                if (select) select.value = '';
                return;
            }

            const id = tags[number];
            if (id !== undefined && id !== null) {
                if (select) {
                    select.value = String(id);
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                }
                this.matchedNumber = number;
                this.matchStatus = 'matched';
            } else {
                if (select) select.value = '';
                this.matchStatus = 'notfound';
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
            const number = this.normalise(decoded);
            if (! number) {
                return;
            }
            // Freeze the decoded frame before releasing the camera, then resolve to a pool tag.
            this.snapshot = this.snapshotFrom();
            await this.stop();
            this.scanInput = number;
            this.resolve();
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
