<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Carbon\Carbon;
use App\Http\Helper;
use App\Trait\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Ftp extends Model
{
    use Log;
    use HasFactory;
    protected $fillable = [
        'nickname',
        'user_id',
        'server_id',
        'password',
        'path'
    ];
    public const TITLE = 'FTP';
    public const CRUD_ROUTE_PART = 'ftps';
    public const FTP_DOMAIN = 'http://ftp.minggogogo.com:9090';

    protected function createdAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->timezone(config('app.timezone'))->format('Y-m-d H:i:s') : '',
        );
    }

    protected function updatedAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->timezone(config('app.timezone'))->format('Y-m-d H:i:s') : '',
        );
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function servers()
    {
        return $this->belongsTo(Server::class, 'server_id');
    }

    public function scopeSearch($query, Request $request)
    {
        if ($request->user_id !== null) {
            $query->where(function ($q) use ($request) {
                $q->where('user_id', $request->user_id);
            });
        }

        if ($request->server_id !== null) {
            $query->whereHas('servers', function ($q) use ($request) {
                $q->where("servers.id", $request->server_id);
            });
        }

        if(Auth::user()->isUploader()){
            $query->where(function ($q) use ($request) {
                $q->where('user_id', Auth::user()->id);
            });
        }

        return $query;
    }

    public static function createFtpNoError($username, $password, $path)
    {
        $response = Helper::sendResourceRequestNoError(
            self::FTP_DOMAIN . "/api/create/ftp/account",
            json_encode([
                'username' => $username,
                'password' => $password
            ]),
            array('Content-Type: application/json'),
            'Crete Ftp'
        );
        $response = json_decode($response);
        if (($response->code ?? 0) == 200) {
            if ($response->data ?? '') {
                if ($response->data->path ?? '') {
                    return $response->data->path;
                }
            }
        }elseif (($response->code ?? 0) == 400) {
            if (($response->msg ?? '') == 'ftp user already exist') {
                return $path;
            }
        }
        return false;
    }

    public static function createFtp($username, $password)
    {
        $response = Helper::sendResourceRequest(
            self::FTP_DOMAIN . "/api/create/ftp/account",
            json_encode([
                'username' => $username,
                'password' => $password
            ]),
            array('Content-Type: application/json'),
            'Crete Ftp'
        );
        $response = json_decode($response);
        if (($response->code ?? 0) == 200) {
            if ($response->data ?? '') {
                if ($response->data->path ?? '') {
                    return $response->data->path;
                }
            }
        }
        return false;
    }

    public static function editFtp($username, $password)
    {
        $response = Helper::sendResourceRequest(
            self::FTP_DOMAIN . "/api/update/ftp/account",
            json_encode([
                'username' => $username,
                'password' => $password
            ]),
            array('Content-Type: application/json'),
            'Edit Ftp'
        );
        $response = json_decode($response);
        if (($response->code ?? 0) == 200) {
            return true;
        }
        return false;
    }

    public static function deleteFtp($username)
    {
        $response = Helper::sendResourceRequest(
            self::FTP_DOMAIN . "/api/delete/ftp/account",
            json_encode([
                'username' => $username,
            ]),
            array('Content-Type: application/json'),
            'Delete Ftp'
        );
        $response = json_decode($response);
        if (($response->code ?? 0) == 200) {
            return true;
        }
        return false;
    }
}
