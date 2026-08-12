<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * M15 — the three public PWA endpoints. All are deliberately outside the auth group: a
 * logged-out or offline user must still be able to fetch the worker, the manifest and the
 * offline fallback, otherwise the install prompt never appears on the login screen and an
 * offline navigation redirects to a login page that is itself unreachable.
 */
class PwaController extends Controller
{
    /**
     * GET /sw.js — vite-plugin-pwa writes the worker into public/build/, which would pin its
     * scope to /build/. Serving the same file from the app root gives it scope "/" from the
     * URL path alone: no .htaccess or nginx directive, identical behaviour on shared hosting,
     * and feature-testable. 404s cleanly before the first "npm run build".
     */
    public function serviceWorker(): BinaryFileResponse|Response
    {
        $path = public_path('build/sw.js');

        if (! is_file($path)) {
            return response('Service worker not built. Run "npm run build".', 404, [
                'Content-Type' => 'text/plain',
            ]);
        }

        return response()->file($path, [
            'Content-Type'          => 'application/javascript',
            'Service-Worker-Allowed' => '/',
            // The worker itself must never be served stale, or a caching-policy fix could
            // take days to reach installed clients.
            'Cache-Control'         => 'no-cache, must-revalidate',
        ]);
    }

    /** GET /manifest.webmanifest — built from config/pwa.php. */
    public function manifest(): JsonResponse
    {
        return response()->json([
            'name'             => config('pwa.name'),
            'short_name'       => config('pwa.short_name'),
            'description'      => config('pwa.description'),
            'start_url'        => config('pwa.start_url'),
            'scope'            => config('pwa.scope'),
            'display'          => config('pwa.display'),
            'orientation'      => config('pwa.orientation'),
            'theme_color'      => config('pwa.theme_color'),
            'background_color' => config('pwa.background_color'),
            'icons'            => config('pwa.icons', []),
        ], 200, [
            'Content-Type'  => 'application/manifest+json',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /** GET /offline — the service worker's navigation fallback. */
    public function offline(): View
    {
        return view('pwa.offline');
    }
}
