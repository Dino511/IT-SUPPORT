<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-company support: companies become their own master table that both
 * employees and devices belong to (replacing the free-text employees.company
 * and the "company" lookup list). Also lets a device carry its uploaded
 * purchase order (PO) document.
 * Existing data is carried over.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150)->unique();
            $table->string('country', 100)->nullable();
            $table->string('address', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // carry over every company name already in use
        $names = DB::table('lookups')->where('category', 'company')->pluck('value')
            ->merge(DB::table('employees')->distinct()->pluck('company'))
            ->filter()->unique(fn ($n) => mb_strtolower($n));
        $codes = [];
        foreach ($names as $name) {
            $code = $base = substr(preg_replace('/[^A-Z0-9]/', '', strtoupper(
                collect(preg_split('/\s+/', $name))->map(fn ($w) => $w[0] ?? '')->implode('')
            )), 0, 10) ?: 'CO';
            for ($i = 2; in_array($code, $codes, true); $i++) {
                $code = $base.$i;
            }
            $codes[] = $code;
            DB::table('companies')->insert(['code' => $code, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]);
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('department')->constrained('companies');
        });
        DB::statement('UPDATE employees SET company_id = (SELECT c.id FROM companies c WHERE c.name = employees.company)');
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('company');
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->constrained('companies'); // owning company
            $table->string('po_file_path', 255)->nullable();  // uploaded PO, on the private "local" disk
            $table->string('po_file_name', 255)->nullable();  // original file name, for downloads
        });
        // a device already assigned is taken to belong to its holder's company
        DB::statement('UPDATE devices SET company_id = (SELECT e.company_id FROM employees e WHERE e.id = devices.current_employee_id) WHERE current_employee_id IS NOT NULL');

        DB::table('lookups')->where('category', 'company')->delete();
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn(['company_id', 'po_file_path', 'po_file_name']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->string('company', 150)->nullable();
        });
        DB::statement('UPDATE employees SET company = (SELECT c.name FROM companies c WHERE c.id = employees.company_id)');
        foreach (DB::table('companies')->pluck('name') as $name) {
            DB::table('lookups')->insert(['category' => 'company', 'value' => $name, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });

        Schema::dropIfExists('companies');
    }
};
