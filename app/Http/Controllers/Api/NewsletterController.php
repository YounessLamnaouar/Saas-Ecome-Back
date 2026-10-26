<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class NewsletterController extends Controller
{
    public function subscribe(Request $request)
    {
        $data = $request->validate(['email' => 'required|email|max:254']);
        $key = config('services.brevo.api_key');
        $list = filter_var(config('services.brevo.newsletter_list_id'), FILTER_VALIDATE_INT);
        if (! $key || ! $list || $list < 1) {
            return response()->json(['message' => 'Newsletter subscription is temporarily unavailable.'], 503);
        }
        try {
            $response = Http::withHeaders(['api-key' => $key])->acceptJson()->timeout(10)
                ->post('https://api.brevo.com/v3/contacts', [
                    'email' => strtolower(trim($data['email'])),
                    'listIds' => [$list],
                    'updateEnabled' => true,
                ]);
        } catch (ConnectionException $e) {
            return response()->json(['message' => 'Newsletter subscription is temporarily unavailable.'], 503);
        }
        if (! $response->successful()) {
            return response()->json(['message' => 'Newsletter subscription is temporarily unavailable.'], 503);
        }

        return response()->json(['message' => 'Thank you for subscribing to our newsletter!']);
    }
}
