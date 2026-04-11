<?php

namespace App\Trait;

use App\Http\Helper;
use Illuminate\Support\Facades\Auth;

trait Log
{
    protected static function boot()
    {
        parent::boot();

        static::deleted(function ($model) {
            $data = $model->toArray();
            $id = $data['id'];
            $className = str_replace("App\Models\\", "", get_class($model));
            Helper::saveLog($data, 3, $className, $id);
        });

        static::saved(function ($model) {
            $flag = true;
            $original = $model->getOriginal();
            $className = str_replace("App\Models\\", "", get_class($model));
            if (!empty($original)) {
                $type = 2;
                $flag = false;
                if (!empty($model->getChanges())) {
                    $flag = true;
                    $id = $model->id;
                    $data = $model->getChanges();
                    unset($data['updated_at']);
                }
            } else {
                $type = 1;
                $data = $model->toArray();
                $id = $data['id'];
                unset($data['id']);
            }
            if ($flag) {
                Helper::saveLog($data, $type, $className, $id);
            }
        });
    }
}
