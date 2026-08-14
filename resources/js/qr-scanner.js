/**
 * M15 — Alpine component behind <x-qr-scanner>. Registered in app.js as Alpine.data('qrScanner').
 *
 * Lives in the bundle rather than inline in the Blade component for one reason: html5-qrcode
 * is ~300 kB and must only load when someone actually taps Start. A dynamic import() written
 * inline in an x-data attribute is never seen by Vite, so the bare specifier would fail to
 * resolve in the browser; here Vite code-splits it into its own chunk.
 */
export default function qrScanner({ viewerId, resolveUrl, tagPlaceholder }) {
    return {
        scanner: null,
        active: false,
        starting: false,
        error: '',
        // Scan-confirmation: freeze the decoded frame + tag and let the user confirm before
        // we navigate, so a stray read of an adjacent label doesn't whisk them away.
        confirming: false,
        captured: '',
        snapshot: '',

        /** Build /scan/{tag} from the template the Blade component handed us. */
        urlFor(tag) {
            return resolveUrl.replace(tagPlaceholder, encodeURIComponent(tag));
        },

        /**
         * Scan region sized from the live viewfinder rather than a fixed 240px square: fill
         * most of the frame so a code doesn't have to be lined up in a tiny box, and make it
         * landscape so wide 1D barcodes fit (QR still fits within the shorter height).
         */
        scanBox(viewfinderWidth, viewfinderHeight) {
            // Guard tiny/zero viewfinder dimensions and clamp to html5-qrcode's 50px minimum —
            // returning anything smaller makes start() throw and scanning never begins.
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

        /**
         * A QR tag's payload is a full url("/scan/{tag}") (TagService::generateBatch), but a
         * barcode carries the bare tag number and a hand-typed entry could be either. Accept
         * all three and reduce to the tag number.
         */
        normalise(text) {
            const raw = (text || '').trim();

            if (! raw) {
                return '';
            }

            const match = raw.match(/\/scan\/([^/?#]+)/);

            return match ? decodeURIComponent(match[1]) : raw;
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
                    // Prefer the browser's native BarcodeDetector where available — far more
                    // reliable for 1D barcodes (Code 128) than the JS/ZXing fallback.
                    experimentalFeatures: { useBarCodeDetectorIfSupported: true },
                    formatsToSupport: [
                        Html5QrcodeSupportedFormats.QR_CODE,
                        Html5QrcodeSupportedFormats.CODE_128,
                        Html5QrcodeSupportedFormats.CODE_39,
                        Html5QrcodeSupportedFormats.EAN_13,
                    ],
                });

                await this.scanner.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: this.scanBox },
                    (decoded) => this.onDecode(decoded),
                    // Per-frame decode misses fire constantly and are not errors worth showing.
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

            // Freeze the frame that decoded (before releasing the camera) and show it for
            // confirmation rather than navigating straight away.
            this.snapshot = this.snapshotFrom();
            this.captured = tag;

            // Release the camera now, or the stream can stay held on some Android browsers
            // and the next scan fails to acquire it.
            await this.stop();

            this.confirming = true;
        },

        /** Confirm the frozen scan — proceed to resolve the tag. */
        confirm() {
            if (! this.captured) {
                return;
            }
            window.location.href = this.urlFor(this.captured) + '?method=camera';
        },

        /** Discard the frozen scan and start the camera again. */
        async rescan() {
            this.confirming = false;
            this.captured = '';
            this.snapshot = '';
            await this.start();
        },

        describe(error) {
            const name = error?.name ?? '';
            const message = typeof error === 'string' ? error : (error?.message ?? '');

            if (name === 'NotAllowedError' || /permission/i.test(message)) {
                return 'Camera access was denied. Allow it in your browser settings, or type the tag number below.';
            }

            if (name === 'NotFoundError' || /no camera|not found/i.test(message)) {
                return 'No camera found on this device. Type the tag number below instead.';
            }

            if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost') {
                return 'The camera needs a secure connection (HTTPS). Type the tag number below instead.';
            }

            return message || 'Could not start the camera. Type the tag number below instead.';
        },

        destroy() {
            this.stop();
        },
    };
}
