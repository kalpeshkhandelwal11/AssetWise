{{--
    M15 — "Install AssetWise" banner. Included once from layouts/app.blade.php.

    Chrome/Edge fire beforeinstallprompt and hand us a deferred prompt we can trigger on tap.
    iOS Safari never fires it, so when we detect iOS-not-already-installed we show the
    "Share -> Add to Home Screen" hint instead — otherwise iPhone users, who are exactly the
    field users this module is for, would never see any install affordance at all.

    Dismissal is remembered in localStorage so the bar does not nag on every page load.
--}}
<div
    x-data="{
        deferred: null,
        show: false,
        iosHint: false,
        init() {
            const standalone = window.matchMedia('(display-mode: standalone)').matches
                || window.navigator.standalone === true;
            const dismissed = localStorage.getItem('pwa-install-dismissed') === '1';

            if (standalone || dismissed) {
                return;
            }

            window.addEventListener('beforeinstallprompt', (event) => {
                event.preventDefault();
                this.deferred = event;
                this.show = true;
            });

            const isIos = /iphone|ipad|ipod/i.test(window.navigator.userAgent);
            const isSafari = /safari/i.test(window.navigator.userAgent) && !/crios|fxios/i.test(window.navigator.userAgent);

            if (isIos && isSafari) {
                this.iosHint = true;
                this.show = true;
            }
        },
        async install() {
            if (!this.deferred) {
                return;
            }
            this.deferred.prompt();
            await this.deferred.userChoice;
            this.deferred = null;
            this.show = false;
        },
        dismiss() {
            this.show = false;
            localStorage.setItem('pwa-install-dismissed', '1');
        },
    }"
    x-show="show"
    x-cloak
    x-transition
    class="fixed bottom-0 inset-x-0 z-50 px-4 pb-4 sm:px-6 sm:pb-6 print:hidden">

    <div class="mx-auto max-w-xl flex items-center gap-3 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-xl px-4 py-3">
        <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-indigo-600 flex items-center justify-center">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/>
            </svg>
        </div>

        <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Install {{ config('app.name', 'AssetWise') }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400" x-show="!iosHint">
                Add it to your home screen for full-screen access and faster scanning.
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400" x-show="iosHint" x-cloak>
                Tap <span class="font-medium">Share</span>, then <span class="font-medium">Add to Home Screen</span>.
            </p>
        </div>

        <button type="button" @click="install()" x-show="!iosHint"
                class="flex-shrink-0 px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium transition-colors">
            Install
        </button>

        <button type="button" @click="dismiss()" title="Dismiss"
                class="flex-shrink-0 p-1.5 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
</div>
