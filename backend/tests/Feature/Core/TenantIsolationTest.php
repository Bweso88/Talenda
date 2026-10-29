<?php

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Tenancy\BelongsToTenant;
use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Isolation multi-tenant (docs/PRODUCT_ARCHITECTURE.md §7), testée sur un
 * modèle d'école minimal créé pour l'occasion.
 */
class IsolationProbe extends Model
{
    use BelongsToTenant;

    protected $table = 'isolation_probes';

    protected $guarded = [];
}

beforeEach(function () {
    Schema::create('isolation_probes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('tenant_id')->constrained();
        $table->string('label');
        $table->timestamps();
    });

    $this->context = app(TenantContext::class);
    $this->schoolA = Tenant::factory()->create();
    $this->schoolB = Tenant::factory()->create();

    $this->context->run($this->schoolA, fn () => IsolationProbe::create(['label' => 'A']));
    $this->context->run($this->schoolB, fn () => IsolationProbe::create(['label' => 'B']));
});

it('ne renvoie aucune ligne sans école active (isolation fermée par défaut)', function () {
    expect($this->context->check())->toBeFalse()
        ->and(IsolationProbe::count())->toBe(0);
});

it('ne renvoie que les lignes de l\'école active', function () {
    $this->context->set($this->schoolA);

    expect(IsolationProbe::pluck('label')->all())->toBe(['A'])
        ->and(IsolationProbe::where('label', 'B')->exists())->toBeFalse();
});

it('voit toutes les écoles uniquement dans withoutTenant()', function () {
    $labels = $this->context->withoutTenant(fn () => IsolationProbe::orderBy('label')->pluck('label')->all());

    expect($labels)->toBe(['A', 'B'])
        ->and(IsolationProbe::count())->toBe(0);
});

it('renseigne tenant_id à la création depuis l\'école active', function () {
    $probe = $this->context->run($this->schoolB, fn () => IsolationProbe::create(['label' => 'B2']));

    expect($probe->tenant_id)->toBe($this->schoolB->id);
});

it('refuse de créer un modèle d\'école sans école active', function () {
    IsolationProbe::create(['label' => 'orphelin']);
})->throws(LogicException::class);

it('restaure le contexte précédent après run()', function () {
    $this->context->set($this->schoolA);

    $this->context->run($this->schoolB, fn () => null);

    expect($this->context->id())->toBe($this->schoolA->id);
});

it('restaure le contexte même si le traitement échoue', function () {
    $this->context->set($this->schoolA);

    try {
        $this->context->run($this->schoolB, fn () => throw new RuntimeException('échec'));
    } catch (RuntimeException) {
    }

    expect($this->context->id())->toBe($this->schoolA->id);
});
