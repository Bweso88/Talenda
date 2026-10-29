<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Caisses, sessions de caisse et encaissements — docs/PRODUCT_ARCHITECTURE.md §8.4.
 *
 * Un paiement n'est jamais supprimé ni modifié : une erreur se corrige en
 * l'annulant (statut reversed, avec date, auteur et motif). Le reçu
 * d'origine reste dans l'historique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('cash_register_id');
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('opened_at');
            $table->bigInteger('opening_float')->default(0);
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            // Espèces attendues (fond + encaissements en espèces) et comptées.
            $table->bigInteger('expected_cash')->nullable();
            $table->bigInteger('counted_cash')->nullable();
            $table->string('closing_note')->nullable();
            $table->timestamps();

            $table->foreign(['tenant_id', 'cash_register_id'])->references(['tenant_id', 'id'])->on('cash_registers')->restrictOnDelete();
            $table->unique(['tenant_id', 'id']);
        });

        DB::statement('ALTER TABLE cash_sessions ADD CONSTRAINT cash_sessions_float CHECK (opening_float >= 0)');
        // Une seule session ouverte par caisse.
        DB::statement('CREATE UNIQUE INDEX cash_sessions_one_open_per_register ON cash_sessions (cash_register_id) WHERE closed_at IS NULL');

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('cash_session_id')->nullable();
            $table->string('receipt_number', 30);
            $table->bigInteger('amount');
            // cash, mobile_money, bank_transfer, cheque.
            $table->string('method', 20);
            // Référence de la transaction (mobile money, virement, chèque).
            $table->string('reference', 100)->nullable();
            $table->string('payer_name', 150)->nullable();
            $table->date('paid_on');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 15)->default('valid');
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('reversal_reason')->nullable();
            $table->timestamps();

            $table->foreign(['tenant_id', 'invoice_id'])->references(['tenant_id', 'id'])->on('invoices')->restrictOnDelete();
            $table->foreign(['tenant_id', 'cash_session_id'])->references(['tenant_id', 'id'])->on('cash_sessions')->restrictOnDelete();
            $table->unique(['tenant_id', 'receipt_number']);
            $table->index(['tenant_id', 'invoice_id', 'status']);
            $table->index(['tenant_id', 'paid_on']);
        });

        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_positive CHECK (amount > 0)');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_method CHECK (method IN ('cash', 'mobile_money', 'bank_transfer', 'cheque'))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_reversal CHECK (
            (status = 'valid' AND reversed_at IS NULL)
            OR (status = 'reversed' AND reversed_at IS NOT NULL AND reversal_reason IS NOT NULL))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_reference_required CHECK (method = 'cash' OR reference IS NOT NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('cash_sessions');
        Schema::dropIfExists('cash_registers');
    }
};
