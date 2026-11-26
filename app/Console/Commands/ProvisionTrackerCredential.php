<?php

namespace App\Console\Commands;

use App\Models\Device;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProvisionTrackerCredential extends Command
{
    protected $signature = 'trackers:provision-credential {device_id : The registered tracker device ID} {--rotate : Replace an existing tracker credential}';

    protected $description = 'Provision or rotate a tracker credential';

    public function handle(): int
    {
        $deviceId = $this->argument('device_id');
        $device = Device::where('device_id', $deviceId)->first();

        if (!$device) {
            $this->error('The specified tracker device is not registered.');

            return self::FAILURE;
        }

        $previousHash = $device->tracker_key_hash;
        $hasCredential = is_string($previousHash) && $previousHash !== '';
        $rotating = (bool) $this->option('rotate');

        if ($hasCredential && !$rotating) {
            $this->error('This tracker already has a credential. Use --rotate to replace it.');

            return self::FAILURE;
        }

        if (!$hasCredential && $rotating) {
            $this->error('This tracker has no credential to rotate. Omit --rotate to provision one.');

            return self::FAILURE;
        }

        $action = $rotating ? 'Rotate' : 'Provision';

        if (!$this->confirm(
            "{$action} tracker credential for {$deviceId}?",
            false
        )) {
            $this->warn('No credential changes were made.');

            return self::SUCCESS;
        }

        $credential = bin2hex(random_bytes(32));
        $credentialHash = hash('sha256', $credential);

        $updated = DB::transaction(function () use (
            $deviceId,
            $previousHash,
            $credentialHash
        ): bool {
            $lockedDevice = Device::where('device_id', $deviceId)
                ->lockForUpdate()
                ->first();

            if (
                !$lockedDevice ||
                !$this->sameCredentialSnapshot(
                    $previousHash,
                    $lockedDevice->tracker_key_hash
                )
            ) {
                return false;
            }

            return $lockedDevice->forceFill([
                'tracker_key_hash' => $credentialHash,
            ])->save();
        });

        if (!$updated) {
            $this->error('The device credential changed during authorization. Verify its state and try again.');

            return self::FAILURE;
        }

        $this->line(
            'Tracker credential (copy it securely; it will not be shown again): '
            .$credential
        );

        return self::SUCCESS;
    }

    private function sameCredentialSnapshot(
        mixed $previousHash,
        mixed $currentHash
    ): bool {
        if ($previousHash === null || $currentHash === null) {
            return $previousHash === $currentHash;
        }

        return is_string($previousHash)
            && is_string($currentHash)
            && hash_equals($previousHash, $currentHash);
    }
}
