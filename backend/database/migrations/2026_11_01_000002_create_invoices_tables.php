<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Factures de scolarité — docs/PRODUCT_ARCHITECTURE.md §8.4.
 *
 * Une facture par inscription. Ses lignes ne sont jamais modifiées ni
 * supprimées : une réduction ou une correction est une nouvelle ligne
 * (montant négatif), avec son motif et son auteur.
 *
 * L'échéancier n'est pas stocké : il est calculé à partir des lignes et du
 * plan de paiement choisi (voir InvoiceStatement), ce qui le garde
 * toujours juste après une réduction ou un changement de plan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('enrollment_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('school_year_id');
            $table->unsignedBigInteger('payment_plan_id')->nullable();
            $table->string('number', 30);
            $table->timestamps();

            $table->foreign(['tenant_id', 'enrollment_id'])->references(['tenant_id', 'id'])->on('enrollments')->restrictOnDelete();
            $table->foreign(['tenant_id', 'student_id'])->references(['tenant_id', 'id'])->on('students')->restrictOnDelete();
            $table->foreign(['tenant_id', 'school_year_id'])->references(['tenant_id', 'id'])->on('school_years')->restrictOnDelete();
            $table->unique(['tenant_id', 'id']);
            $table->unique('enrollment_id');
            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'school_year_id']);
        });

        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_payment_plan_foreign
            FOREIGN KEY (tenant_id, payment_plan_id) REFERENCES payment_plans (tenant_id, id)
            ON DELETE SET NULL (payment_plan_id)');

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('invoice_id');
            // fee : frais ; discount : réduction ; adjustment : correction.
            $table->string('kind', 15);
            $table->unsignedBigInteger('fee_type_id')->nullable();
            // Copie du mode de facturation du frais au moment de la ligne.
            $table->string('billing', 10);
            $table->string('label', 150);
            $table->bigInteger('amount');
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(['tenant_id', 'invoice_id'])->references(['tenant_id', 'id'])->on('invoices')->restrictOnDelete();
            $table->foreign(['tenant_id', 'fee_type_id'])->references(['tenant_id', 'id'])->on('fee_types')->restrictOnDelete();
            $table->index(['tenant_id', 'invoice_id']);
        });

        DB::statement("ALTER TABLE invoice_lines ADD CONSTRAINT invoice_lines_kind CHECK (
            (kind = 'fee' AND amount > 0)
            OR (kind = 'discount' AND amount < 0)
            OR (kind = 'adjustment' AND amount <> 0))");
        DB::statement("ALTER TABLE invoice_lines ADD CONSTRAINT invoice_lines_billing CHECK (billing IN ('upfront', 'spread'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};
