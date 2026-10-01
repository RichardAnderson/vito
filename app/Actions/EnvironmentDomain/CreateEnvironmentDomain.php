<?php

namespace App\Actions\EnvironmentDomain;

use App\Enums\EnvironmentDomainStatus;
use App\Enums\EnvironmentDomainType;
use App\Jobs\EnvironmentDomain\SyncEnvironmentDomainJob;
use App\Models\Environment;
use App\Models\EnvironmentDomain;
use App\ValidationRules\WildcardDomainRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateEnvironmentDomain
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function create(Environment $environment, array $input): EnvironmentDomain
    {
        $this->validate($environment, $input);

        $type = EnvironmentDomainType::from($input['type'] ?? EnvironmentDomainType::ALIAS->value);

        $domain = DB::transaction(function () use ($environment, $input, $type): EnvironmentDomain {
            $domain = new EnvironmentDomain([
                'environment_id' => $environment->id,
                'host' => $input['host'],
                'type' => $type,
                'redirect_to_domain_id' => $type === EnvironmentDomainType::REDIRECT
                    ? $input['redirect_to_domain_id']
                    : null,
                'ssl_method' => $input['ssl_method'] ?? 'letsencrypt',
                'status' => EnvironmentDomainStatus::CREATING,
            ]);
            $domain->save();

            return $domain;
        });

        DB::afterCommit(fn () => dispatch(new SyncEnvironmentDomainJob($domain))->onQueue('ssh'));

        return $domain;
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    private function validate(Environment $environment, array $input): void
    {
        Validator::make($input, [
            'host' => [
                'required',
                'string',
                'max:255',
                new WildcardDomainRule,
                Rule::unique('environment_domains', 'host')->where('environment_id', $environment->id),
            ],
            'type' => ['nullable', Rule::enum(EnvironmentDomainType::class)],
            'redirect_to_domain_id' => [
                'nullable',
                Rule::exists('environment_domains', 'id')
                    ->where('environment_id', $environment->id)
                    ->where('type', EnvironmentDomainType::ALIAS->value),
            ],
            'ssl_method' => ['nullable', Rule::in(['none', 'letsencrypt', 'custom'])],
        ])->validate();

        $type = EnvironmentDomainType::from($input['type'] ?? EnvironmentDomainType::ALIAS->value);

        if ($type === EnvironmentDomainType::REDIRECT && ($input['redirect_to_domain_id'] ?? null) === null) {
            throw ValidationException::withMessages([
                'redirect_to_domain_id' => 'Choose the alias this domain redirects to.',
            ]);
        }

        app(AssertHostIsAvailable::class)->assert($environment, (string) $input['host'], 'host');
    }
}
