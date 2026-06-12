<?php

namespace Tests\Unit\Pages;

use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use App\Pages\Schema\EvaluatesClosures;
use App\Pages\Schema\EvaluationContext;
use Illuminate\Http\Request;
use RuntimeException;
use Tests\TestCase;

class EvaluatesClosuresTest extends TestCase
{
    private object $evaluator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->evaluator = new class
        {
            use EvaluatesClosures;

            public function call(mixed $value, EvaluationContext $ctx, array $named = []): mixed
            {
                return $this->evaluate($value, $ctx, $named);
            }
        };
    }

    public function test_non_closure_is_returned_as_is(): void
    {
        $ctx = EvaluationContext::make();

        $this->assertSame('plain', $this->evaluator->call('plain', $ctx));
        $this->assertSame(42, $this->evaluator->call(42, $ctx));
        $this->assertNull($this->evaluator->call(null, $ctx));
    }

    public function test_zero_argument_closure_is_called(): void
    {
        $ctx = EvaluationContext::make();

        $this->assertSame('ran', $this->evaluator->call(fn (): string => 'ran', $ctx));
    }

    public function test_resolves_models_by_name(): void
    {
        $site = new Site(['domain' => 'example.test']);
        $server = new Server(['name' => 'web-1']);
        $ctx = EvaluationContext::make(['site' => $site, 'server' => $server]);

        $result = $this->evaluator->call(fn ($site, $server): array => [$site, $server], $ctx);

        $this->assertSame([$site, $server], $result);
    }

    public function test_resolves_models_by_type(): void
    {
        $site = new Site(['domain' => 'example.test']);
        $server = new Server(['name' => 'web-1']);
        $ctx = EvaluationContext::make(['site' => $site, 'server' => $server]);

        $result = $this->evaluator->call(fn (Server $s): Server => $s, $ctx);

        $this->assertSame($server, $result);
    }

    public function test_resolves_input_models_request_user_record(): void
    {
        $site = new Site(['domain' => 'example.test']);
        $user = new User(['email' => 'a@b.c']);
        $request = Request::create('/');
        $record = new Server(['name' => 'row']);
        $ctx = new EvaluationContext(['site' => $site], $user, $request, $record, ['k' => 'v']);

        $this->assertSame(['k' => 'v'], $this->evaluator->call(fn (array $input): array => $input, $ctx));
        $this->assertSame(['site' => $site], $this->evaluator->call(fn (array $models): array => $models, $ctx));
        $this->assertSame($request, $this->evaluator->call(fn (Request $request): Request => $request, $ctx));
        $this->assertSame($user, $this->evaluator->call(fn (User $user): User => $user, $ctx));
        $this->assertSame($record, $this->evaluator->call(fn ($record) => $record, $ctx));
    }

    public function test_legacy_models_input_signature_resolves(): void
    {
        $site = new Site(['domain' => 'example.test']);
        $ctx = (new EvaluationContext(['site' => $site]))->withInput(['x' => 1]);

        $result = $this->evaluator->call(
            fn (array $models, array $input): array => [$models['site'], $input],
            $ctx,
        );

        $this->assertSame([$site, ['x' => 1]], $result);
    }

    public function test_named_overrides_take_priority(): void
    {
        $ctx = EvaluationContext::make();

        $result = $this->evaluator->call(fn ($value) => $value, $ctx, ['value' => 'override']);

        $this->assertSame('override', $result);
    }

    public function test_nullable_unresolved_parameter_is_null(): void
    {
        $ctx = EvaluationContext::make();

        $this->assertNull($this->evaluator->call(fn (?Site $site) => $site, $ctx));
    }

    public function test_unresolvable_parameter_throws(): void
    {
        $ctx = EvaluationContext::make();

        $this->expectException(RuntimeException::class);

        $this->evaluator->call(fn (Site $site) => $site, $ctx);
    }
}
