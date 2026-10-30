<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Communication — docs/PRODUCT_ARCHITECTURE.md §8.7 et §13.
 *
 * - Notifications dans l'app (par compte et par école).
 * - SMS : chaque message est journalisé (coût, preuve d'envoi), quel que
 *   soit le fournisseur branché.
 * - Annonces de l'école aux familles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            // Nom d'expéditeur affiché sur le téléphone (11 caractères max).
            $table->string('sms_sender', 11)->nullable();
            // SMS automatiques : désactivés par défaut (ils ont un coût).
            $table->boolean('sms_on_absence')->default(false);
            $table->boolean('sms_on_payment')->default(false);
            $table->boolean('sms_on_report_card')->default(false);
            $table->timestamps();
        });

        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('title', 150);
            $table->string('body', 1000);
            $table->jsonb('data')->default('{}');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'tenant_id', 'created_at']);
        });

        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 20);
            // Texte tel qu'envoyé, sauf secrets (mot de passe provisoire) masqués.
            $table->string('body', 1000);
            $table->unsignedSmallInteger('segments');
            $table->string('purpose', 30);
            $table->string('status', 10)->default('queued');
            $table->string('provider', 30);
            $table->string('provider_message_id')->nullable();
            $table->string('error')->nullable();
            $table->nullableMorphs('subject');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'status']);
        });

        DB::statement("ALTER TABLE sms_messages ADD CONSTRAINT sms_messages_status CHECK (status IN ('queued', 'sent', 'failed'))");

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('body', 2000);
            // school, cycle, level, class.
            $table->string('audience', 10);
            $table->unsignedBigInteger('audience_id')->nullable();
            $table->string('audience_label', 100);
            $table->boolean('send_sms')->default(false);
            $table->unsignedInteger('families_count')->default(0);
            $table->unsignedInteger('app_recipients_count')->default(0);
            $table->unsignedInteger('sms_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at');
            $table->timestamps();

            $table->index(['tenant_id', 'published_at']);
        });

        DB::statement("ALTER TABLE announcements ADD CONSTRAINT announcements_audience CHECK (
            (audience = 'school' AND audience_id IS NULL) OR (audience IN ('cycle', 'level', 'class') AND audience_id IS NOT NULL))");

        // Jetons de notification push des appareils (Firebase), par compte.
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 512)->unique();
            $table->string('platform', 10);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('sms_messages');
        Schema::dropIfExists('user_notifications');
        Schema::dropIfExists('communication_settings');
    }
};
