<?php

namespace Tests\Feature\Pages;

use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Pages\Schema\EvaluationContext;
use App\Pages\SiteSettings\SiteSettingsPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Proves the SiteSettings wire contract (node ids/types/display, action & data
 * references, form field names, methods, and the form-is-null-on-actionMap invariant)
 * is preserved across the framework refactor. Values that legitimately vary with the
 * model (domain, php version, urls) are excluded; only the structural/wiring fingerprint
 * is pinned, so the React renderer and legacy `site-settings.*` consumers are unaffected.
 */
class SiteSettingsWireSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private const BASELINE = __DIR__.'/fixtures/site-settings-wire.json';

    public function test_wire_fingerprint_matches_baseline(): void
    {
        // Give the site a PHP version so the php-version row renders.
        Service::query()->create([
            'server_id' => $this->server->id,
            'type' => 'php',
            'name' => 'php',
            'version' => '8.4',
            'status' => ServiceStatus::READY,
        ]);
        $this->site->php_version = '8.4';
        $this->site->save();

        $fingerprint = $this->fingerprint();

        if (! File::exists(self::BASELINE)) {
            File::ensureDirectoryExists(dirname(self::BASELINE));
            File::put(self::BASELINE, json_encode($fingerprint, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
            $this->markTestIncomplete('Baseline captured — re-run to assert.');
        }

        $this->assertSame(
            json_decode(File::get(self::BASELINE), true),
            $fingerprint,
            'SiteSettings wire fingerprint drifted from the committed baseline.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function fingerprint(): array
    {
        $page = new SiteSettingsPage;
        $models = ['site' => $this->site->refresh(), 'server' => $this->server->refresh()];
        $ctx = EvaluationContext::make($models, $this->user);

        $schema = [];
        foreach ($page->schema() as $node) {
            $serialized = $node->serialize($ctx);
            if ($serialized !== null) {
                $schema[] = $this->node($serialized);
            }
        }

        $actions = [];
        foreach ($page->allActions() as $action) {
            if (! $action->hasAuthorization()) {
                continue;
            }
            $actions[$action->id()] = [
                'method' => $action->getMethod(),
                'form_is_null' => $action->getForm() === null,
            ];
        }
        ksort($actions);

        $data = [];
        foreach ($page->allData() as $endpoint) {
            if (! $endpoint->hasAuthorization()) {
                continue;
            }
            $data[$endpoint->id()] = ['method' => $endpoint->getMethod()];
        }
        ksort($data);

        return ['schema' => $schema, 'actions' => $actions, 'data' => $data];
    }

    /**
     * Structural/wiring keys only — volatile display values are excluded.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function node(array $node): array
    {
        $fp = ['id' => $node['id'], 'type' => $node['type']];

        foreach (['display', 'action', 'sheet', 'load', 'save', 'preview', 'reset', 'readonly', 'language', 'variant', 'confirmField', 'destructive'] as $key) {
            if (array_key_exists($key, $node) && $node[$key] !== null) {
                $fp[$key] = $node[$key];
            }
        }

        foreach (['confirm', 'confirmText', 'form'] as $key) {
            if (array_key_exists($key, $node) && $node[$key] !== null) {
                $fp["has_{$key}"] = true;
            }
        }

        if (isset($node['form']) && is_array($node['form'])) {
            $fp['form_fields'] = array_map(
                fn (array $field): array => ['name' => $field['name'] ?? null, 'type' => $field['type'] ?? null],
                $node['form']['fields'] ?? [],
            );
        }

        foreach (['button', 'dialog', 'editor'] as $child) {
            if (isset($node[$child]) && is_array($node[$child])) {
                $fp[$child] = $this->node($node[$child]);
            }
        }

        if (! empty($node['children'])) {
            $fp['children'] = array_map(fn (array $child): array => $this->node($child), $node['children']);
        }

        return $fp;
    }
}
