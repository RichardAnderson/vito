<?php

namespace App\Actions\FirewallRule;

use App\Enums\FirewallRuleStatus;
use App\Jobs\FirewallRule\ApplyRulesJob;
use App\Models\FirewallRule;
use App\Models\Server;
use App\ValidationRules\PortOrPortRangeRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ManageRule
{
    /**
     * @param  array<string, mixed>  $input
     * @return FirewallRule $rule
     */
    public function create(Server $server, array $input): FirewallRule
    {
        $input = $this->normalizePort($input);
        $this->validate($input);

        $rule = new FirewallRule($this->attributesFromInput($input, FirewallRuleStatus::CREATING));
        $rule->server_id = $server->id;
        $rule->save();

        $this->queueApply($rule);

        return $rule;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return FirewallRule $rule
     */
    public function update(FirewallRule $rule, array $input): FirewallRule
    {
        $input = $this->normalizePort($input);
        $this->validate($input);

        $rule->update($this->attributesFromInput($input, FirewallRuleStatus::UPDATING));

        $this->queueApply($rule);

        return $rule;
    }

    public function delete(FirewallRule $rule): void
    {
        $rule->status = FirewallRuleStatus::DELETING;
        $rule->save();

        $this->queueApply($rule);
    }

    /**
     * Create several rules and apply them in a single SSH trip. Intended for internal callers
     * (e.g. private-network membership) — `$memberId` is set server-side, never from user input.
     *
     * @param  array<int, array<string, mixed>>  $inputs
     * @return array<int, FirewallRule>
     */
    public function createMany(Server $server, array $inputs, ?int $memberId = null): array
    {
        if ($inputs === []) {
            return [];
        }

        $rules = DB::transaction(function () use ($server, $inputs, $memberId): array {
            $created = [];
            foreach ($inputs as $input) {
                $input = $this->normalizePort($input);
                $this->validate($input);

                $rule = new FirewallRule($this->attributesFromInput($input, FirewallRuleStatus::CREATING));
                $rule->server_id = $server->id;
                $rule->private_network_member_id = $memberId;
                $rule->save();

                $created[] = $rule;
            }

            return $created;
        });

        $this->queueApply($rules[0]);

        return $rules;
    }

    private function validate(array $input): void
    {
        $rules = [
            'name' => [
                'required',
                'string',
                'max:18',
            ],
            'type' => [
                'required',
                'in:allow,deny',
            ],
            'protocol' => [
                'required',
                'in:tcp,udp,any',
            ],
            'port' => [
                'required',
                new PortOrPortRangeRule,
            ],
            'source' => [
                'nullable',
                'ip',
            ],
            'mask' => [
                'nullable',
                'numeric',
                'min:1',
                'max:32',
            ],
        ];

        if (($input['protocol'] ?? null) === 'any') {
            $rules['port'] = ['nullable'];
        }

        if (isset($input['source_any']) && $input['source_any'] === false) {
            $rules['source'] = ['required', 'ip'];
            $rules['mask'] = ['required', 'numeric', 'min:1', 'max:32'];
        }

        Validator::make($input, $rules)->validate();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalizePort(array $input): array
    {
        $port = $input['port'] ?? null;
        $input['port'] = is_string($port) || is_int($port) ? (string) $port : null;

        return $input;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function attributesFromInput(array $input, FirewallRuleStatus $status): array
    {
        $sourceAny = $input['source_any'] ?? empty($input['source'] ?? null);
        $protocolAny = ($input['protocol'] ?? null) === 'any';

        return [
            'name' => $input['name'],
            'type' => $input['type'],
            'protocol' => $input['protocol'] ?? null,
            'port' => $protocolAny ? null : ($input['port'] ?? null),
            'source' => $sourceAny ? null : $input['source'],
            'mask' => $sourceAny ? null : ($input['mask'] ?? null),
            'status' => $status,
        ];
    }

    private function queueApply(FirewallRule $rule): void
    {
        dispatch(new ApplyRulesJob($rule))->onQueue('ssh');
    }
}
