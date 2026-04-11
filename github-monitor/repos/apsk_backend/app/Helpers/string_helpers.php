<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Models\Gamemember;
use App\Models\Member;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

if (! function_exists('NotificationDesc')) {
    /**
     * Create a JSON-encoded notification template with optional data.
     *
     * @param string $template
     * @param array $data
     * @return string
     */
    function NotificationDesc(string $template, array $data = []): string
    {
        $notificationDesc = [
            'template' => $template,
            'data' => $data ?? [],
        ];
        return json_encode($notificationDesc);
    }
}

if (! function_exists('NotificationDescDetail')) {
    /**
     * Decode a notification JSON string and translate it.
     *
     * @param string|null $notificationDesc
     * @return string
     */
    function NotificationDescDetail(?string $notificationDesc): string
    {
        if (!$notificationDesc) {
            return "";
        }
        $decoded = json_decode($notificationDesc, true);
        if (!is_array($decoded) || !isset($decoded['template'])) {
            return "";
        }
        $data = $decoded['data'] ?? [];
        // Ensure $data is always an array
        if (!is_array($data)) {
            $data = [];
        }
        $message = __($decoded['template'], $data);
        // ✅ Convert literal \n into actual newline
        return str_replace('\\n', "\n", $message);
    }
}

if (!function_exists('OTPEmail')) {
    function OTPEmail(string $toEmail, string $otp) 
    {
        $data = [
            'subject' => __('messages.otp_title'),
            'otp' => $otp,
        ];
        return sendDynamicEmail(
            $toEmail, 
            "emails.otp", 
            $data
        );
    }
}

if (!function_exists('sendDynamicEmail')) {
    /**
     * Sends an email using a specified Blade view and dynamic data.
     *
     * @param string $toEmail The recipient's email address.
     * @param string $view The Blade view path (e.g., 'emails.notification').
     * @param array $data An associative array of data to pass to the view.
     * Must include 'subject'. Can include 'recipientName', 'bodyContent', etc.
     * @param array $attachments Optional: An array of file paths to attach.
     * @return array
     */
    function sendDynamicEmail(string $toEmail, string $view, array $data, array $attachments = [])
    {
        try {
            // Ensure subject is provided in the data array for the email header
            $subject = $data['subject'] ?? 'Notification';
            Mail::send($view, $data, function ($message) use ($toEmail, $subject, $attachments) {
                $message->to($toEmail)
                        ->subject($subject);
                // Add attachments if provided
                foreach ($attachments as $filePath) {
                    if (file_exists($filePath)) {
                        $message->attach($filePath);
                    } else {
                        // Log or handle error if file does not exist
                        error_log("Attachment file not found: " . $filePath);
                    }
                }
            });
            return [
                'status' => true,
                'message' => __('messages.email_success'),
                'error' => "",
            ];
        } catch (Swift_TransportException $e) {
            // Swift_TransportException is for older Laravel versions (before Laravel 9)
            // or if you're still using Swift Mailer.
            \Log::error("Email sending failed (Swift_TransportException): " . $e->getMessage(), ['email' => $toEmail]);
            return [
                'status' => false,
                'message' => __('messages.email_failed_transport'), // e.g., "Email sending failed due to transport error."
                'error' => $e->getMessage(),
            ];
        } catch (TransportExceptionInterface $e) {
            // TransportExceptionInterface is for Laravel 9+ (Symfony Mailer)
            \Log::error("Email sending failed (TransportExceptionInterface): " . $e->getMessage(), ['email' => $toEmail]);
            return [
                'status' => false,
                'message' => __('messages.email_failed_transport'),
                'error' => $e->getMessage(),
            ];
        } catch (\Exception $e) { // Catch any other unexpected exceptions
            \Log::error("An unexpected error occurred while sending email: " . $e->getMessage(), ['email' => $toEmail]);
            return [
                'status' => false,
                'message' => __('messages.unexpected_error'), // Ensure this translation exists
                'error' => $e->getMessage(),
            ];
        }
    }
}

if (!function_exists('generateRandomPassword')) {
    function generateRandomPassword($length = 6) {
        if ($length < 2) {
            // A password of length less than 2 cannot guarantee both an alphabet and an uppercase character.
            // Adjust the length or handle this case as an error.
            $length = 6; // Default to 6 if length is too short
        }

        $lowercaseChars = 'abcdefghijklmnopqrstuvwxyz';
        $uppercaseChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $numericChars = '0123456789';
        $specialChars = '!@#$%^&*()_+-=[]{}|;:,.<>?';

        $allChars = $lowercaseChars . $uppercaseChars . $numericChars . $specialChars;
        $password = '';

        // Ensure at least one uppercase character
        $password .= $uppercaseChars[rand(0, strlen($uppercaseChars) - 1)];

        // Ensure at least one lowercase character (this also covers "at least 1 alphabet")
        $password .= $lowercaseChars[rand(0, strlen($lowercaseChars) - 1)];

        // Fill the remaining length with random characters from all available sets
        for ($i = 0; $i < $length - 2; $i++) {
            $password .= $allChars[rand(0, strlen($allChars) - 1)];
        }

        // Shuffle the password to randomize the position of the guaranteed characters
        return str_shuffle($password);
    }
}

if (!function_exists('generateRandomPasswordNoSpecial')) {
    function generateRandomPasswordNoSpecial($length = 6) {
        if ($length < 2) {
            // Minimum requirement is at least 2 characters (one uppercase + one lowercase)
            $length = 6;
        }

        $lowercaseChars = 'abcdefghijklmnopqrstuvwxyz';
        $uppercaseChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $numericChars   = '0123456789';

        // Exclude special characters
        $allChars = $lowercaseChars . $uppercaseChars . $numericChars;
        $password = '';

        // Ensure at least one uppercase character
        $password .= $uppercaseChars[rand(0, strlen($uppercaseChars) - 1)];

        // Ensure at least one lowercase character
        $password .= $lowercaseChars[rand(0, strlen($lowercaseChars) - 1)];

        // Fill the rest
        for ($i = 0; $i < $length - 2; $i++) {
            $password .= $allChars[rand(0, strlen($allChars) - 1)];
        }

        return str_shuffle($password);
    }
}

if (!function_exists('generateRandomPasswordSimple')) {
    function generateRandomPasswordSimple($length = 6) {
        // characters allowed: lowercase letters + numbers
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $password;
    }
}

if (!function_exists('generatePasswordPairFormat')) {
    function generatePasswordPairFormat($pairs = 3)
    {
        $pairs = max(1, min(10, (int) $pairs)); // max 10 unique digits (0-9)

        $digits = range(0, 9);
        shuffle($digits); // randomize order

        $numbers = '';

        for ($i = 0; $i < $pairs; $i++) {
            $numbers .= str_repeat($digits[$i], 2);
        }

        return $numbers;
    }
}

if (!function_exists('generatePasswordAbFormat')) {
    function generatePasswordAbFormat($digitLength = 6)
    {
        if ($digitLength < 1) {
            $digitLength = 6;
        }

        $numbers = '';

        for ($i = 0; $i < $digitLength; $i++) {
            $numbers .= rand(0, 9);
        }

        return 'Ab' . $numbers;
    }
}

if (!function_exists('generatePasswordAbPairFormat')) {
    function generatePasswordAbPairFormat($pairs = 3)
    {
        $pairs = max(1, min(10, (int) $pairs)); // max 10 unique digits (0-9)

        $digits = range(0, 9);
        shuffle($digits); // randomize order

        $numbers = '';

        for ($i = 0; $i < $pairs; $i++) {
            $numbers .= str_repeat($digits[$i], 2);
        }

        return 'Ab' . $numbers;
    }
}

if (!function_exists('storeFile')) {
    function storeFile(Request $request, $filename, $field, $path )
    {
        $file = $request->file($field);
        if ($file && $file->isValid()) {
            $extension = $file->getClientOriginalExtension();
            $customName = $filename . '.' . $extension;
            return $file->storeAs($path, $customName, 'public');
        } else {
            return null;
        }
    }
}

if (!function_exists('generateMember')) {
    function generateMember()
    {
        $countplayer = Member::count();
        return '911user' . str_pad( $countplayer + 1, 7, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('generatePlayer')) {
    function generatePlayer()
    {
        $count = 0;
        do {
            $countplayer = Gamemember::count() + $count;
            $login = 'G' . str_pad($countplayer + 1, 7, '0', STR_PAD_LEFT);
            $exists = Gamemember::where('login', $login)->exists();
            if ($exists) {
                $count++;
            }
        } while ($exists);
        return $login;
    }
}

if (! function_exists('encryptPassword')) {
    /**
     * Encrypts a string using Laravel's Crypt facade.
     *
     * @param string $data
     * @return string
     */
    function encryptPassword(string $data): string
    {
        return Crypt::encryptString($data);
    }
}

if (! function_exists('decryptPassword')) {
    /**
     * Decrypts an encrypted string using Laravel's Crypt facade.
     *
     * @param string $encryptedData
     * @return string|null
     */
    function decryptPassword(string $encryptedData): ?string
    {
        try {
            return Crypt::decryptString($encryptedData);
        } catch (DecryptException $e) {
            // Handle decryption failure (e.g., return null or throw an exception)
            return null;
        }
    }
}

if (! function_exists('LogDesc')) {
    function LogDesc(string $name, string $type, string $template, string $target = null): ?string
    {
        return json_encode(
            [
                'name' => $name,
                'type' => $type,
                'template' => "log.".$template,
                'target' => $target,
            ]
        );
    }
}

if (! function_exists('LogDescTranslate')) {
    function LogDescTranslate( string $json_log_desc = null )
    {
        if ( !$json_log_desc ) {
            return [
                'name' => null,
                'type' => null,
                'template' => null,
                'target' => null,
            ];
        }
        try {
            $log_desc = json_decode( $json_log_desc);
            $log_desc->template = __( $log_desc->template );
            return $log_desc;
        } catch (\Throwable $th) {
            return [
                'name' => null,
                'type' => null,
                'template' => null,
                'target' => null,
            ];
        }
    }
}