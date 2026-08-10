import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

// The sidebar submenus and the locations tree both use x-collapse; without this plugin
// Alpine logs a warning for every usage and the sections snap open with no animation.
Alpine.plugin(collapse);

window.Alpine = Alpine;

Alpine.start();
