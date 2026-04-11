<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Bankaccount extends Model
{
    use HasFactory;

    protected $table = 'tbl_bankaccount';
    protected $primaryKey = 'bankaccount_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'bankaccount_id',
        'member_id',
        'bank_id',
        'bank_account',
        'bank_full_name',
        'fastpay',
        'status',
        'delete',
    ];
    
    public function Bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id', 'bank_id');
    }
}