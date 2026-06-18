<?php

namespace App\Notifications;

use App\Models\Deployment;
use Illuminate\Notifications\Messages\MailMessage;

class FailedToDeleteDeploymentBackup extends AbstractNotification
{
    public function __construct(protected Deployment $deployment) {}

    public function rawText(): string
    {
        return "Failed to delete deployment backup files for deployment: {$this->deployment->id}";
    }

    public function toEmail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject(__('Failed to delete deployment backup'))
            ->line(__('We couldn\'t delete the backup files for deployment [:id].', [
                'id' => $this->deployment->id,
            ]))
            ->line(__('Please check your storage provider and remove them manually.'));
    }
}
