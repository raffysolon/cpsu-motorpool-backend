<?php

namespace Tests\Feature;

use Illuminate\Database\PostgresConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GpsSequenceMigrationTest extends TestCase
{
    private function migration(): object
    {
        return require database_path('migrations/2026_11_24_000003_add_unique_device_sequence_to_gps_locations.php');
    }

    public function test_postgres_duplicate_check_selects_only_grouped_columns(): void
    {
        $connection = new PostgresConnection(fn () => throw new \LogicException('SQL compilation must not connect to a database'));
        Schema::shouldReceive('table')->once();
        DB::shouldReceive('table')->once()->with('gps_locations')
            ->andReturn($connection->table('gps_locations'));
        $queries = $connection->pretend(fn () => $this->migration()->up());

        $this->assertCount(1, $queries);
        $this->assertStringContainsString('select "device_registry_id", "device_sequence" from "gps_locations"', $queries[0]['query']);
        $this->assertStringNotContainsString('select *', $queries[0]['query']);
    }

    public function test_duplicates_are_rejected_without_deleting_records(): void
    {
        Schema::create('gps_locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_registry_id');
            $table->unsignedBigInteger('device_sequence')->nullable();
        });
        DB::table('gps_locations')->insert([
            ['device_registry_id' => 1, 'device_sequence' => 7],
            ['device_registry_id' => 1, 'device_sequence' => 7],
        ]);
        try {
            $this->migration()->up();
            $this->fail('Duplicate sequences must block the migration.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Duplicate device sequences detected', $error->getMessage());
            $this->assertSame(2, DB::table('gps_locations')->count());
        }
    }
}
