<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Str;

/**
 * Read-only wrapper over config/notifications.php. Views and NotificationService read
 * through here rather than touching config() directly, so the "unknown type must degrade,
 * not crash" fallback lives in exactly one place.
 */
class NotificationCatalog
{
    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        return config('notifications.types', []);
    }

    public function label(string $type): string
    {
        return $this->attr($type, 'label') ?? Str::headline($type);
    }

    public function icon(string $type): string
    {
        return $this->attr($type, 'icon') ?? config('notifications.fallback.icon', 'bell');
    }

    public function title(string $type, array $data): string
    {
        return $this->resolve($this->attr($type, 'title') ?? $this->label($type), $data);
    }

    public function body(string $type, array $data): string
    {
        return $this->resolve($this->attr($type, 'body') ?? '', $data);
    }

    /** @return list<string> */
    public function channels(string $type): array
    {
        return $this->attr($type, 'channels') ?? config('notifications.fallback.channels', ['database']);
    }

    public function link(string $type, array $data): ?string
    {
        $link = $this->attr($type, 'link');

        if ($link === null) {
            return null;
        }

        if ($link instanceof Closure) {
            return $link($data);
        }

        return $data[$link] ?? null;
    }

    private function attr(string $type, string $key): mixed
    {
        return $this->all()[$type][$key] ?? null;
    }

    private function resolve(string|Closure $template, array $data): string
    {
        if ($template instanceof Closure) {
            return $template($data);
        }

        return (string) preg_replace_callback(
            '/:([a-zA-Z_][a-zA-Z0-9_]*)/',
            fn (array $m) => (string) ($data[$m[1]] ?? ''),
            $template,
        );
    }
}
