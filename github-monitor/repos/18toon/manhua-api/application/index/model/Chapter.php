<?php

namespace app\index\model;

use app\lib\exception\BaseException;
use app\traits\HasTranslation;
use think\cache\driver\Redis;
use think\Model;

class Chapter extends Model
{
    use HasTranslation;

    protected $name = 'capter';
    

    protected $hidden = ['manhua_id'];

    public function getUpdateTimeAttr($value)
    {
        return date('Y-m-d H:i:s', intval($value));
    }


    public function getImageAttr($val)
    {
        if (!empty($val)) {
            // $image_url = Config::get('IMAGE_HOST');
            $image_url = '';
            $val = $image_url . $val;
        }
        return $val;
    }

    public function getImagelistAttr($val, $data)
    {
        // $image_url = Config::get('IMAGE_HOST');
        $image_url = '';

        // 如果是字符串才进行 explode
        if (!empty($val) && is_string($val)) {
            $val = explode(",", $val);
        }

        // 如果是数组就继续处理
        if (is_array($val)) {
            // 自然排序
            natsort($val);
            $val = array_values($val);

            // 拼接图片地址前缀
            foreach ($val as $k => $v) {
                $val[$k] = $image_url . $v;
            }
        }

        return $val;
    }

    public function getIsBuyAttr($val, $data)
    {
        $is_buy = 0;
        $count = Record::where('member_id', '=', getUid())->where('capter_id', '=', $data['id'])->count();
        if ($count) {
            $is_buy = 1;
        }
        return $is_buy;
    }

    public function getFullTableName()
    {
        // 获取数据库连接配置
        $config = $this->getConnection()->getConfig();
        $database = $config['database'] ?? '';
        $table = $this->getTable(); // 带前缀
        return "`{$database}`.`{$table}`";
    }

    public static function chapterList($mid, $page, $limit, $order)
    {
        $redis_key = 'comic_chapter_' . $mid . '_' . $order . '_' . $page . '_' . $limit;
        $redis = new Redis();
        $results = $redis->get($redis_key);

        if (!$results) {
            switch ($order) {
                case 1:
                    $order = 'sort asc';
                    break;
                case 2:
                    $order = 'sort desc';
                    break;
            }

            $results = self::field('id,title,image,isvip,update_time,sort,score,manhua_id')->where('switch', '=', 1)->where('manhua_id', '=', $mid)->order($order)
                ->paginate([
                    'list_rows' => $limit,
                    'page' => $page,
                ])->toArray();

            if ($results['data']){
                $comic = Manhua::field('xianmian')->where('id', $mid)->find();
                if ($comic && $comic['xianmian'] == 1) {
                    foreach ($results['data'] as &$chapter) {
                        $chapter['isvip'] = 0; 
                    }
                }
                $redis->set($redis_key, $results, 86400);
            }
        }
        return $results;
    }



    public static function getChaInfo($checkSwitch = true)
    {
        $cid = getInput('cid');
        $chaInfo = self::field('id,manhua_id,switch')->where('id', '=', $cid)->find();

        if (!$chaInfo) {
            throw new BaseException(4001);
        }
        if ($checkSwitch && $chaInfo['switch'] == 0) {
            throw new BaseException(4002);
        }
        return $chaInfo;
    }

    public static function info($cid, $lang)
    {
        $chaQuery = self::alias('c')
            ->field('c.id, c.manhua_id, c.switch, c.isvip, c.score, c.sort')
            ->where('c.id', '=', $cid);

        // 处理漫画章节和翻译
        self::withTranslation($chaQuery, 'c', $lang, ['title', 'imagelist'], 'qiswl_capter_trans', 'capter_id');

        $chaInfo = $chaQuery->find();
        
        // // 过滤广告图片(检查的时间较长)
        // if (!empty($chaInfo['imagelist'])) {
        //     $chaInfo['imagelist'] = filterSameImageFromImagelist(
        //         $chaInfo['imagelist'],
        //         '/notic/testing.jpeg',
        //     );
        // }

        $comicQuery = Manhua::alias('m')
            ->field(Manhua::info_tran_field)
            ->where('m.id', '=', $chaInfo['manhua_id']);

        // 处理漫画和翻译
        self::withTranslation($comicQuery, 'm', $lang, ['title', 'desc', 'keyword', 'last_chapter_title', 'image', 'cover_horizontal'], 'qiswl_manhua_trans', 'manhua_id');

        $comicInfo = $comicQuery->find();

        // 查找前一个章节（sort 小于当前 sort，id 最接近）
        $prevQuery = self::alias('c')
            ->field('c.id, c.manhua_id, c.switch, c.isvip, c.score, c.sort')
            ->where('c.manhua_id', '=', $chaInfo['manhua_id'])
            ->where('c.sort', '<', $chaInfo['sort'])
            ->order('c.sort', 'desc')
            ->limit(1);

        self::withTranslation($prevQuery, 'c', $lang, ['title', 'imagelist'], 'qiswl_capter_trans', 'capter_id');

        $prev = $prevQuery->find();

        // 查找下一个章节（sort 大于当前 sort，id 最接近）
        $nextQuery = self::alias('c')
            ->field('c.id, c.manhua_id, c.switch, c.isvip, c.score, c.sort')
            ->where('c.manhua_id', '=', $chaInfo['manhua_id'])
            ->where('c.sort', '>', $chaInfo['sort'])
            ->order('c.sort', 'asc')
            ->limit(1);

        self::withTranslation($nextQuery, 'c', $lang, ['title', 'imagelist'], 'qiswl_capter_trans', 'capter_id');

        $next = $nextQuery->find();

        $comicXianmian = Manhua::where('id', $chaInfo['manhua_id'])->value('xianmian');

        if ($comicXianmian == 1) {
            $chaInfo['isvip'] = 0;
            if ($prev) {
                $prev['isvip'] = 0;
            }
            if ($next) {
                $next['isvip'] = 0;
            }
        }

        $result = [
            'chaInfo' => $chaInfo,
            'comicInfo' => $comicInfo,
            'prevChaInfo' => $prev ?: null,
            'nextChaInfo' => $next ?: null
        ];

        // if ($comicInfo['xianmian'] == 0 && $chaInfo['isvip'] == 1) {
        //     $uid = getUid();
        //     if (!$uid) {
        //         throw new BaseException(2000);
        //     }
        //     if ($chaInfo['score'] > 0) {
        //         $userInfo = User::field('score,viptime,isvip_status,auto_buy')->where('id', '=', $uid)->find();

        //         /*if($userInfo['isvip_status'] == 1 && $comicInfo['vipcanread'] == 1){
        //             return $result;
        //         }*/
        //         $is_buy = Record::where('member_id', '=', $uid)->where('capter_id', '=', $chaInfo['id'])->count();
        //         if (!$is_buy && $userInfo['isvip_status'] != 1) {
        //             if ($userInfo['auto_buy'] == 0) {
        //                 $data = [
        //                     'cid' => $cid,
        //                     'score' => $chaInfo['score']
        //                 ];
        //                 throw new BaseException(4004, $data);
        //             }
        //             if ($userInfo['score'] < $chaInfo['score']) {
        //                 throw new BaseException(4003);
        //             }
        //             $add_record = [
        //                 'capter_id' => $chaInfo['id'],
        //                 'manhua_id' => $chaInfo['manhua_id'],
        //                 'member_id' => $uid,
        //                 'score' => $chaInfo['score'],
        //                 'buytime' => time(),
        //                 'showtime' => date('Y-m-d H:i:s'),
        //             ];
        //             self::startTrans();
        //             try {
        //                 Record::insert($add_record);
        //                 User::where('id', '=', $uid)->setDec('score', $chaInfo['score']);
        //                 Manhua::where('id', '=', $chaInfo['manhua_id'])->setInc('mark');
        //             } catch (\Exception $e) {
        //                 self::rollback();
        //                 throw new BaseException(999);
        //             }
        //             self::commit();
        //         }
        //     }
        // }
        return $result;
    }


    public static function buy($cid, $uid)
    {

        $chaInfo = self::field('id,manhua_id,switch,isvip,score,imagelist')->where('id', '=', $cid)->find();
        $is_buy = Record::where('member_id', '=', $uid)->where('capter_id', '=', $chaInfo['id'])->count();
        if ($chaInfo['score'] > 0 && !$is_buy) {
            $userInfo = User::field('score,viptime,isvip_status,auto_buy')->where('id', '=', $uid)->find();
            if ($userInfo['score'] < $chaInfo['score']) {
                throw new BaseException(4003);
            }
            $add_record = [
                'capter_id' => $chaInfo['id'],
                'manhua_id' => $chaInfo['manhua_id'],
                'member_id' => $uid,
                'score' => $chaInfo['score'],
                'buytime' => time(),
                'showtime' => date('Y-m-d H:i:s'),
            ];
            self::startTrans();
            /*try {*/
            Record::insert($add_record);
            User::where('id', '=', $uid)->setDec('score', $chaInfo['score']);
            Manhua::where('id', '=', $chaInfo['manhua_id'])->setInc('mark');
            Manhua::where('id', '=', $chaInfo['manhua_id'])->setInc('monthly_mark');
            /*            }catch (\Exception $e){
                self::rollback();
                throw new BaseException(999);
            }*/
            self::commit();
        }

        $result = [
            'chaInfo' => $chaInfo,
        ];
        return $result;
    }
}
