<?php

namespace App\Providers;

use Vito\Plugin\Facades\Register;

use App\DTOs\DynamicField;
use Vito\Plugin\DTOs\DynamicForm;
use App\StorageProviders\Dropbox;
use App\StorageProviders\FTP;
use App\StorageProviders\Local;
use App\StorageProviders\S3;
use App\StorageProviders\SFTP;
use Illuminate\Support\ServiceProvider;

class StorageProviderServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->local();
        $this->aws();
        $this->dropbox();
        $this->ftp();
        $this->sftp();
    }

    private function local(): void
    {
        Register::storageProvider(Local::id())
            ->label('Local')
            ->handler(Local::class)
            ->form(
                DynamicForm::make([
                    DynamicField::make('path')
                        ->text()
                        ->label('Path'),
                ])
            )
            ->register();
    }

    private function aws(): void
    {
        Register::storageProvider(S3::id())
            ->label('S3')
            ->handler(S3::class)
            ->form(
                DynamicForm::make([
                    DynamicField::make('api_url')
                        ->text()
                        ->label('API URL'),
                    DynamicField::make('key')
                        ->text()
                        ->label('Access Key'),
                    DynamicField::make('secret')
                        ->text()
                        ->label('Secret Key'),
                    DynamicField::make('region')
                        ->text()
                        ->label('Region'),
                    DynamicField::make('bucket')
                        ->text()
                        ->label('Bucket Name'),
                    DynamicField::make('path')
                        ->text()
                        ->label('Path'),
                ])
            )
            ->register();
    }

    private function dropbox(): void
    {
        Register::storageProvider(Dropbox::id())
            ->label('Dropbox')
            ->handler(Dropbox::class)
            ->form(
                DynamicForm::make([
                    DynamicField::make('app_key')
                        ->text()
                        ->label('App key'),
                    DynamicField::make('app_secret')
                        ->password()
                        ->label('App secret'),
                ])
            )
            ->register();
    }

    private function ftp(): void
    {
        Register::storageProvider(FTP::id())
            ->label('FTP')
            ->handler(FTP::class)
            ->form(
                DynamicForm::make([
                    DynamicField::make('host')
                        ->text()
                        ->label('Host'),
                    DynamicField::make('port')
                        ->text()
                        ->label('Port')
                        ->default(21),
                    DynamicField::make('path')
                        ->text()
                        ->label('Path'),
                    DynamicField::make('username')
                        ->text()
                        ->label('Username'),
                    DynamicField::make('password')
                        ->text()
                        ->label('Password'),
                    DynamicField::make('ssl')
                        ->checkbox()
                        ->label('Use SSL')
                        ->default(false),
                    DynamicField::make('passive')
                        ->checkbox()
                        ->label('Use Passive Mode')
                        ->default(true),
                ])
            )
            ->register();
    }

    private function sftp(): void
    {
        Register::storageProvider(SFTP::id())
            ->label('SFTP')
            ->handler(SFTP::class)
            ->form(
                DynamicForm::make([
                    DynamicField::make('host')
                        ->text()
                        ->label('Host'),
                    DynamicField::make('port')
                        ->text()
                        ->label('Port')
                        ->default(22),
                    DynamicField::make('path')
                        ->text()
                        ->label('Path'),
                    DynamicField::make('username')
                        ->text()
                        ->label('Username'),
                    DynamicField::make('password')
                        ->password()
                        ->label('Password'),
                ])
            )
            ->register();
    }
}
