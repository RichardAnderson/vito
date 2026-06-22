<?php

namespace Tests\Feature;

use App\Enums\DeploymentStatus;
use App\Facades\SSH;
use App\Jobs\Site\DeployJob;
use App\Models\Deployment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Vito\Plugin\Hooks\PreDeploy;
use Vito\Plugin\Hooks\ShouldDeploy;

class DeployHookTest extends TestCase
{
    use RefreshDatabase;

    public function test_should_deploy_veto_blocks_the_job_before_pre_deploy_and_ssh(): void
    {
        SSH::fake();

        $deployment = Deployment::factory()->create([
            'site_id' => $this->site->id,
            'status' => DeploymentStatus::DEPLOYING,
        ]);

        $preDeployRan = false;
        ShouldDeploy::register(fn ($site): bool => false);
        PreDeploy::register(function () use (&$preDeployRan): void {
            $preDeployRan = true;
        });

        (new DeployJob($deployment))->handle();

        $deployment->refresh();
        $this->assertSame(DeploymentStatus::FAILED, $deployment->status);
        $this->assertFalse($preDeployRan, 'A ShouldDeploy veto must short-circuit before PreDeploy and any SSH.');
    }

    public function test_no_listeners_leaves_the_deploy_untouched(): void
    {
        SSH::fake();

        $deployment = Deployment::factory()->create([
            'site_id' => $this->site->id,
            'status' => DeploymentStatus::DEPLOYING,
        ]);

        $this->assertTrue(ShouldDeploy::execute($this->site), 'With no listeners the gate proceeds.');
        $this->assertSame(DeploymentStatus::DEPLOYING, $deployment->fresh()->status);
    }
}
