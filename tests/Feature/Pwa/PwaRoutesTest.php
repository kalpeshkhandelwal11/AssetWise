<?php

namespace Tests\Feature\Pwa;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_is_served_with_the_manifest_content_type(): void
    {
        $this->get('/manifest.webmanifest')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json');
    }

    public function test_manifest_describes_an_installable_standalone_app(): void
    {
        $response = $this->get('/manifest.webmanifest')->assertOk();

        $manifest = $response->json();

        $this->assertNotEmpty($manifest['name']);
        $this->assertNotEmpty($manifest['short_name']);
        // UF-18 step 3 — the installed app lands on the dashboard, not the marketing root.
        $this->assertSame('/dashboard', $manifest['start_url']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertNotEmpty($manifest['icons']);
    }

    public function test_manifest_advertises_the_icon_sizes_chrome_requires_for_installability(): void
    {
        $icons = $this->get('/manifest.webmanifest')->assertOk()->json('icons');

        $sizes = array_column($icons, 'sizes');
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);

        // A maskable icon is what keeps the Android launcher from letterboxing the glyph.
        $this->assertContains('maskable', array_column($icons, 'purpose'));
    }

    public function test_every_manifest_icon_file_actually_exists(): void
    {
        $icons = $this->get('/manifest.webmanifest')->assertOk()->json('icons');

        foreach ($icons as $icon) {
            $this->assertFileExists(
                public_path(ltrim($icon['src'], '/')),
                "Manifest references a missing icon: {$icon['src']}"
            );
        }

        $this->assertFileExists(public_path(ltrim(config('pwa.apple_touch_icon'), '/')));
    }

    public function test_offline_page_renders_the_branded_fallback(): void
    {
        $this->get('/offline')
            ->assertOk()
            ->assertSee("You're offline", false)
            ->assertSee('Try again');
    }

    /**
     * All three endpoints must resolve without a session: the worker and manifest are
     * fetched from the login screen, and an offline navigation that redirected to /login
     * would hit a page that is itself unreachable.
     */
    public function test_pwa_routes_are_reachable_without_authentication(): void
    {
        $this->assertGuest();

        $this->get('/manifest.webmanifest')->assertOk();
        $this->get('/offline')->assertOk();

        // /sw.js is 200 or a clean 404 depending on whether "npm run build" has run — the
        // point here is only that it is never bounced to the login screen.
        $this->assertNotEquals(302, $this->get('/sw.js')->getStatusCode());
    }

    public function test_offline_page_does_not_require_a_user_and_omits_the_app_shell(): void
    {
        // The app layout dereferences auth()->user() and renders the sidebar; the offline
        // page is precached with no session, so it must stay standalone.
        $this->get('/offline')
            ->assertOk()
            ->assertDontSee('Sign out')
            ->assertDontSee('Notifications');
    }
}
