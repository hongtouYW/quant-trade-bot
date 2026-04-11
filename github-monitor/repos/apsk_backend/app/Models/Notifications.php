<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notifications extends Model
{
    protected $table = 'tbl_notification';
    protected $primaryKey = 'notification_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'notification_id',
        'sender_id',
        'sender_type',
        'recipient_id',
        'recipient_type',
        'notification_type',
        'title',
        'notification_desc',
        'is_read',
        'agent_id',
        'firebase_error',
        'status',
        'delete',
    ];

    // public function Sender()
    // {
    //     return $this->morphTo(__FUNCTION__, 'sender_type', 'sender_id');
    // }

    // public function Recipient()
    // {
    //     return $this->morphTo(__FUNCTION__, 'recipient_type', 'recipient_id');
    // }

    public function Sender()
    {
        return $this->morphTo();
    }

    public function Recipient()
    {
        return $this->morphTo();
    }

    public function Agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    protected static function booted()
    {
        static::created(function ($notification) {
            try {
                $notification->loadMissing('Recipient');
                if (!$notification->Recipient) {
                    $notification->updateQuietly([
                        'status' => -1,
                        'firebase_error' => 'firebase.invalid_recipient',
                        'updated_on' => now(),
                    ]);
                    return;
                }
                if (!$notification->Recipient->devicekey) {
                    $notification->updateQuietly([
                        'status' => -1,
                        'firebase_error' => 'firebase.invalid_devicekey',
                        'updated_on' => now(),
                    ]);
                    return;
                }
                $result = \Firebasehelper::sendToToken(
                    $notification->Recipient->devicekey,
                    __($notification->title),
                    NotificationDescDetail($notification->notification_desc),
                    [
                        'notification_id' => $notification->notification_id,
                        'type' => $notification->notification_type,
                    ]
                );
                $notification->updateQuietly([
                    'status' => $result['status'] ? 1 : -1,
                    'firebase_error' => $result['message'],
                    'updated_on' => now(),
                ]);
            } catch (\Throwable $e) {
                $notification->updateQuietly([
                    'status' => -1,
                    'firebase_error' => $e->getMessage(),
                    'updated_on' => now(),
                ]);
                try {
                    Firebaseerror::create([
                        'request' => json_encode([
                            'notification_id' => $notification->notification_id,
                            'recipient_id' => $notification->recipient_id,
                        ], JSON_UNESCAPED_SLASHES),
                        'response' => $e->getMessage(),
                        'status' => 1,
                        'delete' => 0,
                        'created_on' => now(),
                        'updated_on' => now(),
                    ]);
                } catch (\Throwable $logError) {
                    \Log::critical('Firebaseerror logging failed: '.$logError->getMessage());
                }
            }
        });
    }
}
