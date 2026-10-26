<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Console\Formatter\OutputFormatter;

class CheckBrevoNewsletter extends Command
{
    protected $signature = 'brevo:check-newsletter';

    protected $description = 'Check newsletter API credentials and list access without subscribing anyone';

    public function handle(): int
    {
        $key = trim((string) config('services.brevo.api_key'));
        $list = filter_var(config('services.brevo.newsletter_list_id'), FILTER_VALIDATE_INT);
        if ($key === '' || str_starts_with($key, 'xsmtpsib-')) {
            $this->error('BREVO_API_KEY is missing or contains an SMTP key. Use an API v3 key.');

            return self::FAILURE;
        }
        if (! $list || $list < 1) {
            $this->error('BREVO_NEWSLETTER_LIST_ID must be a positive numeric list ID.');

            return self::FAILURE;
        }
        try {
            $response = Http::withHeaders(['api-key' => $key])->acceptJson()->timeout(15)
                ->get('https://api.brevo.com/v3/contacts/lists/'.$list);
        } catch (ConnectionException $e) {
            $this->error('Cannot connect to Brevo over HTTPS. Check network access and PHP TLS/CA settings.');

            return self::FAILURE;
        }
        if ($response->successful()) {
            $this->info('PASS: Brevo API authentication and newsletter list access work. No contact was created.');

            return self::SUCCESS;
        }
        $reason = match ($response->status()) {
            401 => 'API authentication rejected. Check the API v3 key and Brevo authorized IP settings.',
            403 => 'Access denied. Check Brevo authorized IPs, account permissions and network policy.',
            404 => 'List not found. Check the numeric list ID and that the key belongs to the same Brevo account.',
            429 => 'Brevo rate limit reached. Wait before retrying.',
            default => 'Brevo request failed. Check the account status and try again later.',
        };
        $this->error('HTTP '.$response->status().': '.$reason);
        $message = $response->json('message');
        $code = $response->json('code');
        if (is_string($message) || is_string($code)) {
            foreach (['Provider code' => $code, 'Provider message' => $message] as $label => $value) {
                if (! is_string($value)) {
                    continue;
                }
                $value = str_replace($key, '[REDACTED]', $value);
                $value = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value);
                $this->line($label.': '.OutputFormatter::escape(mb_substr($value, 0, 600)));
            }
        } else {
            $this->warn('No Brevo JSON error message was returned. A proxy, firewall or gateway may have rejected the request before it reached Brevo.');
        }
        if (getenv('HTTPS_PROXY') || getenv('https_proxy') || getenv('ALL_PROXY')) {
            $this->line('An outbound proxy is configured in this process (address hidden).');
        }

        return self::FAILURE;
    }
}
