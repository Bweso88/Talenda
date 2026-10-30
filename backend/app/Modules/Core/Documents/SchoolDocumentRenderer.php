<?php

declare(strict_types=1);

namespace App\Modules\Core\Documents;

use App\Modules\Core\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Rendu des documents officiels de l'école en PDF (dompdf : PHP pur, sans
 * navigateur à installer sur le serveur).
 *
 * Les images (logo, photos d'élèves) sont intégrées en base64 : dompdf
 * n'accède jamais au réseau ni aux fichiers par URL.
 */
final class SchoolDocumentRenderer
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function render(Tenant $tenant, string $view, array $data, string $orientation = 'portrait', string $paper = 'a4'): string
    {
        $tenant->loadMissing('settings');

        return Pdf::loadView($view, [
            ...$data,
            'tenant' => $tenant,
            'settings' => $tenant->settings,
            'logo' => $this->imageDataUri('public', $tenant->settings?->logo_path),
        ])
            ->setPaper($paper, $orientation)
            // N'embarquer que les caractères utilisés : PDF bien plus légers.
            ->setOption('isFontSubsettingEnabled', true)
            ->setOption('isRemoteEnabled', false)
            ->output();
    }

    /**
     * Image d'un disque Laravel en « data URI », ou null si absente.
     */
    public function imageDataUri(string $disk, ?string $path): ?string
    {
        if ($path === null || ! Storage::disk($disk)->exists($path)) {
            return null;
        }

        $mime = Storage::disk($disk)->mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) Storage::disk($disk)->get($path));
    }
}
