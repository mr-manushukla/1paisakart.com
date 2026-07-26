<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a vendor that one of their products just sold. Sent once per shop per
 * order, listing only that shop's lines — a vendor never sees another's items.
 *
 * Not queued: shared hosting has no worker, so it sends inline. It is fired from
 * DB::afterCommit so a mail failure can never roll back a paid order.
 */
class VendorNewOrderNotification extends Notification
{
    /** @param array<int, array{name:string, qty:int, line_total:int}> $lines */
    public function __construct(
        public Order $order,
        public array $lines,
        public int $shopTotal,
    ) {}

    /** @return array<int,string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("New order #{$this->order->id} — 1paisakart")
            ->greeting('You have a new order 🎉')
            ->line("Order #{$this->order->id} includes the following item(s) from your shop:");

        foreach ($this->lines as $line) {
            $mail->line("• {$line['name']} × {$line['qty']} — ".$this->rupees($line['line_total']));
        }

        return $mail
            ->line('Your order total: '.$this->rupees($this->shopTotal))
            ->line('Please prepare it for dispatch. Delivery details are on your vendor dashboard.')
            ->salutation('— Team 1paisakart');
    }

    /** Paise -> "₹1,234.50" (money lives in paise everywhere; format only at the edge). */
    private function rupees(int $paise): string
    {
        return '₹'.number_format($paise / 100, 2);
    }
}
