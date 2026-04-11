<?php

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Illuminate\Support\Facades\Log;
use App\Models\Firebaseerror;

class Firebasehelper
{
    protected static $messaging = null;

    protected static function messaging()
    {
        if (self::$messaging === null) {
            try {
                $factory = (new Factory)
                    ->withServiceAccount(config('firebase.firebasecredentials'));

                self::$messaging = $factory->createMessaging();
            } catch (\Throwable $e) {
                Log::error('Firebase init failed', [
                    'message' => $e->getMessage(),
                    'env' => app()->environment(),
                ]);
                $request = [
                    'env' => app()->environment(),
                ];
                Firebaseerror::create([
                    'request' => json_encode($request, JSON_UNESCAPED_SLASHES),
                    'response' => $e->getMessage(),
                    'status' => 1,
                    'delete' => 0,
                    'created_on' => now(),
                    'updated_on' => now(),
                ]);
                return null;
            }
        }

        return self::$messaging;
    }

    public static function sendToToken(string $token, string $title, string $body, array $data = []): array
    {
        try {
            $messaging = self::messaging();
            if (!$messaging) {
                Log::error('Firebase messaging instance unavailable', [
                    'message' => 'Firebase messaging instance unavailable',
                    'env' => app()->environment(),
                ]);
                return [
                    'status' => false,
                    'message' => 'firebase.invalid_messaging',
                ];
            }

            $message = CloudMessage::withTarget('token', $token)
                ->withNotification(Notification::create($title, $body))
                ->withData(self::convertDataToString($data));

            $messaging->send($message);
            return [
                'status' => true,
                'message' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('FCM sendToToken error', [
                'message' => $e->getMessage(),
                'env' => app()->environment(),
            ]);
            $request = [
                'env' => app()->environment(),
            ];
            Firebaseerror::create([
                'request' => json_encode($request, JSON_UNESCAPED_SLASHES),
                'response' => $e->getMessage(),
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            return [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    protected static function convertDataToString(array $data): array
    {
        return array_map(fn ($v) => (string) $v, $data);
    }
}
