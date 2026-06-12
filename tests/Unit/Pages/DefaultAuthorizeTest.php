<?php

namespace Tests\Unit\Pages;

use App\Models\User;
use App\Pages\AbstractArea;
use App\Pages\AbstractPage;
use App\Pages\Areas\ServerArea;
use App\Pages\PageAction;
use Closure;
use Tests\TestCase;

class DefaultAuthorizeTest extends TestCase
{
    private function makePage(?Closure $default): AbstractPage
    {
        return new class($default) extends AbstractPage
        {
            public function __construct(private ?Closure $default) {}

            public static function id(): string
            {
                return 'fixture';
            }

            public function area(): AbstractArea
            {
                return app(ServerArea::class);
            }

            public function slug(): string
            {
                return 'fixture';
            }

            public function render(array $models): array
            {
                return [];
            }

            public function actions(): array
            {
                return [PageAction::make('no-gate')->run(fn () => null)];
            }

            public function defaultAuthorize(): ?Closure
            {
                return $this->default;
            }
        };
    }

    public function test_action_without_gate_inherits_the_page_default(): void
    {
        $page = $this->makePage(fn (User $user): bool => true);

        $action = $page->allActions()[0];

        $this->assertTrue($action->hasAuthorization());
    }

    public function test_action_without_gate_and_no_default_stays_unauthorized(): void
    {
        // RegisterPageRoutes skips such an action, so it gets no route — fail closed.
        $page = $this->makePage(null);

        $action = $page->allActions()[0];

        $this->assertFalse($action->hasAuthorization());
    }

    public function test_explicit_gate_is_not_overridden_by_default(): void
    {
        $page = new class extends AbstractPage
        {
            public static function id(): string
            {
                return 'fixture2';
            }

            public function area(): AbstractArea
            {
                return app(ServerArea::class);
            }

            public function slug(): string
            {
                return 'fixture2';
            }

            public function render(array $models): array
            {
                return [];
            }

            public function actions(): array
            {
                return [
                    PageAction::make('explicit')
                        ->authorize(fn (User $user): bool => false)
                        ->run(fn () => null),
                ];
            }

            public function defaultAuthorize(): ?Closure
            {
                return fn (User $user): bool => true;
            }
        };

        $action = $page->allActions()[0];

        // The explicit (false) gate must win over the default (true).
        $this->assertFalse($action->isAuthorized(new User, []));
    }
}
