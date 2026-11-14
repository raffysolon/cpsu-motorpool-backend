
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('gps_locations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('device_registry_id')
                ->nullable()
                ->constrained('device_registry')
                ->nullOnDelete();

            $table->foreignId('vehicle_id')
                ->nullable()
                ->constrained('vehicles')
                ->nullOnDelete();

            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);

            $table->unsignedSmallInteger('satellites')
                ->nullable();

            $table->timestamp('recorded_at');

            $table->timestamps();

            $table->index(['vehicle_id', 'recorded_at']);
            $table->index(['device_registry_id', 'recorded_at']);
        });

        // Enable Row Level Security for PostgreSQL
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE gps_locations ENABLE ROW LEVEL SECURITY'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gps_locations');
    }
};
