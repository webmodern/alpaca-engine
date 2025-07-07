<?php

namespace App\Filament\Clusters\Access\Resources\Users\Pages;

use App\Filament\Clusters\Access\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}
