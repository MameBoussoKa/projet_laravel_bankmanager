<?php

namespace App\Services;

use Twilio\Rest\Client;
use Illuminate\Support\Facades\Log;

/**
 * Service for sending SMS using Twilio
 */
class SmsService
{
    protected $twilio;

    public function __construct()
    {
        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');

        // Check if credentials are configured
        if (!$sid || !$token || $sid === 'votre_account_sid_twilio' || $token === 'votre_auth_token_twilio') {
            throw new \Exception('Twilio credentials not properly configured. Please set TWILIO_SID and TWILIO_TOKEN in your .env file.');
        }

        $this->twilio = new Client($sid, $token);
    }

    /**
     * Send SMS to a phone number
     *
     * @param string $to Phone number in international format (e.g., +221771234568)
     * @param string $message The message to send
     * @return bool True if sent successfully, false otherwise
     */
    public function sendSms(string $to, string $message): bool
    {
        try {
            $this->twilio->messages->create($to, [
                'from' => config('services.twilio.from'),
                'body' => $message
            ]);

            Log::info("SMS envoyé avec succès à {$to}: {$message}");
            return true;
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'envoi du SMS à {$to}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send authentication code via SMS
     *
     * @param string $phoneNumber Phone number in international format
     * @param string $code The authentication code
     * @return bool True if sent successfully, false otherwise
     */
    public function sendAuthenticationCode(string $phoneNumber, string $code): bool
    {
        $message = "Votre code d'authentification BankManager est: {$code}";
        return $this->sendSms($phoneNumber, $message);
    }
}