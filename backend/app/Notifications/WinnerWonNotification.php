<?php

namespace App\Notifications;

use App\Models\DrawEntry;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the pool winner once the draw settles. Email only — SMS goes through
 * SmsService (a provider isn't wired yet). Not queued: on shared hosting there's
 * no worker, so it sends inline (fired from DB::afterCommit so a mail failure
 * can never roll back the draw).
 */
class WinnerWonNotification extends Notification
{
    public function __construct(public DrawEntry $entry) {}

    /** @return array<int,string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $product = $this->entry->product?->name ?? 'your product';

        return (new MailMessage)
            ->subject('🎉 Congratulations — you won on 1paisakart!')
            ->greeting('Congratulations! 🎉')
            ->line("You won the prize — {$product}.")
            ->line('You will receive your product soon. We may reach out to confirm your delivery address.')
            ->line('Thank you for choosing 1paisakart.')
            ->salutation('— Team 1paisakart');
    }
}
