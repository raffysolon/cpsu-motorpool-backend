<?php

namespace App\Http\Middleware;

use App\Enums\TrackerCallerType;
use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackerAuthentication
{
    public function handle(
        Request $request,
        Closure $next,
        string $operation
    ): Response {
        if ($request->headers->has('X-Tracker-Key')) {
            $deviceId = $request->input('device_id');
            $trackerKeyHeaders = $request->headers->all('X-Tracker-Key');
            $trackerKey = count($trackerKeyHeaders) === 1
                ? $trackerKeyHeaders[0]
                : null;

            if (
                !is_string($deviceId) ||
                !is_string($trackerKey) ||
                preg_match('/\A[a-f0-9]{64}\z/', $trackerKey) !== 1
            ) {
                return $this->unauthorizedTracker();
            }

            $device = Device::where('device_id', $deviceId)->first();

            if (
                !$device ||
                !is_string($device->tracker_key_hash) ||
                !hash_equals(
                    $device->tracker_key_hash,
                    hash('sha256', $trackerKey)
                )
            ) {
                return $this->unauthorizedTracker();
            }

            $request->attributes->set('authenticated_tracker', $device);
            $callerType = TrackerCallerType::IndividualTracker;
        } else {
            $sharedAuthentication = $this->authenticateSharedCaller($request);

            if ($sharedAuthentication instanceof Response) {
                return $sharedAuthentication;
            }

            if (!($sharedAuthentication instanceof TrackerCallerType)) {
                return $this->unauthorizedTracker();
            }

            $callerType = $sharedAuthentication;
        }

        $request->attributes->set('authenticated_caller_type', $callerType);

        if (!$this->isAuthorized($callerType, $request, $operation)) {
            return response()->json([
                'message' => 'This caller is not authorized for the requested tracker operation.',
            ], 403);
        }

        return $next($request);
    }

    private function authenticateSharedCaller(
        Request $request
    ): TrackerCallerType|Response|null {
        $gatewayKey = config('services.gateway.api_key');
        $legacyKey = config('services.tracker_legacy.api_key');
        $providedKeys = $request->headers->all('X-Gateway-Key');
        $providedKey = count($providedKeys) === 1
            ? $providedKeys[0]
            : null;

        if (!is_string($providedKey)) {
            return null;
        }

        $legacyEnabled = (bool) config('tracking.legacy_auth_enabled');
        $gatewayConfigured = is_string($gatewayKey) && $gatewayKey !== '';
        $legacyConfigured = is_string($legacyKey) && $legacyKey !== '';
        $gatewayMatches = $gatewayConfigured
            && hash_equals($gatewayKey, $providedKey);
        $legacyMatches = $legacyEnabled
            && $legacyConfigured
            && hash_equals($legacyKey, $providedKey);

        if (
            $gatewayMatches &&
            $legacyMatches &&
            hash_equals($gatewayKey, $legacyKey)
        ) {
            return response()->json([
                'message' => 'Gateway and legacy authentication credentials must be distinct.',
            ], 503);
        }

        if ($gatewayMatches) {
            return TrackerCallerType::TrustedGateway;
        }

        if ($legacyMatches) {
            $legacyDeviceId = config('tracking.legacy_device_id');
            $submittedDeviceId = $request->input('device_id');

            if (
                !is_string($legacyDeviceId) ||
                $legacyDeviceId === '' ||
                !is_string($submittedDeviceId) ||
                !hash_equals($legacyDeviceId, $submittedDeviceId)
            ) {
                return null;
            }

            return TrackerCallerType::TemporaryLegacy;
        }

        return null;
    }

    private function isAuthorized(
        TrackerCallerType $callerType,
        Request $request,
        string $operation
    ): bool {
        if ($operation === 'heartbeat') {
            return in_array($callerType, [
                TrackerCallerType::IndividualTracker,
                TrackerCallerType::TemporaryLegacy,
            ], true);
        }

        if ($operation === 'gateway-sync-status') {
            return $callerType === TrackerCallerType::TrustedGateway;
        }

        if ($operation !== 'gps') {
            return false;
        }

        $source = $request->input('upload_source');

        if (!in_array($source, [
            null,
            'live_lte',
            'recovered_lte',
            'recovered_lora',
        ], true)) {
            return true;
        }

        return match ($callerType) {
            TrackerCallerType::IndividualTracker => in_array(
                $source,
                ['live_lte', 'recovered_lte'],
                true
            ),
            TrackerCallerType::TrustedGateway => $source === 'recovered_lora',
            TrackerCallerType::TemporaryLegacy => in_array(
                $source,
                [null, 'live_lte', 'recovered_lte'],
                true
            ),
        };
    }

    private function unauthorizedTracker(): Response
    {
        return response()->json([
            'message' => 'Unauthorized tracker.',
        ], 401);
    }
}
