<?php

namespace Vito\Core\Testing;

use App\Providers\CoreServiceProvider;
use Composer\InstalledVersions;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Application;
use Illuminate\Support\Str;

trait CreatesApplication
{
    public function createApplication(): Application
    {
        $this->prepareEnvironment();

        $app = Application::configure(basePath: $this->basePath())
            ->withProviders([CoreServiceProvider::class])
            ->create();

        $app->useStoragePath($this->testStoragePath());

        $app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function (Application $app): void {
            $directories = array_values(array_filter(
                (array) $app['config']->get('route-attributes.directories', []),
                'is_dir'
            ));
            $app['config']->set('route-attributes.directories', $directories);
        });

        $this->resolveCoreFactoryNames($app);

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function resolveCoreFactoryNames(Application $app): void
    {
        Factory::guessFactoryNamesUsing(function (string $modelName): string {
            $coreFactory = 'Database\\Factories\\'.class_basename($modelName).'Factory';
            if (class_exists($coreFactory)) {
                return $coreFactory;
            }

            $appNamespace = $app->getNamespace();
            $relative = Str::startsWith($modelName, $appNamespace.'Models\\')
                ? Str::after($modelName, $appNamespace.'Models\\')
                : Str::after($modelName, $appNamespace);

            return 'Database\\Factories\\'.$relative.'Factory';
        });
    }

    protected function basePath(): string
    {
        $root = InstalledVersions::getRootPackage()['install_path'] ?? getcwd();

        return rtrim((string) realpath($root) ?: $root, '/');
    }

    protected function testStoragePath(): string
    {
        $path = $this->basePath().'/storage/testing';

        foreach (['app', 'framework/cache', 'framework/views', 'framework/sessions', 'logs'] as $dir) {
            if (! is_dir($path.'/'.$dir)) {
                mkdir($path.'/'.$dir, 0755, true);
            }
        }

        return $path;
    }

    protected function prepareEnvironment(): void
    {
        $bootstrapCache = $this->basePath().'/bootstrap/cache';
        if (! is_dir($bootstrapCache)) {
            mkdir($bootstrapCache, 0755, true);
        }

        $token = getenv('TEST_TOKEN') ?: '1';

        $database = $this->basePath().'/storage/testing/database-'.$token.'.sqlite';
        if (! is_dir(dirname($database))) {
            mkdir(dirname($database), 0755, true);
        }
        if (! file_exists($database)) {
            touch($database);
        }

        $this->setEnv('APP_ENV', 'testing');
        $this->setEnv('APP_DEBUG', 'true');
        $this->setEnv('DB_CONNECTION', 'sqlite');
        $this->setEnv('DB_DATABASE', $database);
        $this->setEnv('CACHE_STORE', 'array');
        $this->setEnv('SESSION_DRIVER', 'array');
        $this->setEnv('QUEUE_CONNECTION', 'sync');
        $this->setEnv('MAIL_MAILER', 'array');
        $this->setEnv('BCRYPT_ROUNDS', '4');

        if (! env('APP_KEY')) {
            $this->setEnv('APP_KEY', 'base64:'.base64_encode(random_bytes(32)));
        }
    }

    protected function setEnv(string $key, string $value): void
    {
        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
