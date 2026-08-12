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

        /** Build /scan/{tag} from the template the Blade component handed us. */
        urlFor(tag) {
            return resolveUrl.replace(tagPlaceholder, encodeURIComponent(tag));
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
                const { Html5Qrcode } = await import('html5-qrcode');

                this.scanner = new Html5Qrcode(viewerId, { verbose: false });

                await this.scanner.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: { width: 240, height: 240 } },
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

            // Release the camera before navigating, or the stream can stay held on some
            // Android browsers and the next scan fails to acquire it.
            await this.stop();

            window.location.href = this.urlFor(tag) + '?method=camera';
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
