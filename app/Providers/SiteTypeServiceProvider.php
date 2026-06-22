<?php

namespace App\Providers;

use Vito\Plugin\Facades\Register;

use App\DTOs\DynamicField;
use Vito\Plugin\DTOs\DynamicForm;
use App\Enums\LoadBalancerMethod;
use App\SiteFeatures\ModernDeployment\Configuration;
use App\SiteFeatures\ModernDeployment\Disable;
use App\SiteFeatures\ModernDeployment\Enable;
use App\SiteTypes\Blank;
use App\SiteTypes\BunSite;
use App\SiteTypes\Laravel;
use App\SiteTypes\LoadBalancer;
use App\SiteTypes\NodeJS;
use App\SiteTypes\NodeSite;
use App\SiteTypes\PHPBlank;
use App\SiteTypes\PHPMyAdmin;
use App\SiteTypes\PHPSite;
use App\SiteTypes\Wordpress;
use App\Tooling\NodeTooling;
use App\Tooling\PnpmTooling;
use App\Tooling\YarnTooling;
use Illuminate\Support\ServiceProvider;

class SiteTypeServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->php();
        $this->phpBlank();
        $this->laravel();
        $this->nodeJS();
        $this->nodeSite();
        $this->bunSite();
        $this->blank();
        $this->loadBalancer();
        $this->phpMyAdmin();
        $this->wordpress();
    }

    private function php(): void
    {
        Register::siteType(PHPSite::id())
            ->label('PHP')
            ->handler(PHPSite::class)
            ->form(DynamicForm::make([
                DynamicField::make('php_version')
                    ->component()
                    ->label('PHP Version'),
                DynamicField::make('source_control')
                    ->component()
                    ->label('Source Control'),
                DynamicField::make('repository')
                    ->text()
                    ->component()
                    ->label('Repository'),
                DynamicField::make('branch')
                    ->component()
                    ->label('Branch'),
                DynamicField::make('web_directory')
                    ->text()
                    ->label('Web Directory')
                    ->placeholder('e.g., public, www, dist (leave empty for root)')
                    ->description('The relative path of your website from /home/vito/your-domain/'),
                DynamicField::make('composer')
                    ->checkbox()
                    ->label('Run `composer install --no-dev`')
                    ->default(false),
                DynamicField::make('package_manager')
                    ->toolingSelector(
                        [NodeTooling::class, PnpmTooling::class, YarnTooling::class],
                        [NodeTooling::class => 'npm'],
                        null,
                        true,
                    )
                    ->label('Package Manager')
                    ->description('JavaScript package manager used to build front-end assets during deployment.'),
            ]))
            ->register();
    }

    private function phpBlank(): void
    {
        Register::siteType(PHPBlank::id())
            ->label('PHP Blank')
            ->handler(PHPBlank::class)
            ->form(DynamicForm::make([
                DynamicField::make('php_version')
                    ->component()
                    ->label('PHP Version'),
                DynamicField::make('web_directory')
                    ->text()
                    ->label('Web Directory')
                    ->placeholder('e.g., public, www, dist (leave empty for root)')
                    ->description('The relative path of your website from /home/vito/your-domain/'),
                DynamicField::make('package_manager')
                    ->toolingSelector(
                        [NodeTooling::class, PnpmTooling::class, YarnTooling::class],
                        [NodeTooling::class => 'npm'],
                        null,
                        true,
                    )
                    ->label('Package Manager')
                    ->description('JavaScript package manager used to build front-end assets during deployment.'),
            ]))
            ->register();
    }

    private function laravel(): void
    {
        Register::siteType(Laravel::id())
            ->label('Laravel')
            ->handler(Laravel::class)
            ->form(DynamicForm::make([
                DynamicField::make('php_version')
                    ->component()
                    ->label('PHP Version'),
                DynamicField::make('source_control')
                    ->component()
                    ->label('Source Control'),
                DynamicField::make('web_directory')
                    ->text()
                    ->label('Web Directory')
                    ->default('public')
                    ->placeholder('e.g., public, www, dist (leave empty for root)')
                    ->description('The relative path of your website from /home/vito/your-domain/'),
                DynamicField::make('repository')
                    ->text()
                    ->label('Repository')
                    ->placeholder('organization/repository'),
                DynamicField::make('branch')
                    ->text()
                    ->label('Branch')
                    ->default('main'),
                DynamicField::make('composer')
                    ->checkbox()
                    ->label('Run `composer install --no-dev`')
                    ->default(false),
                DynamicField::make('package_manager')
                    ->toolingSelector(
                        [NodeTooling::class, PnpmTooling::class, YarnTooling::class],
                        [NodeTooling::class => 'npm'],
                        NodeTooling::class,
                    )
                    ->label('Package Manager')
                    ->description('JavaScript package manager used to build front-end assets during deployment.'),
            ]))
            ->register();
        Register::siteFeature(Laravel::id(), 'modern-deployment')
            ->label('Modern Deployment (beta)')
            ->description('Enables zero downtime deployment and deployment rollbacks')
            ->register();
        Register::siteFeatureAction(Laravel::id(), 'modern-deployment', 'enable')
            ->label('Enable')
            ->handler(Enable::class)
            ->register();
        Register::siteFeatureAction(Laravel::id(), 'modern-deployment', 'disable')
            ->label('Disable')
            ->handler(Disable::class)
            ->register();
        Register::siteFeatureAction(Laravel::id(), 'modern-deployment', 'configuration')
            ->label('Configure')
            ->handler(Configuration::class)
            ->register();
    }

    private function nodeJS(): void
    {
        Register::siteType(NodeJS::id())
            ->label('NodeJS (Deprecated - Do Not Use)')
            ->handler(NodeJS::class)
            ->form(DynamicForm::make([]))
            ->register();
    }

    private function nodeSite(): void
    {
        Register::siteType(NodeSite::id())
            ->label('Node.js')
            ->handler(NodeSite::class)
            ->form(DynamicForm::make(NodeSite::formFields()))
            ->register();
    }

    private function bunSite(): void
    {
        Register::siteType(BunSite::id())
            ->label('Bun')
            ->handler(BunSite::class)
            ->form(DynamicForm::make(BunSite::formFields()))
            ->register();
    }

    private function blank(): void
    {
        Register::siteType(Blank::id())
            ->label('Blank (Reverse Proxy)')
            ->handler(Blank::class)
            ->form(DynamicForm::make(Blank::formFields()))
            ->register();
    }

    public function loadBalancer(): void
    {
        Register::siteType(LoadBalancer::id())
            ->label('Load Balancer')
            ->handler(LoadBalancer::class)
            ->form(DynamicForm::make([
                DynamicField::make('method')
                    ->select()
                    ->label('Load Balancing Method')
                    ->options([
                        LoadBalancerMethod::IP_HASH->value,
                        LoadBalancerMethod::ROUND_ROBIN->value,
                        LoadBalancerMethod::LEAST_CONNECTIONS->value,
                    ]),
            ]))
            ->register();
    }

    public function phpMyAdmin(): void
    {
        Register::siteType(PHPMyAdmin::id())
            ->label('PHPMyAdmin')
            ->handler(PHPMyAdmin::class)
            ->form(DynamicForm::make([
                DynamicField::make('php_version')
                    ->component()
                    ->label('PHP Version'),
            ]))
            ->register();
    }

    public function wordpress(): void
    {
        Register::siteType(Wordpress::id())
            ->label('WordPress')
            ->handler(Wordpress::class)
            ->form(DynamicForm::make([
                DynamicField::make('php_version')
                    ->component()
                    ->label('PHP Version'),
                DynamicField::make('title')
                    ->text()
                    ->label('Site Title')
                    ->placeholder('My WordPress Site'),
                DynamicField::make('username')
                    ->text()
                    ->label('Admin Username')
                    ->placeholder('admin'),
                DynamicField::make('password')
                    ->text()
                    ->label('Admin Password'),
                DynamicField::make('email')
                    ->text()
                    ->label('Admin Email'),
                DynamicField::make('database')
                    ->text()
                    ->label('Database Name')
                    ->placeholder('wordpress')
                    ->componentProps(['defaultCharset' => 'utf8mb4', 'defaultCollation' => 'utf8mb4_0900_ai_ci']),
                DynamicField::make('database_user')
                    ->text()
                    ->label('Database User')
                    ->placeholder('wp_user'),
                DynamicField::make('database_password')
                    ->text()
                    ->label('Database Password'),
            ]))
            ->register();
    }
}
