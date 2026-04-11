<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Shoppin extends Model
{
    use HasFactory;

    protected $table = 'tbl_shoppin';
    protected $primaryKey = 'shoppin_id';
    public $incrementing = false;
    protected $keyType = 'string';
    const CREATED_AT = 'created_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'shoppin_id',
        'shop_id',
        'manager_id',
    ];
}