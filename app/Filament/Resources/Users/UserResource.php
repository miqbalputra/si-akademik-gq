<?php

namespace App\Filament\Resources\Users;

use App\Filament\Concerns\HasRoleBasedResourceAccess;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class UserResource extends Resource
{
    use HasRoleBasedResourceAccess;

    protected const NAVIGATION_GROUP = 'Pengaturan';
    protected const NAVIGATION_LABEL = 'Pengguna';
    protected const NAVIGATION_SORT = 10;

    // Kelola akun login (semua peran) = admin-only.
    protected const VIEW_ROLES = ['admin'];
    protected const MANAGE_ROLES = ['admin'];

    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'Pengguna';
    protected static ?string $pluralModelLabel = 'Akun Pengguna';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
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
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}