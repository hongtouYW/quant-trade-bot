<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Bank extends Model
{
    use HasFactory;

    protected $table = 'tbl_bank';
    protected $primaryKey = 'bank_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'bank_id',
        'bank_name',
        'fpaybank_id',
        'superpaybankcode',
        'icon',
        'api',
        'status',
        'delete',
    ];
}
