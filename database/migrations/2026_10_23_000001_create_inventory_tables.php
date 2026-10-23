<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IT support inventory schema.
 *
 * SQL Server rejects multiple cascade paths, so foreign keys here use the
 * default NO ACTION. Records are deactivated/retired instead of deleted to
 * keep the audit trail intact.
 */
return new class extends Migration
{
    public function up(): void
    {
        // People devices are issued to. (`company` text is replaced by company_id
        // in migration 2026_10_23_000002.)
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_no', 30)->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('position', 100)->nullable();
            $table->string('department', 100);
            $table->string('company', 150);
            $table->string('contact_no', 30)->nullable();
            $table->string('email', 150)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Login accounts (Laravel's users table) gain a role, an optional link to
        // the staff member's employee record, and an enabled flag.
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('admin'); // super_admin | admin
            $table->foreignId('employee_id')->nullable()->constrained('employees');
            $table->boolean('is_active')->default(true);
        });

        // Master data maintained by the super admin.
        Schema::create('device_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();   // immutable key stored on devices.type
            $table->string('name', 100);
            $table->string('prefix', 10)->unique(); // asset tag prefix, e.g. LT -> LT-0001
            $table->unsignedInteger('last_number')->default(0);
            $table->boolean('has_computer_specs')->default(false); // processor, memory, storage, OS
            $table->boolean('has_imei')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Dropdown options: brand, model (child of brand), processor, memory, storage,
        // operating_system, color, supplier, spec_name, department, company, position.
        Schema::create('lookups', function (Blueprint $table) {
            $table->id();
            $table->string('category', 30);
            $table->string('value', 150);
            $table->foreignId('parent_id')->nullable()->constrained('lookups');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['category', 'parent_id', 'value']);
        });

        // The asset register. Dropdown-backed columns store the option text.
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('asset_tag', 50)->unique(); // generated from device_types.prefix
            $table->string('type', 30);
            $table->foreign('type')->references('code')->on('device_types');
            $table->string('brand', 100);
            $table->string('model', 150);
            $table->string('serial_number', 100)->nullable()->unique();
            $table->string('imei', 50)->nullable();
            $table->string('processor', 150)->nullable();
            $table->string('memory', 50)->nullable();
            $table->string('storage', 50)->nullable();
            $table->string('operating_system', 100)->nullable();
            $table->string('color', 50)->nullable();
            $table->json('specs')->nullable(); // extra key/value specs
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 12, 2)->nullable();
            $table->date('warranty_until')->nullable();
            $table->string('supplier', 150)->nullable();
            // available, assigned, in_repair, lost, damaged, retired
            $table->string('status', 20)->default('available')->index();
            $table->string('condition', 20)->default('good'); // new, good, fair, poor
            $table->text('notes')->nullable();
            $table->foreignId('current_employee_id')->nullable()->constrained('employees');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        // Requests filed by admins for employees (new device, repair, damage, lost, return).
        Schema::create('device_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 30)->unique();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('requested_by')->nullable()->constrained('users');
            // new_device, repair, lost, damaged, return
            $table->string('request_type', 20)->index();
            $table->string('device_type', 30)->nullable(); // for new_device requests
            $table->foreign('device_type')->references('code')->on('device_types');
            $table->foreignId('device_id')->nullable()->constrained('devices');
            $table->string('priority', 10)->default('normal'); // low, normal, high
            $table->text('reason');
            $table->dateTime('incident_date')->nullable();
            // pending, approved, rejected, completed, cancelled
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('handled_by')->nullable()->constrained('users');
            $table->dateTime('handled_at')->nullable();
            $table->text('admin_remarks')->nullable();
            $table->timestamps();
        });

        // Hand-over history: one row per time a device was given to someone
        // (returned_at null = still with them).
        Schema::create('device_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices');
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('device_request_id')->nullable()->constrained('device_requests');
            $table->foreignId('assigned_by')->nullable()->constrained('users');
            $table->dateTime('assigned_at');
            $table->string('condition_on_assign', 20)->nullable();
            $table->dateTime('returned_at')->nullable();
            $table->foreignId('returned_to')->nullable()->constrained('users');
            $table->string('condition_on_return', 20)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        // Append-only audit trail (no updated_at). Written via AuditLog::record().
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users');      // who did it
            $table->foreignId('device_id')->nullable()->constrained('devices');
            $table->foreignId('employee_id')->nullable()->constrained('employees');
            $table->foreignId('device_request_id')->nullable()->constrained('device_requests');
            $table->string('action', 50)->index();
            $table->text('description');
            $table->json('changes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('device_assignments');
        Schema::dropIfExists('device_requests');
        Schema::dropIfExists('devices');
        Schema::dropIfExists('lookups');
        Schema::dropIfExists('device_types');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropColumn(['role', 'employee_id', 'is_active']);
        });
        Schema::dropIfExists('employees');
    }
};
