<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Commissionrank extends Model
{
    use HasFactory;

    protected $table = 'tbl_commissionrank';
    protected $primaryKey = 'commissionrank_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'commissionrank_id',
        'rank',
        'amount',
        'status',
        'delete',
    ];
}
