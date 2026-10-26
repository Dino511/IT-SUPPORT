<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Serial numbers stay unique, but more than one device may now have none.
 * SQL Server counts NULL as a value in a unique index, so the original index
 * let only one device be saved without a serial number (the next one failed
 * with "duplicate key value is (<NULL>)"). A filtered index skips NULLs, which
 * is how the other databases treat them anyway.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropUnique(['serial_number']);
        });

        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement('CREATE UNIQUE INDEX devices_serial_number_unique ON devices (serial_number) WHERE serial_number IS NOT NULL');
        } else {
            Schema::table('devices', function (Blueprint $table) {
                $table->unique('serial_number');
            });
        }
    }

    /** Fails on SQL Server if two or more devices have no serial number by then. */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropUnique(['serial_number']);
            $table->unique('serial_number');
        });
    }
};
