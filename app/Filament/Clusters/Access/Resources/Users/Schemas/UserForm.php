<?php

namespace App\Filament\Clusters\Access\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->email()
                    ->required(),
                DateTimePicker::make('email_verified_at'),
                Select::make('roles')
                   ->relationship('roles', 'name')
                   ->multiple()
                   ->preload()
                   ->searchable(),
            ]);
    }
}
