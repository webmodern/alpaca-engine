<?php

namespace App\Filament\Clusters\Access\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('roles.name')->label(__('resource.user.roles'))
                     ->formatStateUsing(fn($state): string => $state ? Str::headline($state) : __('resource.user.no_roles'))
                     ->colors(['info'])
                     ->badge()
                     ->tooltip(__('resource.user.roles_tooltip')),
                TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
//                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('email_verified_at')
                     ->label('Email verification')
                     ->nullable()
                     ->placeholder('All users')
                     ->trueLabel('Verified users')
                     ->falseLabel('Not verified users'),
                Filter::make('created_at')
                      ->schema([
                          DatePicker::make('created_from'),
                          DatePicker::make('created_until'),
                      ])
                      ->query(function (Builder $query, array $data): Builder {
                          return $query
                              ->when(
                                  $data['created_from'],
                                  fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                              )
                              ->when(
                                  $data['created_until'],
                                  fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                              );
                      })
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
