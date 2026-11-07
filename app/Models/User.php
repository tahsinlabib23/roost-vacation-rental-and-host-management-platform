<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class, 'host_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'guest_id');
    }

    public function guestConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'guest_id');
    }

    public function hostConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'host_id');
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }
}
