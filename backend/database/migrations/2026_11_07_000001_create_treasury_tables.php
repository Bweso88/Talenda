<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trésorerie — docs/TRESORERIE.md.
 *
 * - Sites : une école peut en avoir plusieurs ; les classes (donc les
 *   élèves) et les caisses y sont rattachées.
 * - Caisses typées (espèces, mobile money, banque), chacune avec son compte
 *   SYSCOHADA (571, 552, 521) et un solde suivi.
 * - Opérations de caisse : dépenses, salaires, avances à justifier,
 *   virements entre caisses. Jamais supprimées : une erreur s'annule.
 * - Journal comptable : chaque mouvement d'argent produit une écriture
 *   équilibrée (débit = crédit), exportable pour le comptable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'name']);
        });

        // Un site par école existante ; ses classes et ses caisses y sont rattachées.
        DB::statement("INSERT INTO sites (uuid, tenant_id, name, is_active, created_at, updated_at)
            SELECT gen_random_uuid(), id, 'Site principal', true, now(), now() FROM tenants");

        Schema::table('school_classes', function (Blueprint $table) {
            $table->unsignedBigInteger('site_id')->nullable()->after('school_year_id');
            $table->foreign(['tenant_id', 'site_id'])->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
            $table->index(['tenant_id', 'site_id']);
        });

        Schema::table('cash_registers', function (Blueprint $table) {
            $table->unsignedBigInteger('site_id')->nullable()->after('tenant_id');
            // cash (571), mobile_money (552), bank (521).
            $table->string('kind', 15)->default('cash')->after('name');
            $table->string('account_code', 10)->default('5711')->after('kind');
            // Solde de départ, fixé à la première ouverture (fond de caisse initial).
            $table->bigInteger('opening_balance')->default(0)->after('account_code');
            $table->foreign(['tenant_id', 'site_id'])->references(['tenant_id', 'id'])->on('sites')->restrictOnDelete();
        });

        DB::statement('UPDATE school_classes SET site_id = sites.id FROM sites WHERE sites.tenant_id = school_classes.tenant_id');
        DB::statement('UPDATE cash_registers SET site_id = sites.id FROM sites WHERE sites.tenant_id = cash_registers.tenant_id');
        DB::statement("ALTER TABLE cash_registers ADD CONSTRAINT cash_registers_kind CHECK (kind IN ('cash', 'mobile_money', 'bank'))");
        DB::statement('ALTER TABLE cash_registers ADD CONSTRAINT cash_registers_opening_balance CHECK (opening_balance >= 0)');

        Schema::create('treasury_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            // Au-delà, une sortie d'argent attend la validation de la direction.
            $table->bigInteger('approval_threshold')->default(50000);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE treasury_settings ADD CONSTRAINT treasury_settings_threshold CHECK (approval_threshold >= 0)');

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            // Compte de charge SYSCOHADA (6051 eau, 6052 électricité…).
            $table->string('account_code', 10);
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('cash_operations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30);
            // expense, salary, advance, transfer.
            $table->string('type', 15);
            $table->string('status', 20);
            $table->unsignedBigInteger('cash_register_id');
            // Virement : caisse qui reçoit.
            $table->unsignedBigInteger('destination_register_id')->nullable();
            $table->bigInteger('amount');
            $table->unsignedBigInteger('expense_category_id')->nullable();
            // Salaire, avance : membre du personnel (ou bénéficiaire libre).
            $table->unsignedBigInteger('staff_member_id')->nullable();
            $table->string('beneficiary', 150)->nullable();
            // Salaire : mois payé (2026-11).
            $table->char('period', 7)->nullable();
            $table->string('description', 255);
            // Pièce justificative (n° de facture du fournisseur…).
            $table->string('reference', 100)->nullable();
            $table->date('occurred_on');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejection_reason')->nullable();
            // Virement : réception à l'arrivée, après comptage.
            $table->bigInteger('received_amount')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('received_at')->nullable();
            // Avance : justification (dépensé, rendu).
            $table->bigInteger('spent_amount')->nullable();
            $table->bigInteger('returned_amount')->nullable();
            $table->foreignId('settled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('settled_at')->nullable();
            $table->string('settlement_note')->nullable();
            // Annulation (contre-passation).
            $table->foreignId('reversed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason')->nullable();
            $table->timestamps();

            $table->foreign(['tenant_id', 'cash_register_id'])->references(['tenant_id', 'id'])->on('cash_registers')->restrictOnDelete();
            $table->foreign(['tenant_id', 'destination_register_id'])->references(['tenant_id', 'id'])->on('cash_registers')->restrictOnDelete();
            $table->foreign(['tenant_id', 'expense_category_id'])->references(['tenant_id', 'id'])->on('expense_categories')->restrictOnDelete();
            $table->foreign(['tenant_id', 'staff_member_id'])->references(['tenant_id', 'id'])->on('staff_members')->restrictOnDelete();
            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'occurred_on']);
        });

        DB::statement('ALTER TABLE cash_operations ADD CONSTRAINT cash_operations_amount CHECK (amount > 0)');
        DB::statement("ALTER TABLE cash_operations ADD CONSTRAINT cash_operations_type CHECK (type IN ('expense', 'salary', 'advance', 'transfer'))");
        DB::statement("ALTER TABLE cash_operations ADD CONSTRAINT cash_operations_status CHECK (status IN ('pending_approval', 'rejected', 'done', 'outstanding', 'settled', 'in_transit', 'received', 'cancelled', 'reversed'))");
        DB::statement("ALTER TABLE cash_operations ADD CONSTRAINT cash_operations_transfer CHECK ((type = 'transfer') = (destination_register_id IS NOT NULL))");
        DB::statement("ALTER TABLE cash_operations ADD CONSTRAINT cash_operations_salary CHECK (type <> 'salary' OR (staff_member_id IS NOT NULL AND period IS NOT NULL))");
        DB::statement("ALTER TABLE cash_operations ADD CONSTRAINT cash_operations_category CHECK (type NOT IN ('expense', 'advance') OR expense_category_id IS NOT NULL)");
        DB::statement('ALTER TABLE cash_operations ADD CONSTRAINT cash_operations_settlement CHECK (spent_amount IS NULL OR (spent_amount >= 0 AND returned_amount >= 0))');

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('entry_date');
            // N° de pièce : reçu (R2026-00001), opération (OP2026-00001)…
            $table->string('reference', 30);
            $table->string('label', 255);
            $table->nullableMorphs('source');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'entry_date']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('journal_entry_id');
            $table->string('account_code', 10);
            $table->string('account_label', 150);
            // Ligne qui fait bouger le solde d'une caisse.
            $table->unsignedBigInteger('cash_register_id')->nullable();
            $table->bigInteger('debit')->default(0);
            $table->bigInteger('credit')->default(0);
            $table->timestamps();

            $table->foreign(['tenant_id', 'journal_entry_id'])->references(['tenant_id', 'id'])->on('journal_entries')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'cash_register_id'])->references(['tenant_id', 'id'])->on('cash_registers')->restrictOnDelete();
            $table->index(['tenant_id', 'cash_register_id']);
            $table->index(['tenant_id', 'account_code']);
        });

        DB::statement('ALTER TABLE journal_lines ADD CONSTRAINT journal_lines_amounts CHECK (debit >= 0 AND credit >= 0 AND (debit = 0) <> (credit = 0))');
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('cash_operations');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('treasury_settings');

        Schema::table('cash_registers', function (Blueprint $table) {
            $table->dropForeign(['tenant_id', 'site_id']);
            $table->dropColumn(['site_id', 'kind', 'account_code', 'opening_balance']);
        });
        DB::statement('ALTER TABLE cash_registers DROP CONSTRAINT IF EXISTS cash_registers_kind');
        DB::statement('ALTER TABLE cash_registers DROP CONSTRAINT IF EXISTS cash_registers_opening_balance');

        Schema::table('school_classes', function (Blueprint $table) {
            $table->dropForeign(['tenant_id', 'site_id']);
            $table->dropIndex(['tenant_id', 'site_id']);
            $table->dropColumn('site_id');
        });

        Schema::dropIfExists('sites');
    }
};
