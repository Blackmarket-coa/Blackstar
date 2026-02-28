<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GovernanceSetting extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'federation_council_room_id',
    ];
}
