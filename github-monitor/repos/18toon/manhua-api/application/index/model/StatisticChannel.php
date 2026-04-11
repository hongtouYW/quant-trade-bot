<?php

namespace app\index\model;
use think\Model;

class StatisticChannel extends Model
{
    protected $name = 'statistic_channels';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $dateFormat = 'Y-m-d H:i:s';

    protected $fillable = [
        'channel_id',
        'name',
        'secret_key',
        'description',
    ];


    public static function getByChannelId(int $channelId)
    {
        return self::where('channel_id', $channelId)->find();
    }

    public static function getByName(string $name)
    {
        return self::where('name', $name)->find();
    }

    public static function getById(int $id)
    {
        return self::find($id);
    }
}
