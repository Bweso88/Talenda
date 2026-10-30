<?php

namespace App\Filament\School\Resources\Students\RelationManagers;

use App\Modules\SchoolLife\Enums\AttendanceStatus;
use App\Modules\SchoolLife\Models\AttendanceRecord;
use App\Modules\SchoolLife\Models\AttendanceSession;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Absences et retards de l'élève, du plus récent au plus ancien.
 */
class AttendanceRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'attendanceRecords';

    protected static ?string $title = 'Absences et retards';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Filament::auth()->user()?->can('attendance.manage') ?? false;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => AttendanceRecord::selectJustified($query)
                ->with(['session.slot', 'session.schoolClass'])
                ->orderByDesc(AttendanceSession::query()->select('held_on')->whereColumn('attendance_sessions.id', 'attendance_records.attendance_session_id')))
            ->columns([
                TextColumn::make('session.held_on')->label('Date')->date('d/m/Y'),
                TextColumn::make('session.slot.name')->label('Créneau'),
                TextColumn::make('session.schoolClass.name')->label('Classe'),
                TextColumn::make('status')->label('Type')->badge()
                    ->formatStateUsing(fn (AttendanceRecord $record): string => $record->status === AttendanceStatus::Late && $record->late_minutes
                        ? "Retard ({$record->late_minutes} min)"
                        : $record->status->getLabel()),
                IconColumn::make('is_justified')->label('Justifiée')->boolean()
                    ->state(fn (AttendanceRecord $record): ?bool => $record->status === AttendanceStatus::Absent ? (bool) $record->is_justified : null),
                TextColumn::make('note')->label('Remarque')->placeholder('—'),
            ]);
    }
}
