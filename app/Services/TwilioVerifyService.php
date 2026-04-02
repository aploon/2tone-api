<?php

namespace App\Services;

use Twilio\Exceptions\RestException;
use Twilio\Rest\Client;

class TwilioVerifyService
{
    private ?Client $client = null;

    public function __construct()
    {
        $sid = config('services.twilio.account_sid');
        $token = config('services.twilio.auth_token');
        if (is_string($sid) && $sid !== '' && is_string($token) && $token !== '') {
            $this->client = new Client($sid, $token);
        }
    }

    public function isConfigured(): bool
    {
        $sid = config('services.twilio.verify_service_sid');

        return $this->client !== null && is_string($sid) && $sid !== '';
    }

    public function sendVerification(string $e164Phone): void
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Twilio Verify is not configured.');
        }

        $this->client->verify->v2
            ->services((string) config('services.twilio.verify_service_sid'))
            ->verifications
            ->create($e164Phone, 'sms');
    }

    public function checkVerification(string $e164Phone, string $code): bool
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Twilio Verify is not configured.');
        }

        try {
            $check = $this->client->verify->v2
                ->services((string) config('services.twilio.verify_service_sid'))
                ->verificationChecks
                ->create([
                    'code' => $code,
                    'to' => $e164Phone,
                ]);
        } catch (RestException $e) {
            return false;
        }

        return ($check->status ?? '') === 'approved';
    }
}
