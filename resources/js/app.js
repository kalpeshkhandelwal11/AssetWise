import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

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

window.Alpine = Alpine;

Alpine.start();
