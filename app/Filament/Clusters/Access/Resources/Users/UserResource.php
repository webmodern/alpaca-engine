<?php

namespace App\Filament\Clusters\Access\Resources\Users;

use App\Filament\Clusters\Access\AccessCluster;
use App\Filament\Clusters\Access\Resources\Users\Pages\CreateUser;
use App\Filament\Clusters\Access\Resources\Users\Pages\EditUser;
use App\Filament\Clusters\Access\Resources\Users\Pages\ListUsers;
use App\Filament\Clusters\Access\Resources\Users\Pages\ViewUser;
use App\Filament\Clusters\Access\Resources\Users\Schemas\UserForm;
use App\Filament\Clusters\Access\Resources\Users\Schemas\UserInfolist;
use App\Filament\Clusters\Access\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Tables\Table;
use Filament\Widgets\AccountWidget;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ListBullet;

    protected static ?string $cluster = AccessCluster::class;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UserInfolist::configure($schema);
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
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
