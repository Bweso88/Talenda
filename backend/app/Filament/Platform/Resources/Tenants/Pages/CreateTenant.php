<?php

namespace App\Filament\Platform\Resources\Tenants\Pages;

use App\Filament\Platform\Resources\Tenants\TenantResource;
use App\Modules\Core\Actions\ProvisionTenant;
use App\Modules\Core\Support\PhoneNumber;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(ProvisionTenant::class)->handle(
            school: [
                'name' => $data['name'],
                'slug' => $data['slug'],
                'country_code' => $data['country_code'],
            ],
            admin: [
                'name' => $data['admin_name'],
                'email' => $data['admin_email'] ?? null,
                'phone' => filled($data['admin_phone'] ?? null)
                    ? PhoneNumber::normalize($data['admin_phone'], $data['country_code'])
                    : null,
                'password' => $data['admin_password'],
            ],
            actor: Filament::auth()->user(),
        );
    }
}
