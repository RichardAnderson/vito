<?php

namespace Tests\Unit\Plugins;

use App\Plugins\Hooks\HookRegistry;
use Tests\TestCase;
use Vito\Plugin\Contracts\HookRegistry as HookRegistryContract;
use Vito\Plugin\Hooks\ActionHook;
use Vito\Plugin\Hooks\Decision;
use Vito\Plugin\Hooks\DecisionHook;

class TestAction extends ActionHook {}

class TestDecision extends DecisionHook
{
    protected bool $default = true;
}

class TestDecisionFailClosed extends DecisionHook
{
    protected bool $default = true;

    protected ?bool $safeValue = false;
}

class TestDecisionDefaultFalse extends DecisionHook
{
    protected bool $default = false;
}

class HookTest extends TestCase
{
    public function test_action_hook_runs_all_listeners(): void
    {
        $count = 0;
        TestAction::register(function () use (&$count): void {
            $count++;
        });
        TestAction::register(function () use (&$count): void {
            $count++;
        });

        TestAction::execute();

        $this->assertSame(2, $count);
    }

    public function test_action_hook_isolates_a_throwing_listener(): void
    {
        $ran = false;
        TestAction::register(function (): void {
            throw new \RuntimeException('boom');
        });
        TestAction::register(function () use (&$ran): void {
            $ran = true;
        });

        TestAction::execute();

        $this->assertTrue($ran, 'A throwing listener must not stop later listeners.');
    }

    public function test_decision_defaults_to_proceed_with_no_listeners(): void
    {
        $this->assertTrue(TestDecision::execute());
    }

    public function test_decision_any_false_vote_wins(): void
    {
        TestDecision::register(fn (): bool => true);
        TestDecision::register(fn (): ?bool => null);
        TestDecision::register(fn (): bool => false);

        $this->assertFalse(TestDecision::execute());
    }

    public function test_decision_is_order_independent(): void
    {
        TestDecision::register(fn (): bool => false);
        TestDecision::register(fn (): bool => true);

        $this->assertFalse(TestDecision::execute());
    }

    public function test_decision_captures_deny_reason(): void
    {
        TestDecision::register(fn () => Decision::deny('a backup is still running'));

        $result = TestDecision::evaluate();

        $this->assertFalse($result->allowed);
        $this->assertSame('a backup is still running', $result->reason);
    }

    public function test_decision_fails_open_when_a_listener_throws(): void
    {
        TestDecision::register(function (): bool {
            throw new \RuntimeException('boom');
        });

        $this->assertTrue(TestDecision::execute(), 'A broken predicate abstains; the gate stays at its default.');
    }

    public function test_decision_fail_closed_forces_safe_value_on_error(): void
    {
        TestDecisionFailClosed::register(function (): bool {
            throw new \RuntimeException('boom');
        });

        $this->assertFalse(TestDecisionFailClosed::execute(), 'A fail-closed gate must block when a listener errors.');
    }

    public function test_decision_default_false_any_true_wins(): void
    {
        TestDecisionDefaultFalse::register(fn (): bool => true);

        $this->assertTrue(TestDecisionDefaultFalse::execute());
    }

    public function test_registry_survives_scope_reset_so_queued_jobs_keep_listeners(): void
    {
        TestDecision::register(fn (): bool => false);
        $this->assertFalse(TestDecision::execute());

        // The queue worker calls forgetScopedInstances() before every job. The hook registry is a
        // singleton precisely so plugin listeners survive that reset — otherwise a queued DeployJob
        // firing ShouldDeploy would see an empty registry and never veto.
        app()->forgetScopedInstances();

        $this->assertFalse(TestDecision::execute(), 'Hook listeners must survive the worker scope reset.');
    }

    public function test_registry_flush_then_reregister_is_stable(): void
    {
        $registry = app(HookRegistry::class);
        $this->assertInstanceOf(HookRegistryContract::class, $registry);

        TestDecision::register(fn (): bool => false);
        $this->assertCount(1, $registry->listeners(TestDecision::class));

        $registry->flush();
        $this->assertCount(0, $registry->listeners(TestDecision::class));
        $this->assertTrue(TestDecision::execute(), 'After a flush the gate is back to default until re-registered.');

        TestDecision::register(fn (): bool => false);
        $this->assertCount(1, $registry->listeners(TestDecision::class));
        $this->assertFalse(TestDecision::execute());
    }
}
