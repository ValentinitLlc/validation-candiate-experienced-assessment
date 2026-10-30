<?php

namespace App\Notifications;

use App\Models\Cause;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CauseGoalReachedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Cause $cause;

    /**
     * Create a new notification instance.
     */
    public function __construct(Cause $cause)
    {
        $this->cause = $cause;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Goal Reached: {$this->cause->title}")
            ->line("The cause '{$this->cause->title}' has reached its funding goal!")
            ->line("Goal Amount: {$this->cause->goal_amount}")
            ->action('View Cause', url("/api/admin/causes/{$this->cause->id}"));
    }
}
