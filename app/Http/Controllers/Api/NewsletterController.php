<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NewsletterController extends Controller
{
    public function subscribe(Request $request)
    {
        $data = $request->validate(['email' => 'required|email|max:254']);
        $key = trim((string) config('services.brevo.api_key'));
        $list = filter_var(config('services.brevo.newsletter_list_id'), FILTER_VALIDATE_INT);
        if (! $key || ! $list || $list < 1) {
            Log::warning('Newsletter configuration missing or invalid; run php artisan brevo:check-newsletter.');

            return response()->json(['message' => 'Newsletter subscription is temporarily unavailable.'], 503);
        }
        $email = strtolower(trim($data['email']));
        $success = ['message' => 'Thank you for subscribing to our newsletter!'];
        try {
            $client = Http::withHeaders(['api-key' => $key])->acceptJson()->connectTimeout(5)->timeout(15)
                ->retry(2, 500, fn ($exception) => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError()), throw: false);
            $contact = $client->get('https://api.brevo.com/v3/contacts/'.rawurlencode($email));
            if ($contact->successful() && in_array($list, $contact->json('listIds', []), true)) {
                return response()->json($success);
            }

            if ($contact->successful()) {
                // Existing contacts use the membership endpoint, not create/updateEnabled.
                $response = $client->post('https://api.brevo.com/v3/contacts/lists/'.$list.'/contacts/add', ['emails' => [$email]]);
                $added = $response->json('contacts.success', $response->json('success', []));
                if ($response->successful() && ! in_array($email, $added, true)) {
                    return response()->json(['message' => 'Newsletter subscription is temporarily unavailable.'], 503);
                }
            } elseif ($contact->status() === 404) {
                $response = $client->post('https://api.brevo.com/v3/contacts', [
                    'email' => $email,
                    'listIds' => [$list],
                ]);
                // Another request may create this contact between lookup and creation.
                if ($response->status() === 400 && $response->json('code') === 'duplicate_parameter') {
                    $response = $client->post('https://api.brevo.com/v3/contacts/lists/'.$list.'/contacts/add', ['emails' => [$email]]);
                    $added = $response->json('contacts.success', $response->json('success', []));
                    if ($response->successful() && ! in_array($email, $added, true)) {
                        return response()->json(['message' => 'Newsletter subscription is temporarily unavailable.'], 503);
                    }
                }
            } else {
                $response = $contact;
            }
        } catch (ConnectionException $e) {
            $previous = $e->getPrevious();
            $context = $previous && method_exists($previous, 'getHandlerContext') ? $previous->getHandlerContext() : [];
            Log::warning('Newsletter HTTPS connection failed; run php artisan brevo:check-newsletter.', [
                'curl_errno' => $context['errno'] ?? null,
                'connect_time' => $context['connect_time'] ?? null,
                'total_time' => $context['total_time'] ?? null,
            ]);

            return response()->json(['message' => 'Newsletter subscription is temporarily unavailable.'], 503);
        }
        if (! $response->successful()) {
            Log::warning('Newsletter provider rejected request; run php artisan brevo:check-newsletter.', ['http_status' => $response->status()]);

            return response()->json(['message' => 'Newsletter subscription is temporarily unavailable.'], 503);
        }

        return response()->json($success);
    }
}
