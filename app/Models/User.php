<?php

namespace App\Models;

 use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Collection;
use Illuminate\Support\Arr;


class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return TRUE;
        return $this->hasVerifiedEmail();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'password'])
            ->logOnlyDirty()
            ->useLogName('User')
//            ->setDescriptionForEvent(fn(string $eventName) => "This model has been {$eventName}")
            ->setDescriptionForEvent(function (string $eventName) {
                if ($eventName === 'created') {
                    return 'User created.';
                } elseif ($eventName === 'updated') {
                    if ( Activity::all()->last() ) {
                        return 'User updated ' . collect(Activity::all()->last()->properties->first())->keys()->implode(', ') . '.';
                    } else {
                        return 'User updated.';
                    }
                } elseif ($eventName === 'deleted') {
                    return 'User deleted.';
                }

                return 'User ' . $eventName . '.';
            })
            ->dontSubmitEmptyLogs();
        // Chain fluent methods for configuration options
    }

}
