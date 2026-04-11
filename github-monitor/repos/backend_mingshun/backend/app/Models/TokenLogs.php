<?php

namespace App\Models;

use App\Trait\Log;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TokenLogs extends Model
{
    use HasFactory;
    use Log;

    public const TYPE = [
        '1' => '青色',
        '2' => '红色', 
        '3' => '灰色', 
        '4' => '白色',     
    ];

    public const CALENDARREVIEWERCLASS = [
        '1' => 'day-green',
        '2' => 'day-red', 
        '3' => 'day-grey', 
        '4' => '',     
    ];

    public const CALENDARMORESUPERADMINCLASS = 'badge bg-dark';
    public const CALENDARSUPERADMINCLASS = [
        '1' => 'badge bg-success',
        '2' => 'badge bg-danger', 
        '3' => 'badge bg-secondary', 
        '4' => 'badge bg-light',     
    ];

    protected $fillable = [
        'user_id',
        'type',
        'created_at',
        'extra'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
