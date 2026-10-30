<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Matières et personnel de l'école — docs/PRODUCT_ARCHITECTURE.md §8.2.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Cible de la clé composite staff_members → memberships.
        Schema::table('memberships', function (Blueprint $table) {
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('staff_members', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // Compte de connexion, s'il en a un (voir GrantStaffAccess).
            $table->unsignedBigInteger('membership_id')->nullable()->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('gender', 10)->nullable();
            $table->string('position', 30);
            $table->string('employee_number', 50)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'employee_number']);
            $table->index(['tenant_id', 'last_name']);
        });

        // Le compte doit appartenir à la même école ; SET NULL limité à
        // membership_id (PostgreSQL 15+).
        DB::statement('ALTER TABLE staff_members ADD CONSTRAINT staff_members_membership_foreign
            FOREIGN KEY (tenant_id, membership_id) REFERENCES memberships (tenant_id, id)
            ON DELETE SET NULL (membership_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_members');
        Schema::dropIfExists('subjects');

        Schema::table('memberships', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'id']);
        });
    }
};
