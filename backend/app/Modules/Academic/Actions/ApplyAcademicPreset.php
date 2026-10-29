<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Modules\Academic\Models\Cycle;
use App\Modules\Academic\Models\Level;
use App\Modules\Academic\Models\Stream;
use App\Modules\Academic\Models\Subject;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Crée la structure scolaire proposée pour le pays de l'école
 * (config/talenda.php, academic_presets) : cycles choisis, leurs niveaux
 * chaînés pour les passages, séries du lycée et matières des cycles.
 *
 * Idempotente : un élément dont le code existe déjà n'est ni dupliqué ni
 * modifié, on peut donc ajouter un cycle plus tard.
 */
final class ApplyAcademicPreset
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    /**
     * Codes des cycles du gabarit du pays, dans l'ordre de scolarité.
     *
     * @return array<string, string> code => nom
     */
    public static function availableCycles(string $countryCode): array
    {
        /** @var array<string, array{name: string}> $cycles */
        $cycles = config("talenda.academic_presets.{$countryCode}.cycles", []);

        return array_map(fn (array $cycle): string => $cycle['name'], $cycles);
    }

    /**
     * @param  list<string>  $cycleCodes
     */
    public function handle(Tenant $tenant, array $cycleCodes): void
    {
        /** @var array{cycles: array<string, array{name: string, levels: list<array{code: string, name: string, exam?: string}>}>, streams: array<string, string>, subjects: list<array{code: string, name: string, cycles: list<string>}>}|null $preset */
        $preset = config("talenda.academic_presets.{$tenant->country_code}");

        if ($preset === null) {
            return;
        }

        $this->tenantContext->run($tenant, function () use ($preset, $cycleCodes): void {
            DB::transaction(function () use ($preset, $cycleCodes): void {
                $previousLevel = null;
                $cyclePosition = 0;
                $levelPosition = 0;

                foreach ($preset['cycles'] as $cycleCode => $cyclePreset) {
                    $cyclePosition++;

                    if (! in_array($cycleCode, $cycleCodes, true)) {
                        // Un cycle non retenu interrompt la chaîne des passages.
                        $previousLevel = null;

                        continue;
                    }

                    $cycle = Cycle::query()->firstOrCreate(
                        ['code' => $cycleCode],
                        ['name' => $cyclePreset['name'], 'position' => $cyclePosition],
                    );

                    foreach ($cyclePreset['levels'] as $levelPreset) {
                        $levelPosition++;

                        $level = Level::query()->firstOrCreate(
                            ['code' => $levelPreset['code']],
                            [
                                'cycle_id' => $cycle->id,
                                'name' => $levelPreset['name'],
                                'position' => $levelPosition,
                                'exam_name' => $levelPreset['exam'] ?? null,
                            ],
                        );

                        if ($previousLevel !== null && $previousLevel->next_level_id === null) {
                            $previousLevel->update(['next_level_id' => $level->id]);
                        }

                        $previousLevel = $level;
                    }
                }

                if (in_array('LYCEE', $cycleCodes, true)) {
                    foreach ($preset['streams'] as $code => $name) {
                        Stream::query()->firstOrCreate(['code' => $code], ['name' => $name]);
                    }
                }

                foreach ($preset['subjects'] as $subjectPreset) {
                    if (array_intersect($subjectPreset['cycles'], $cycleCodes) !== []) {
                        Subject::query()->firstOrCreate(
                            ['code' => $subjectPreset['code']],
                            ['name' => $subjectPreset['name']],
                        );
                    }
                }
            });
        });
    }
}
