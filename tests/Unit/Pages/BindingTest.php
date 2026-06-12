<?php

namespace Tests\Unit\Pages;

use App\Models\Server;
use App\Models\Site;
use App\Pages\Areas\ServerArea;
use App\Pages\Areas\SiteArea;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class BindingTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_root_by_id(): void
    {
        $models = app(ServerArea::class)->resolve(['server' => $this->server->id]);

        $this->assertTrue($models['server']->is($this->server));
    }

    public function test_accepts_already_bound_model_instance(): void
    {
        $models = app(ServerArea::class)->resolve(['server' => $this->server]);

        $this->assertTrue($models['server']->is($this->server));
    }

    public function test_resolves_child_through_parent(): void
    {
        $models = app(SiteArea::class)->resolve([
            'server' => $this->server->id,
            'site' => $this->site->id,
        ]);

        $this->assertTrue($models['site']->is($this->site));
        $this->assertTrue($models['server']->is($this->server));
    }

    public function test_child_belonging_to_another_parent_404s(): void
    {
        $otherServer = Server::factory()->create(['project_id' => $this->server->project_id]);
        $otherSite = Site::factory()->create(['server_id' => $otherServer->id]);

        $this->expectException(NotFoundHttpException::class);

        app(SiteArea::class)->resolve([
            'server' => $this->server->id,
            'site' => $otherSite->id,
        ]);
    }

    public function test_nonexistent_root_throws_model_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        app(ServerArea::class)->resolve(['server' => 99999999]);
    }
}
