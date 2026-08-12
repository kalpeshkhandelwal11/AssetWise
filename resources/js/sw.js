/**
 * AssetWise service worker (M15).
 *
 * Hand-written rather than generated: the caching policy is three rules and keeping them
 * literal makes them auditable. vite-plugin-pwa (injectManifest strategy) replaces
 * self.__WB_MANIFEST below with the build's content-hashed asset list.
 *
 * Policy — authenticated HTML is NEVER cached. Navigations go to the network and fall back
 * to the branded /offline page. A literal "network-first for pages" would leave a
 * permission-gated page in the cache for the next user of a shared warehouse device.
 * Offline data sync and offline edits are out of scope for M15.
 */

const CACHE = 'assetwise-v1';
const OFFLINE_URL = '/offline';

// Injected at build time: [{ url, revision }, …] for everything under public/build.
const PRECACHE = [OFFLINE_URL, ...(self.__WB_MANIFEST || []).map((entry) => entry.url ?? entry)];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE)
            // One bad URL would reject the whole addAll() and leave the worker uninstalled,
            // so each entry is cached independently.
            .then((cache) => Promise.allSettled(PRECACHE.map((url) => cache.add(url))))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
        return;
    }

    event.respondWith(caches.match(request).then((hit) => hit || fetch(request)));
});
