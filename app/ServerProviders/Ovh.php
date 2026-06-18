<?php

namespace App\ServerProviders;

use App\Enums\OperatingSystem;
use App\Exceptions\CouldNotConnectToProvider;
use App\Exceptions\ServerProviderError;
use App\Facades\Notifier;
use App\Notifications\FailedToDeleteServerFromProvider;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class Ovh extends AbstractProvider
{
    /**
     * @var array<string, string>
     */
    private const array ENDPOINTS = [
        'ovh-eu' => 'https://eu.api.ovh.com/1.0',
        'ovh-ca' => 'https://ca.api.ovh.com/1.0',
        'ovh-us' => 'https://api.us.ovhcloud.com/1.0',
    ];

    private ?int $timeDelta = null;

    public static function id(): string
    {
        return 'ovh';
    }

    public function createRules(array $input): array
    {
        return [
            'plan' => 'required',
            'region' => 'required',
        ];
    }

    public function credentialValidationRules(array $input): array
    {
        return [
            'endpoint' => ['required', Rule::in(array_keys(self::ENDPOINTS))],
            'application_key' => ['required'],
            'application_secret' => ['required'],
            'consumer_key' => ['required'],
            'project_id' => ['required'],
        ];
    }

    public function credentialData(array $input): array
    {
        return [
            'endpoint' => $input['endpoint'],
            'application_key' => $input['application_key'],
            'application_secret' => $input['application_secret'],
            'consumer_key' => $input['consumer_key'],
            'project_id' => $input['project_id'],
        ];
    }

    public function data(array $input): array
    {
        return [
            'plan' => $input['plan'],
            'region' => $input['region'],
        ];
    }

    /**
     * @throws CouldNotConnectToProvider
     */
    public function connect(array $credentials): bool
    {
        try {
            $response = $this->request($credentials, 'GET', '/auth/currentCredential');
        } catch (Exception) {
            throw new CouldNotConnectToProvider('OVHcloud');
        }

        if (! $response->ok()) {
            throw new CouldNotConnectToProvider('OVHcloud');
        }

        return true;
    }

    /**
     * @return array<string, array{label: string, available: bool}>
     */
    public function plans(?string $region): array
    {
        try {
            $credentials = $this->serverProvider->credentials;

            $response = $this->request(
                $credentials,
                'GET',
                '/cloud/project/'.$credentials['project_id'].'/flavor',
            );

            if (! $response->successful() || ! is_array($response->json())) {
                return [];
            }

            $pricing = $this->pricing($credentials);

            return collect($response->json())
                ->filter(fn (mixed $flavor): bool => is_array($flavor)
                    && ($flavor['osType'] ?? null) === 'linux'
                    && ($flavor['region'] ?? null) === $region)
                ->mapWithKeys(function (array $flavor) use ($pricing): array {
                    $label = __('server_providers.plan', [
                        'name' => $flavor['name'],
                        'cpu' => $flavor['vcpus'],
                        'memory' => $flavor['ram'],
                        'disk' => $flavor['disk'],
                    ]);

                    $price = $this->monthlyPrice($flavor, $pricing['prices']);
                    if ($price !== null) {
                        $label .= ' ('.number_format($price, 2).' '.$pricing['currency'].'/mo)';
                    }

                    return [
                        $flavor['id'] => [
                            'label' => $label,
                            'available' => true,
                        ],
                    ];
                })
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Resolve the monthly price for a flavor from the catalog price map, using
     * the monthly plan code when present and otherwise estimating from the
     * hourly rate (~730 hours/month).
     *
     * @param  array<string, mixed>  $flavor
     * @param  array<string, float>  $prices
     */
    private function monthlyPrice(array $flavor, array $prices): ?float
    {
        $hourlyCode = $flavor['planCodes']['hourly'] ?? null;
        if (is_string($hourlyCode) && isset($prices[$hourlyCode])) {
            return $prices[$hourlyCode] * 730;
        }

        $monthlyCode = $flavor['planCodes']['monthly'] ?? null;
        if (is_string($monthlyCode) && isset($prices[$monthlyCode])) {
            return $prices[$monthlyCode];
        }

        return null;
    }

    /**
     * Fetch OVHcloud Public Cloud pricing for the account's subsidiary. Prices
     * live in the public order catalog keyed by plan code (in ucents); the
     * subsidiary and currency come from /me. Best-effort: returns empty pricing
     * when the credential lacks /me access or the catalog is unavailable.
     *
     * @param  array<string, mixed>  $credentials
     * @return array{currency: string, prices: array<string, float>}
     */
    private function pricing(array $credentials): array
    {
        $empty = ['currency' => '', 'prices' => []];

        try {
            $endpoint = $credentials['endpoint'] ?? 'ovh-eu';
            $base = self::ENDPOINTS[$endpoint] ?? self::ENDPOINTS['ovh-eu'];
            $subsidiary = $this->subsidiary($credentials, $endpoint);

            $cacheKey = 'ovh-catalog-prices:'.$endpoint.':'.$subsidiary;
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }

            $catalog = Http::get($base.'/order/catalog/public/cloud', ['ovhSubsidiary' => $subsidiary])->json();
            if (! is_array($catalog) || ! is_array($catalog['addons'] ?? null)) {
                return $empty;
            }

            $prices = [];
            foreach ($catalog['addons'] as $addon) {
                if (is_array($addon) && isset($addon['planCode'], $addon['pricings'][0]['price'])) {
                    $prices[$addon['planCode']] = $addon['pricings'][0]['price'] / 100000000;
                }
            }

            $currency = $catalog['locale']['currencyCode'] ?? '';
            $result = ['currency' => is_string($currency) ? $currency : '', 'prices' => $prices];

            Cache::put($cacheKey, $result, now()->addHours(12));

            return $result;
        } catch (\Throwable) {
            return $empty;
        }
    }

    /**
     * Resolve the account's OVHcloud subsidiary (which drives catalog currency
     * and pricing). Uses /me when the credential grants it, otherwise falls back
     * to a sensible default for the configured endpoint.
     *
     * @param  array<string, mixed>  $credentials
     */
    private function subsidiary(array $credentials, string $endpoint): string
    {
        return Cache::remember(
            'ovh-subsidiary:'.md5(($credentials['consumer_key'] ?? '').$endpoint),
            now()->addHours(12),
            function () use ($credentials, $endpoint): string {
                try {
                    $me = $this->request($credentials, 'GET', '/me');
                    $subsidiary = $me->successful() ? $me->json('ovhSubsidiary') : null;
                    if (is_string($subsidiary) && $subsidiary !== '') {
                        return $subsidiary;
                    }
                } catch (\Throwable) {
                    // fall through to the endpoint default
                }

                return match ($endpoint) {
                    'ovh-ca' => 'CA',
                    'ovh-us' => 'US',
                    default => 'FR',
                };
            }
        );
    }

    public function regions(): array
    {
        try {
            $credentials = $this->serverProvider->credentials;

            $response = $this->request(
                $credentials,
                'GET',
                '/cloud/project/'.$credentials['project_id'].'/flavor',
            );

            if (! $response->successful() || ! is_array($response->json())) {
                return [];
            }

            return collect($response->json())
                ->filter(fn (mixed $flavor): bool => is_array($flavor)
                    && ($flavor['osType'] ?? null) === 'linux'
                    && is_string($flavor['region'] ?? null))
                ->pluck('region')
                ->unique()
                ->sort()
                ->mapWithKeys(fn (string $region): array => [$region => $this->regionLabel($region)])
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Map an OVHcloud region code (e.g. "GRA11", "BHS5", "EU-WEST-PAR") to a
     * human-friendly location, falling back to the raw code when unknown.
     */
    private function regionLabel(string $region): string
    {
        $locations = config('serverproviders.ovh.regions', []);

        $key = (string) preg_replace('/-[A-Z]$/', '', $region);
        $key = rtrim((string) preg_replace('/\d+$/', '', $key), '-');

        $location = $locations[$region] ?? $locations[$key] ?? null;

        return $location !== null ? $location.' ('.$region.')' : $region;
    }

    /**
     * @throws ServerProviderError
     */
    public function create(): void
    {
        $this->generateKeyPair();

        $credentials = $this->server->serverProvider->credentials;
        $project = $credentials['project_id'];
        $region = $this->server->provider_data['region'];

        $sshKey = $this->request($credentials, 'POST', '/cloud/project/'.$project.'/sshkey', [
            'name' => 'vito-'.str($this->server->name)->slug().'-'.$this->server->id,
            'publicKey' => $this->server->sshKey()['public_key'],
        ]);

        if (! in_array($sshKey->status(), [200, 201], true)) {
            $this->providerError($sshKey);
        }

        $this->server->jsonUpdate('provider_data', 'ssh_key_id', $sshKey->json('id'));

        $create = $this->request($credentials, 'POST', '/cloud/project/'.$project.'/instance', [
            'name' => str($this->server->name)->slug(),
            'flavorId' => $this->server->provider_data['plan'],
            'imageId' => $this->getImageId($credentials, $project, $region, $this->server->os),
            'region' => $region,
            'sshKeyId' => $sshKey->json('id'),
            'monthlyBilling' => false,
        ]);

        if (! in_array($create->status(), [200, 201], true)) {
            $this->providerError($create);
        }

        $this->server->jsonUpdate('provider_data', 'instance_id', $create->json('id'), false);
        $this->server->save();
    }

    public function isRunning(): bool
    {
        try {
            $credentials = $this->server->serverProvider->credentials;

            $status = $this->request(
                $credentials,
                'GET',
                '/cloud/project/'.$credentials['project_id'].'/instance/'.$this->server->provider_data['instance_id'],
            );
        } catch (Exception) {
            return false;
        }

        if (! $status->ok()) {
            return false;
        }

        if (! $this->server->ip) {
            foreach ($status->json('ipAddresses') ?? [] as $ip) {
                if (($ip['version'] ?? null) !== 4) {
                    continue;
                }

                if (($ip['type'] ?? null) === 'public') {
                    $this->server->ip = $ip['ip'];
                } else {
                    $this->server->local_ip = $ip['ip'];
                }
            }

            if ($this->server->ip) {
                $this->server->save();
            }
        }

        if (! $this->server->ip) {
            return false;
        }

        return $status->json('status') === 'ACTIVE';
    }

    public function delete(): void
    {
        $credentials = $this->server->serverProvider->credentials;
        $project = $credentials['project_id'] ?? null;

        if ($project === null) {
            return;
        }

        if (isset($this->server->provider_data['instance_id'])) {
            $delete = $this->request(
                $credentials,
                'DELETE',
                '/cloud/project/'.$project.'/instance/'.$this->server->provider_data['instance_id'],
            );

            if (! $delete->ok()) {
                Notifier::send($this->server, new FailedToDeleteServerFromProvider($this->server));
            }
        }

        if (isset($this->server->provider_data['ssh_key_id'])) {
            $this->request(
                $credentials,
                'DELETE',
                '/cloud/project/'.$project.'/sshkey/'.$this->server->provider_data['ssh_key_id'],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $credentials
     *
     * @throws ServerProviderError
     */
    private function getImageId(array $credentials, string $project, string $region, OperatingSystem $os): string
    {
        $imageName = config('serverproviders.ovh.images')[$os->value] ?? null;

        if ($imageName === null) {
            throw new ServerProviderError('Unsupported operating system for OVHcloud.');
        }

        $response = $this->request(
            $credentials,
            'GET',
            '/cloud/project/'.$project.'/image',
        );

        $images = collect(is_array($response->json()) ? $response->json() : [])
            ->filter(fn (mixed $image): bool => is_array($image)
                && ($image['region'] ?? null) === $region);

        $image = $images->first(fn (array $image): bool => $image['name'] === $imageName)
            ?? $images->first(fn (array $image): bool => str_contains((string) $image['name'], (string) $imageName));

        if ($image === null) {
            throw new ServerProviderError('Could not find an OVHcloud image for the selected operating system.');
        }

        return $image['id'];
    }

    /**
     * Send a signed request to the OVHcloud API.
     *
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $query
     */
    private function request(array $credentials, string $method, string $path, array $body = [], array $query = []): Response
    {
        $method = strtoupper($method);
        $base = self::ENDPOINTS[$credentials['endpoint'] ?? 'ovh-eu'] ?? self::ENDPOINTS['ovh-eu'];
        $url = $base.$path;

        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        $bodyJson = $body === [] ? '' : (string) json_encode($body);
        $timestamp = $this->timestamp($base);

        $signature = '$1$'.sha1(implode('+', [
            $credentials['application_secret'] ?? '',
            $credentials['consumer_key'] ?? '',
            $method,
            $url,
            $bodyJson,
            $timestamp,
        ]));

        $request = Http::withHeaders([
            'X-Ovh-Application' => $credentials['application_key'] ?? '',
            'X-Ovh-Consumer' => $credentials['consumer_key'] ?? '',
            'X-Ovh-Timestamp' => (string) $timestamp,
            'X-Ovh-Signature' => $signature,
        ]);

        if ($bodyJson !== '') {
            $request = $request->withBody($bodyJson, 'application/json');
        }

        return match ($method) {
            'GET' => $request->get($url),
            'PUT' => $request->put($url),
            'DELETE' => $request->delete($url),
            default => $request->post($url),
        };
    }

    /**
     * OVHcloud signatures are time-sensitive; align to the API clock to avoid
     * INVALID_SIGNATURE errors caused by local clock drift. The delta is fetched
     * once per provider instance.
     */
    private function timestamp(string $base): int
    {
        if ($this->timeDelta === null) {
            $this->timeDelta = (int) Http::get($base.'/auth/time')->body() - time();
        }

        return time() + $this->timeDelta;
    }

    /**
     * @throws ServerProviderError
     */
    private function providerError(Response $response): void
    {
        throw new ServerProviderError($response->json('message') ?? 'OVHcloud API error');
    }
}
