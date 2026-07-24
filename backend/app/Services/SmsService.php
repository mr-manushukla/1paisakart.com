<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * One place to send an SMS. A provider isn't wired yet, so by default this only
 * LOGS what it would send — no dead integration against a provider nobody picked.
 *
 * To go live: choose a provider (MSG91 is standard for India), set
 *   SMS_ENABLED=true, SMS_ENDPOINT, SMS_KEY, SMS_SENDER
 * in .env (see config/services.php), and fill in send() for that provider's API.
 */
class SmsService
{
    public function enabled(): bool
    {
        return (bool) config('services.sms.enabled');
    }

    /** Returns true if handed off to a provider, false if it was only logged. */
    public function send(?string $phone, string $message): bool
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return false;
        }

        if (! $this->enabled()) {
            Log::info('SMS (not sent — provider disabled)', ['to' => $phone, 'message' => $message]);

            return false;
        }

        // ponytail: generic form-post shape. Swap the body for your provider's spec
        // when you wire one up (MSG91/Twilio/etc.).
        Http::asForm()->post((string) config('services.sms.endpoint'), [
            'authkey' => config('services.sms.key'),
            'sender' => config('services.sms.sender'),
            'mobiles' => $phone,
            'message' => $message,
        ])->throw();

        return true;
    }
}
