<?php

namespace Tests\Feature;

use App\Console\Commands\ProvisionTrackerCredential;
use App\Models\Device;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\TestCase;

class TrackerCredentialCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $existingCredential;

    protected function setUp(): void
    {
        parent::setUp();

        $this->existingCredential = str_repeat('c', 64);

        $vehicle = Vehicle::create([
            'name' => 'Credential test vehicle',
            'plate_no' => 'CRED-1',
            'status' => 'active',
        ]);

        $existingDevice = Device::create([
            'device_id' => 'TRACKER-1',
            'vehicle_id' => $vehicle->id,
            'vehicle_plate' => $vehicle->plate_no,
        ]);

        $existingDevice->forceFill([
            'tracker_key_hash' => hash(
                'sha256',
                $this->existingCredential
            ),
        ])->save();

        Device::create([
            'device_id' => 'TRACKER-NEW',
            'vehicle_id' => $vehicle->id,
            'vehicle_plate' => $vehicle->plate_no,
        ]);
    }

    public function test_provisioning_generates_and_displays_only_the_plaintext_credential_once(): void
    {
        [$exitCode, $output] = $this->executeCommand(
            ['device_id' => 'TRACKER-NEW'],
            ['yes']
        );

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString(
            'Provision tracker credential for TRACKER-NEW?',
            $output
        );
        $this->assertSame(
            1,
            preg_match_all(
                '/Tracker credential \(copy it securely; it will not be shown again\): ([a-f0-9]{64})/',
                $output,
                $matches
            )
        );

        $credential = $matches[1][0];
        $storedHash = Device::where('device_id', 'TRACKER-NEW')
            ->firstOrFail()
            ->getRawOriginal('tracker_key_hash');

        $this->assertSame(hash('sha256', $credential), $storedHash);
        $this->assertNotSame($credential, $storedHash);
        $this->assertSame(1, substr_count($output, $credential));
    }

    public function test_unknown_device_cannot_be_provisioned(): void
    {
        $this->artisan('trackers:provision-credential', [
            'device_id' => 'MISSING',
        ])
            ->expectsOutputToContain('not registered')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_existing_credentials_are_not_replaced_without_rotate_option(): void
    {
        $originalHash = Device::where('device_id', 'TRACKER-1')
            ->value('tracker_key_hash');

        $this->artisan('trackers:provision-credential', [
            'device_id' => 'TRACKER-1',
        ])
            ->expectsOutputToContain('Use --rotate')
            ->assertExitCode(Command::FAILURE);

        $this->assertSame(
            $originalHash,
            Device::where('device_id', 'TRACKER-1')
                ->value('tracker_key_hash')
        );
    }

    public function test_rotation_rejects_old_credential_and_accepts_new_credential(): void
    {
        [$exitCode, $output] = $this->executeCommand(
            [
                'device_id' => 'TRACKER-1',
                '--rotate' => true,
            ],
            ['yes']
        );

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString(
            'Rotate tracker credential for TRACKER-1?',
            $output
        );
        $this->assertSame(
            1,
            preg_match_all(
                '/Tracker credential \(copy it securely; it will not be shown again\): ([a-f0-9]{64})/',
                $output,
                $matches
            )
        );

        $newCredential = $matches[1][0];

        $this->withHeader('X-Tracker-Key', $this->existingCredential)
            ->postJson('/api/gps/location', [
                'device_id' => 'TRACKER-1',
                'upload_source' => 'live_lte',
                'captured_at' => now('UTC')->toISOString(),
                'latitude' => 10,
                'longitude' => 123,
            ])
            ->assertUnauthorized();

        $this->withHeader('X-Tracker-Key', $newCredential)
            ->postJson('/api/gps/location', [
                'device_id' => 'TRACKER-1',
                'upload_source' => 'live_lte',
                'captured_at' => now('UTC')->toISOString(),
                'latitude' => 10,
                'longitude' => 123,
            ])
            ->assertCreated()
            ->assertDontSee($newCredential);

        $this->assertSame(
            hash('sha256', $newCredential),
            Device::where('device_id', 'TRACKER-1')
                ->value('tracker_key_hash')
        );
    }

    public function test_declined_rotation_leaves_the_existing_credential_unchanged(): void
    {
        $originalHash = Device::where('device_id', 'TRACKER-1')
            ->value('tracker_key_hash');

        [$exitCode, $output] = $this->executeCommand(
            [
                'device_id' => 'TRACKER-1',
                '--rotate' => true,
            ],
            ['no']
        );

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString(
            'No credential changes were made.',
            $output
        );
        $this->assertStringNotContainsString('Tracker credential (copy', $output);
        $this->assertSame(
            $originalHash,
            Device::where('device_id', 'TRACKER-1')
                ->value('tracker_key_hash')
        );
    }

    private function executeCommand(array $arguments, array $answers): array
    {
        $command = app(ProvisionTrackerCredential::class);
        $command->setLaravel(app());

        $tester = new CommandTester($command);
        $tester->setInputs($answers);

        $exitCode = $tester->execute($arguments);

        return [$exitCode, $tester->getDisplay()];
    }
}
