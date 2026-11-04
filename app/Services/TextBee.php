<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class TextBee
{
    public static function body(string $type, CarbonImmutable $scheduledAt): string
    {
        $date = $scheduledAt->setTimezone('Asia/Manila')->format('d M Y, h:i A');
        $prefix = match ($type) {
            'Cancellation' => 'Cancelled: check-up',
            'Reschedule' => 'Updated check-up:',
            'Reminder' => 'Reminder: check-up',
            default => 'Check-up confirmed:',
        };

        return "Alabang Health Center: {$prefix} {$date} (PH). ".($type === 'Cancellation' ? 'Contact the health center to rebook.' : 'Bring your health center ID. Arrive 10 min early.');
    }

    public function configured(): bool
    {
        return (bool) config('textbee.enabled') && filled(config('textbee.api_key')) && filled(config('textbee.device_id'));
    }

    private function client(): PendingRequest
    {
        if (app()->runningUnitTests()) {
            Http::preventStrayRequests();
        }

        return Http::baseUrl(rtrim(config('textbee.base_url'), '/'))
            ->withHeaders(['x-api-key' => config('textbee.api_key')])->acceptJson()->asJson()->connectTimeout(5)->timeout(15)
            ->withOptions(['allow_redirects' => false]);
    }

    /** @return array{status: string, outcome: string, batch_id?: string} */
    public function send(string $recipient, string $body): array
    {
        try {
            $device = $this->client()->get('/gateway/devices/'.rawurlencode(config('textbee.device_id')));
            if (! $device->successful() || $device->json('data.enabled') !== true) {
                return ['status' => 'Failed', 'outcome' => 'Device unavailable or disabled. Verify device and credentials before retrying.'];
            }
        } catch (ConnectionException) {
            return ['status' => 'Failed', 'outcome' => 'Device check failed. No send was attempted; verify connectivity before retrying.'];
        }
        try {
            $response = $this->client()->post('/gateway/send-sms', [
                'deviceId' => config('textbee.device_id'), 'recipients' => [$recipient], 'message' => $body,
            ]);
        } catch (ConnectionException) {
            return ['status' => 'Uncertain', 'outcome' => 'Send response unavailable. Check TextBee history before sending again.'];
        }
        if ($response->successful()) {
            $batchId = $response->json('data.smsBatchId');
            if (is_string($batchId) && $batchId !== '' && $response->json('data.success') === true) {
                return ['status' => 'Submitted', 'batch_id' => $batchId, 'outcome' => 'Accepted by TextBee; delivery not yet confirmed.'];
            }

            return ['status' => 'Uncertain', 'outcome' => 'Response did not include a verifiable batch. Check TextBee history.'];
        }

        return match ($response->status()) {
            400 => ['status' => 'Failed', 'outcome' => 'TextBee rejected the request. Verify device, SMS permissions and account setup.'],
            401, 403, 404, 422 => ['status' => 'Failed', 'outcome' => 'TextBee rejected the request. Verify configuration and recipient.'],
            429 => ['status' => 'Pending', 'outcome' => 'TextBee rate limit: retry deferred.'],
            default => ['status' => 'Uncertain', 'outcome' => 'TextBee outcome unknown. Check provider history before sending again.'],
        };
    }

    /** @return array{status: string, outcome: string}|null */
    public function delivery(string $deviceId, string $batchId, string $recipient): ?array
    {
        try {
            $response = $this->client()->get('/gateway/devices/'.rawurlencode($deviceId).'/sms-batch/'.rawurlencode($batchId));
        } catch (ConnectionException) {
            return null;
        }
        if (! $response->successful()) {
            return null;
        }
        foreach ($response->json('data.messages', []) as $message) {
            if (($message['recipient'] ?? null) !== $recipient) {
                continue;
            }

            return match ($message['status'] ?? null) {
                'delivered' => ['status' => 'Delivered', 'outcome' => 'Carrier confirmed delivery.'],
                'failed' => ['status' => 'Failed', 'outcome' => 'TextBee reports delivery failure. Verify contact and sending device.'],
                'unknown' => ['status' => 'Submitted', 'outcome' => 'TextBee has no device result yet. Do not resend automatically.'],
                default => ['status' => 'Submitted', 'outcome' => 'Awaiting carrier delivery confirmation.'],
            };
        }

        return null;
    }
}
