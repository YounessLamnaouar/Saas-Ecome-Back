<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'not_regex:/[\r\n]/'],
            'email' => ['required', 'email', 'max:254'],
            'phone' => ['nullable', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:150', 'not_regex:/[\r\n]/'],
            'message' => ['required', 'string', 'max:5000'],
            'website' => ['nullable', 'string', 'max:0'],
        ]);

        $key = trim((string) config('services.brevo.api_key'));
        $sender = config('services.brevo.contact_sender');
        $recipient = config('services.brevo.contact_recipient');
        $unavailable = ['message' => 'Unable to send your message right now. Please try again later.'];
        if (! $key || ! filter_var($sender, FILTER_VALIDATE_EMAIL) || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return response()->json($unavailable, 503);
        }

        try {
            // No automatic retries: a timeout may occur after Brevo accepts the message.
            $response = Http::withHeaders(['api-key' => $key])->acceptJson()->connectTimeout(5)->timeout(15)
                ->post('https://api.brevo.com/v3/smtp/email', [
                    'sender' => ['email' => $sender, 'name' => config('services.brevo.contact_sender_name')],
                    'to' => [['email' => $recipient]],
                    'replyTo' => ['email' => $data['email'], 'name' => $data['name']],
                    'subject' => '[Fashion Store contact] '.(($data['subject'] ?? null) ?: 'New message'),
                    'textContent' => "Name: {$data['name']}\nEmail: {$data['email']}\nPhone: ".($data['phone'] ?? 'Not provided')."\n\n{$data['message']}",
                    'tags' => ['contact-form'],
                ]);
        } catch (ConnectionException $e) {
            Log::warning('Contact email provider connection failed.');
            return response()->json($unavailable, 503);
        }

        if (! $response->successful() || ! $response->json('messageId')) {
            Log::warning('Contact email provider rejected request.', ['http_status' => $response->status()]);
            return response()->json($unavailable, 503);
        }

        return response()->json(['message' => 'Your message has been accepted for sending.']);
    }
}
