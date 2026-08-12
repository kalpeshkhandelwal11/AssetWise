<?php

namespace Tests\Feature\Pwa;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The whole reason /sw.js is a Laravel route rather than a static file: vite-plugin-pwa
 * writes the worker into public/build/, where its scope would be pinned to /build/ and it
 * could never control the app's navigations. Serving it from the root gives scope "/" from
 * the URL path alone, with no .htaccess or nginx directive to get wrong per host.
 *
 * public/build is gitignored, so these tests create the fixture themselves when a real
 * build is absent and remove exactly what they created — deterministic either way.
 */
class ServiceWorkerRouteTest extends TestCase
{
    use RefreshDatabase;

    private bool $createdFixture = false;
    private bool $createdDirectory = false;

    protected function tearDown(): void
    {
        if ($this->createdFixture && is_file(public_path('build/sw.js'))) {
            unlink(public_path('build/sw.js'));
        }

        if ($this->createdDirectory && is_dir(public_path('build')) && ! (new \FilesystemIterator(public_path('build')))->valid()) {
            rmdir(public_path('build'));
        }

        parent::tearDown();
    }

    private function ensureWorkerExists(): void
    {
        if (is_file(public_path('build/sw.js'))) {
            return;
        }

        if (! is_dir(public_path('build'))) {
            mkdir(public_path('build'), 0755, true);
            $this->createdDirectory = true;
        }

        file_put_contents(public_path('build/sw.js'), "// test fixture\n");
        $this->createdFixture = true;
    }

    private function hideWorker(): ?string
    {
        $path = public_path('build/sw.js');

        if (! is_file($path)) {
            return null;
        }

        $backup = $path . '.testbackup';
        rename($path, $backup);

        return $backup;
    }

    public function test_service_worker_is_served_from_the_app_root_with_root_scope_allowed(): void
    {
        $this->ensureWorkerExists();

        $response = $this->get('/sw.js')->assertOk();

        $this->assertStringContainsString('application/javascript', $response->headers->get('Content-Type'));
        $this->assertSame('/', $response->headers->get('Service-Worker-Allowed'));
    }

    public function test_service_worker_is_never_cached(): void
    {
        $this->ensureWorkerExists();

        $cacheControl = $this->get('/sw.js')->assertOk()->headers->get('Cache-Control');

        // A stale worker would keep serving an old caching policy to installed clients.
        $this->assertStringContainsString('no-cache', $cacheControl);
    }

    public function test_missing_build_output_404s_cleanly_instead_of_erroring(): void
    {
        $backup = $this->hideWorker();

        try {
            $this->get('/sw.js')->assertNotFound();
        } finally {
            if ($backup) {
                rename($backup, public_path('build/sw.js'));
            }
        }
    }
}
