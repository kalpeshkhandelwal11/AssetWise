import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import 'tom-select/dist/css/tom-select.css';
import qrScanner from './qr-scanner';
import tagScanner from './tag-scanner';
import registerSearchableSelect from './searchable-select';
import tagReplace from './tag-replace';
import registerImageCompress from './image-compress';
import filterBar from './filter-bar';

// Apply dark mode before first paint to avoid flash
(function () {
    const stored = localStorage.getItem('theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    if (stored === 'dark' || (!stored && prefersDark)) {
        document.documentElement.classList.add('dark');
    }
})();

// The sidebar submenus and the locations tree both use x-collapse; without this plugin
// Alpine logs a warning for every usage and the sections snap open with no animation.
Alpine.plugin(collapse);

// M15 camera scanner, shared by /scan and the audit verify worklist. Must be registered
// before start(); html5-qrcode itself is lazy-imported inside, not bundled here.
Alpine.data('qrScanner', qrScanner);

// Asset-create Barcode/Tag section: decode a printed tag with the phone camera and drop the
// number into the tag input (hardware scanners type into that input directly, no JS needed).
Alpine.data('tagScanner', tagScanner);

// Replace-tag form: scan (camera or hardware reader) or type a pool tag number, resolved to
// the submitted <select name="new_tag_id">.
Alpine.data('tagReplace', tagReplace);

// `x-searchable` — Tom Select behind <x-searchable-select>. Registered before start() so the
// directive is known when the first tree is walked.
registerSearchableSelect(Alpine);

// Shrink selected images to <= 2 MB before upload, on any <input type=file data-compress>.
registerImageCompress();

// Auto-applying filters behind <x-filter-bar> — apply on change / debounced typing, no button.
Alpine.data('filterBar', filterBar);

window.Alpine = Alpine;

Alpine.start();

// M15 — register the service worker from the app root so its scope is "/" and not "/build/".
// PROD-guarded: in dev the worker would intercept and break Vite's HMR requests, and
// /sw.js does not exist until "npm run build" has run.
if ('serviceWorker' in navigator && import.meta.env.PROD) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch((error) => {
            console.error('[AssetWise] Service worker registration failed', error);
        });
    });
}
