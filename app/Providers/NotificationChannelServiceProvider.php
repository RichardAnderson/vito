<?php

namespace App\Providers;

use Vito\Plugin\Facades\Register;

use App\DTOs\DynamicField;
use Vito\Plugin\DTOs\DynamicForm;
use App\NotificationChannels\Discord;
use App\NotificationChannels\Email;
use App\NotificationChannels\Slack;
use App\NotificationChannels\Telegram;
use Illuminate\Support\ServiceProvider;

class NotificationChannelServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->discord();
        $this->slack();
        $this->email();
        $this->telegram();
    }

    private function discord(): void
    {
        Register::notificationChannel(Discord::id())
            ->label('Discord')
            ->handler(Discord::class)
            ->form(
                DynamicForm::make([
                    DynamicField::make('webhook_url')
                        ->text()
                        ->label('Webhook URL'),
                ])
            )
            ->register();
    }

    public function slack(): void
    {
        Register::notificationChannel(Slack::id())
            ->label('Slack')
            ->handler(Slack::class)
            ->form(
                DynamicForm::make([
                    DynamicField::make('webhook_url')
                        ->text()
                        ->label('Webhook URL'),
                ])
            )
            ->register();
    }

    private function email(): void
    {
        Register::notificationChannel(Email::id())
            ->label('Email')
            ->handler(Email::class)
            ->form(
                DynamicForm::make([
                    DynamicField::make('email')
                        ->text()
                        ->label('Email address'),
                ])
            )
            ->register();
    }

    private function telegram(): void
    {
        Register::notificationChannel(Telegram::id())
            ->label('Telegram')
            ->handler(Telegram::class)
            ->form(
                DynamicForm::make([
                    DynamicField::make('bot_token')
                        ->text()
                        ->label('Bot Token'),
                    DynamicField::make('chat_id')
                        ->text()
                        ->label('Chat ID'),
                ]),
            )
            ->register();
    }
}
