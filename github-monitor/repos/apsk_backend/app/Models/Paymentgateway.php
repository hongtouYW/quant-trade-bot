<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Paymentgateway extends Model
{
    protected $table = 'tbl_paymentgateway';
    protected $primaryKey = 'payment_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'payment_id',
        'payment_name',
        'icon',
        'amount_type',
        'type',
        'min_amount',
        'max_amount',
        'status',
        'delete',
    ];
}
