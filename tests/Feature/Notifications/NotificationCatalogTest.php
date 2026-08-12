<?php

namespace Tests\Feature\Notifications;

use App\Support\NotificationCatalog;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NotificationCatalogTest extends TestCase
{
    /**
     * Every type string that a producer actually calls NotificationService::send()/sendMany()
     * with today (established by reading every call site, not the module doc's aspirational
     * event table). A producer added without a matching catalog row would otherwise silently
     * fall back to a generic label everywhere it's rendered.
     */
    public static function liveTypes(): array
    {
        return [
            ['approval.pending'],
            ['approval.approved'],
            ['approval.rejected'],
            ['approval.escalated'],
            ['audit.campaign_activated'],
            ['audit.campaign_closed'],
            ['movement.completed'],
            ['disposal.completed'],
            ['expiry_alert'],
            ['export_ready'],
            ['import_completed'],
        ];
    }

    #[DataProvider('liveTypes')]
    public function test_every_live_type_has_a_catalog_entry(string $type): void
    {
        $catalog = app(NotificationCatalog::class);

        $this->assertArrayHasKey($type, $catalog->all());
        $this->assertNotEmpty($catalog->label($type));
        $this->assertContains('database', $catalog->channels($type));
    }

    public function test_unknown_type_falls_back_instead_of_throwing(): void
    {
        $catalog = app(NotificationCatalog::class);

        $this->assertSame(['database'], $catalog->channels('some.removed.type'));
        $this->assertNotEmpty($catalog->label('some.removed.type'));
        $this->assertNull($catalog->link('some.removed.type', ['url' => 'https://example.test']));
        $this->assertSame('bell', $catalog->icon('some.removed.type'));
    }

    public function test_import_completed_error_shape_renders_without_a_missing_key_error(): void
    {
        $catalog = app(NotificationCatalog::class);

        $body = $catalog->body('import_completed', [
            'batch_id' => 5,
            'error'    => 'The uploaded file could not be read.',
        ]);

        $this->assertSame('The uploaded file could not be read.', $body);
    }

    public function test_import_completed_success_shape_renders_without_the_error_key(): void
    {
        $catalog = app(NotificationCatalog::class);

        $body = $catalog->body('import_completed', [
            'batch_id'      => 5,
            'success_count' => 8,
            'error_count'   => 2,
        ]);

        $this->assertSame('8 succeeded, 2 failed.', $body);
    }

    /**
     * expiry_alert's own payload carries a `type` key (warranty|amc) that must never be
     * confused with the notification's type string — the renderer receives the payload
     * array separately from the type, so this is really a regression guard on that
     * separation rather than a special case in the catalog itself.
     */
    public function test_expiry_alert_payload_type_key_does_not_shadow_the_notification_type(): void
    {
        $catalog = app(NotificationCatalog::class);

        $title = $catalog->title('expiry_alert', [
            'asset'          => 'Laptop #4',
            'type'           => 'warranty',
            'days_remaining' => 7,
            'expires_on'     => '2026-08-20',
        ]);

        $this->assertStringContainsString('warranty', $title);
        $this->assertSame('Laptop #4 — warranty expiring in 7 day(s)', $title);
    }

    public function test_export_ready_and_import_completed_link_via_a_different_payload_key_than_url(): void
    {
        $catalog = app(NotificationCatalog::class);

        $this->assertSame(
            'https://files.test/export.xlsx',
            $catalog->link('export_ready', ['download_url' => 'https://files.test/export.xlsx']),
        );

        // import_completed has no static 'link' payload key at all — it's a closure keyed
        // off batch_id, resolved through a named route.
        $this->assertNull($catalog->link('import_completed', ['error' => 'boom']));
    }
}
