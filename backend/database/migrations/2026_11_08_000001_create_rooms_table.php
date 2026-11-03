<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Salles préenregistrées par site : une classe choisit sa salle dans la
 * liste (ou la crée à la volée) au lieu de saisir un texte libre.
 * Les salles déjà saisies sur les classes sont reprises.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('site_id');
            $table->string('name', 50);
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign(['tenant_id', 'site_id'])->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'site_id', 'name']);
        });

        DB::statement('ALTER TABLE rooms ADD CONSTRAINT rooms_capacity CHECK (capacity IS NULL OR capacity > 0)');

        Schema::table('school_classes', function (Blueprint $table) {
            $table->unsignedBigInteger('room_id')->nullable()->after('capacity');
            $table->foreign(['tenant_id', 'room_id'])->references(['tenant_id', 'id'])->on('rooms')->restrictOnDelete();
        });

        DB::statement("INSERT INTO rooms (uuid, tenant_id, site_id, name, is_active, created_at, updated_at)
            SELECT gen_random_uuid(), tenant_id, site_id, trim(room), true, now(), now()
            FROM school_classes
            WHERE room IS NOT NULL AND trim(room) <> '' AND site_id IS NOT NULL
            GROUP BY tenant_id, site_id, trim(room)");
        DB::statement('UPDATE school_classes SET room_id = rooms.id FROM rooms
            WHERE rooms.tenant_id = school_classes.tenant_id AND rooms.site_id = school_classes.site_id AND rooms.name = trim(school_classes.room)');

        Schema::table('school_classes', function (Blueprint $table) {
            $table->dropColumn('room');
        });
    }

    public function down(): void
    {
        Schema::table('school_classes', function (Blueprint $table) {
            $table->string('room', 50)->nullable();
        });

        DB::statement('UPDATE school_classes SET room = rooms.name FROM rooms WHERE rooms.id = school_classes.room_id');

        Schema::table('school_classes', function (Blueprint $table) {
            $table->dropForeign(['tenant_id', 'room_id']);
            $table->dropColumn('room_id');
        });

        Schema::dropIfExists('rooms');
    }
};
