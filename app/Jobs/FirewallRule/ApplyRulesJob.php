<?php

namespace App\Jobs\FirewallRule;

use App\DTOs\SocketEventDTO;
use App\Enums\FirewallRuleStatus;
use App\Events\SocketEvent;
use App\Http\Resources\FirewallRuleResource;
use App\Models\FirewallRule;
use App\Models\ServerLog;
use App\Models\Service;
use App\Services\Firewall\Firewall;
use App\Traits\UniqueQueue;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ApplyRulesJob implements ShouldQueue
{
    use Queueable;
    use UniqueQueue;

    public function __construct(protected FirewallRule $rule) {}

    public function handle(): void
    {
        $this->run("server-{$this->rule->server_id}", function () {
            $server = $this->rule->server;

            /** @var Service $service */
            $service = $server->firewall();
            /** @var Firewall $handler */
            $handler = $service->handler();
            $handler->applyRules();

            // applyRules() renders the full non-deleting rule set, so every pending rule on this
            // server is now live — finalise them all in one pass (lets a single apply settle a
            // batch of rules created together, e.g. a WireGuard membership's handshake + trust).
            $deleting = $server->firewallRules()->where('status', FirewallRuleStatus::DELETING)->get();
            foreach ($deleting as $rule) {
                $projectId = $server->project_id;
                $ruleId = $rule->id;
                $rule->delete();

                SocketEvent::dispatch(new SocketEventDTO(
                    projectId: $projectId,
                    type: 'firewall-rule.deleted',
                    data: ['id' => $ruleId],
                ));
            }

            $pending = $server->firewallRules()
                ->whereIn('status', [FirewallRuleStatus::CREATING, FirewallRuleStatus::UPDATING])
                ->get();
            foreach ($pending as $rule) {
                $rule->status = FirewallRuleStatus::READY;
                $rule->save();
                $this->broadcastRule($rule);
            }
        });
    }

    public function failed(Exception $e): void
    {
        $failedRules = $this->rule->server->firewallRules()
            ->where('status', '!=', FirewallRuleStatus::READY)
            ->get();

        $this->rule->server->firewallRules()
            ->where('status', '!=', FirewallRuleStatus::READY)
            ->update(['status' => FirewallRuleStatus::FAILED]);

        foreach ($failedRules as $rule) {
            $rule->status = FirewallRuleStatus::FAILED;
            SocketEvent::dispatch(new SocketEventDTO(
                projectId: $rule->server->project_id,
                type: 'firewall-rule.updated',
                data: new FirewallRuleResource($rule),
            ));
        }

        ServerLog::log($this->rule->server, 'apply-firewall-rules-failed', $e->getMessage());
    }

    private function broadcastRule(FirewallRule $rule): void
    {
        $rule->refresh();

        SocketEvent::dispatch(new SocketEventDTO(
            projectId: $rule->server->project_id,
            type: 'firewall-rule.updated',
            data: new FirewallRuleResource($rule),
        ));
    }
}
