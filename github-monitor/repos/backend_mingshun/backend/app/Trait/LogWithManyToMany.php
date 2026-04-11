<?php

namespace App\Trait;

use App\Http\Helper;

trait LogWithManyToMany
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
    }

    public static function processSaveLog($request, $model, $type, $original=[])
    {
        if($type == 1){
            $data = $model->toArray();
            $change = self::getManyRelationRequest($request);
            $data = self::addManyRelation($data, $change, $original);
            $id = $data['id'];
            unset($data['id']);
        }else{
            $data = $model->getChanges();
            $change = self::getManyRelationRequest($request);
            $data = self::addManyRelation($data, $change, $original);
            unset($data['updated_at']);
            $id = $model->id;
        }
        if(!empty($data)){
            Helper::saveLog($data, $type, str_replace("App\Models\\", "", get_class($model)), $id);
        }
    }

    public static function getManyRelationRequest($request){
        $temp = [];
        foreach(self::BELONGTOMANY as $model){
            if(array_key_exists($model, $request)){
                if(isset($request[$model]) && gettype($request[$model]) != 'array'){
                    $tempValue = [$request[$model]];
                }else{
                    $tempValue = $request[$model];
                }
                $temp[$model] = $tempValue;
            }
        }
        return $temp;
    }

    public static function getManyRelationModel($user){
        $temp = [];
        foreach(self::BELONGTOMANY as $model){
            $temp[$model] = $user->$model?->pluck('id')->toArray();
        }
        return $temp;
    }

    public static function addManyRelation($data, $change, $original){
        foreach(self::BELONGTOMANY as $model){
            if(array_diff($original[$model] ?? [], $change[$model] ?? [])){
                $data[$model] = json_encode($change[$model]);
                continue;
            }
            if(array_diff($change[$model] ?? [], $original[$model] ?? [])){
                $data[$model] = json_encode($change[$model]);
            }
        }
        return $data;
    }
}
