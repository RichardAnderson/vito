<?php

namespace App\Http\Controllers;

use App\Actions\Network\AddServerToNetwork;
use App\Actions\Network\CreatePrivateNetwork;
use App\Actions\Network\DeletePrivateNetwork;
use App\Actions\Network\RemoveServerFromNetwork;
use App\Actions\Network\UpdatePrivateNetwork;
use App\Http\Resources\PrivateNetworkResource;
use App\Models\PrivateNetwork;
use App\Models\Server;
use App\Support\Cidr;
use App\Tables\PrivateNetworkMemberTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Put;

#[Prefix('networks')]
#[Middleware(['auth', 'has-project'])]
class PrivateNetworkController extends Controller
{
    #[Get('/', name: 'networks')]
    public function index(): Response
    {
        $this->authorize('viewAny', [PrivateNetwork::class, user()->currentProject]);

        return $this->renderIndex(null);
    }

    #[Post('/', name: 'networks.store')]
    public function store(Request $request): RedirectResponse
    {
        $user = user();

        $this->authorize('create', [PrivateNetwork::class, $user->currentProject]);

        $network = app(CreatePrivateNetwork::class)->create($user->currentProject, $request->all());

        return redirect()->route('networks.show', $network->id);
    }

    #[Get('/{network}', name: 'networks.show')]
    public function show(PrivateNetwork $network): Response
    {
        $this->scoped($network);
        $this->authorize('view', $network);

        return $this->renderIndex($network);
    }

    #[Put('/{network}', name: 'networks.update')]
    public function update(Request $request, PrivateNetwork $network): RedirectResponse
    {
        $this->scoped($network);
        $this->authorize('update', $network);

        app(UpdatePrivateNetwork::class)->update($network, $request->all());

        return back()->with('success', 'Changes saved!');
    }

    #[Delete('/{network}', name: 'networks.destroy')]
    public function destroy(PrivateNetwork $network): RedirectResponse
    {
        $this->scoped($network);
        $this->authorize('delete', $network);

        app(DeletePrivateNetwork::class)->delete($network);

        return redirect()->route('networks')->with('success', 'Private network deleted!');
    }

    #[Post('/{network}/servers', name: 'networks.servers.attach')]
    public function attach(Request $request, PrivateNetwork $network): RedirectResponse
    {
        $this->scoped($network);
        $this->authorize('update', $network);

        app(AddServerToNetwork::class)->add($network, $request->all());

        return back()->with('info', 'Server is joining the private network.');
    }

    #[Delete('/{network}/servers/{server}', name: 'networks.servers.detach')]
    public function detach(PrivateNetwork $network, Server $server): RedirectResponse
    {
        $this->scoped($network);
        abort_unless($server->project_id === $network->project_id, 404);
        $this->authorize('update', $network);

        app(RemoveServerFromNetwork::class)->remove($network, $server);

        return back()->with('info', 'Server is leaving the private network.');
    }

    private function renderIndex(?PrivateNetwork $selected): Response
    {
        $networks = user()->currentProject->privateNetworks()->withCount('members')->latest()->get();

        return Inertia::render('networks/index', [
            'networks' => PrivateNetworkResource::collection($networks),
            'selectedId' => $selected?->id,
            'members' => $selected
                ? PrivateNetworkMemberTable::make($selected->members())->simplePaginate()
                : null,
            'availableServers' => $selected ? $this->availableServers($selected) : [],
            'suggestedSubnet' => Cidr::suggestSubnet($networks->pluck('subnet')->all()),
        ]);
    }

    private function scoped(PrivateNetwork $network): void
    {
        abort_unless($network->project_id === user()->current_project_id, 404);
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function availableServers(PrivateNetwork $network): array
    {
        $memberIds = $network->members()->pluck('server_id')->all();

        return $network->project->servers()
            ->whereNotIn('id', $memberIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Server $server): array => ['id' => $server->id, 'name' => $server->name])
            ->all();
    }
}
