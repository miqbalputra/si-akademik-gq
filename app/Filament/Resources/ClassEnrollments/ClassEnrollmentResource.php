<?php

namespace App\Filament\Resources\ClassEnrollments;

use App\Filament\Concerns\HasRoleBasedResourceAccess;
use App\Filament\Resources\ClassEnrollments\Pages\CreateClassEnrollment;
use App\Filament\Resources\ClassEnrollments\Pages\EditClassEnrollment;
use App\Filament\Resources\ClassEnrollments\Pages\ListClassEnrollments;
use App\Filament\Resources\ClassEnrollments\Schemas\ClassEnrollmentForm;
use App\Filament\Resources\ClassEnrollments\Tables\ClassEnrollmentsTable;
use App\Models\ClassEnrollment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ClassEnrollmentResource extends Resource
{
    use HasRoleBasedResourceAccess;

    protected const NAVIGATION_GROUP = 'Struktur Kelas';

    protected const NAVIGATION_LABEL = 'Anggota Kelas';

    protected const NAVIGATION_SORT = 30;

    protected const VIEW_ROLES = ['admin', 'kabag_diniyyah', 'kepala_sekolah'];

    protected const MANAGE_ROLES = ['admin'];

    protected static ?string $model = ClassEnrollment::class;

    protected static ?string $modelLabel = 'Pendaftaran Kelas';
    protected static ?string $pluralModelLabel = 'Pendaftaran Kelas';


    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return ClassEnrollmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClassEnrollmentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClassEnrollments::route('/'),
            'create' => CreateClassEnrollment::route('/create'),
            'edit' => EditClassEnrollment::route('/{record}/edit'),
        ];
    }
}
