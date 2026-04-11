<?php

namespace App\Models;

use App\Trait\Log;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostVideo extends Model
{
    use Log;
    use HasFactory;

    protected $fillable = [
        'path',
        'post_id'
    ];
}
