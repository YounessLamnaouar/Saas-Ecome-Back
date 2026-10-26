<?php

namespace App\Notifications;

use App\Enums\OrderStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function backoff(): array
    {
        return [60, 300];
    }

    public function __construct(public string $code, public string $status)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $label = OrderStatus::from($this->status)->label();

        return (new MailMessage)->subject("Order {$this->code}: {$label}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your order {$this->code} is now {$label}.")
            ->line('Thank you for shopping with us.');
    }
}
