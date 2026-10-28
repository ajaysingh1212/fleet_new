<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $fillable = ['registration_number', 'name', 'status', 'details', 'owner_user_id'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }
}
