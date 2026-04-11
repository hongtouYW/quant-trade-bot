<?php

namespace app\index\model;

use app\service\ElasticsearchService;
use think\Model;

class ComicTran extends Model
{
    protected $name = 'manhua_trans';
    

    protected static function init()
    {
        self::afterWrite(function ($comicTran) {
            $langCode = $comicTran['lang_code'] ?? null;
            if (!$langCode) {
                return;
            }

            ElasticsearchService::indexManhua([
                'id' => $comicTran['manhua_id'],
                'title' => $comicTran['title'],
                'desc' => $comicTran['desc']
            ], $langCode);
        });

        self::afterDelete(function ($comicTran) {
            $langCode = $comicTran['lang_code'] ?? null;
            if (!$langCode) {
                return;
            }

            ElasticsearchService::deleteManhua($comicTran['manhua_id'], $langCode);
        });
    }
}
