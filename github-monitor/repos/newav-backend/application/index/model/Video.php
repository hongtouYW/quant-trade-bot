<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/6/7
 * Time: 14:51
 */

namespace app\index\model;
use think\cache\driver\Redis;
use think\Db;
use think\Model;

class Video extends Model
{
    public static $translateFields = ['title', 'description'];

    public function countData($where,$where2)
    {
        $total = $this->where($where)->where($where2)->count();
        return $total;
    }

    public function getAdminNameAttr($value,$data){
        return model('admin')->where('id','=',$data['last_admin_id'])->value('username');
    }

    public function getTagsNameAttr($val, $data) {
        if (!empty($data['tags'])) {
            $tagIds = explode(',', $data['tags']);
            $val    = Tags::where('id', 'in', $tagIds)->column('name');
            $val    = implode(',', $val);
        }
        return $val;
    }

    public function getActorAttr($val, $data)
    {
        if (empty($val)) {
            return '';
        }
        $ids = is_array($val)
            ? $val
            : array_filter(array_map('trim', explode(',', (string)$val)));

        if (empty($ids)) {
            return '';
        }
        $names = Actor::where('id', 'in', $ids)->column('name');
        return !empty($names) ? implode(',', $names) : '';
    }

    public function getThumbAttr($val){

        if(!empty($val)){
            $thumb_url = Configs::get('no_au_thumb_url');
            $val = $thumb_url.$val;
        }
        return $val;
    }

    public function getVideoUrlAttr($val,$data){

        if(!empty($val)){
            $video_url = Configs::get('video_url');
            $val       = $video_url.$val;
            $key       = md5($val);
            $redis     = new Redis();
            $redis->select(3);
            $url = $redis->get($key);
            if(empty($url)){
                $url = self::jianquan($val);
                $redis->set($key,$url,300);
            }
            return $url;
        }
        return $val;
    }

    private static function jianquan($url){
        $parse = parse_url($url);
        $expiryTime = 7200; // 有效期（秒）
        $secretKey = 'FoEDb2QIeVvUOyTlBJ9NMDYgJFNZ30';
        $wstime = time() + $expiryTime; // 当前时间戳 + 有效期
        $uri = $parse['path']; // 资源路径
        $group = $secretKey . $uri . $wstime; // 生成鉴权组合：密钥 + 路径 + 时间戳
        $wsSecret = md5($group); // 使用 MD5 加密生成签名
        return $url."?wsSecret=" . $wsSecret . "&wsTime=" . $wstime;
    }

    public function getGroupNamesAttr($val, $data)
    {
        if ($data['private'] != 3) {
            return ''; // Only for 系列 videos
        }

        $groupIds = Db::name('video_group_details')
            ->where('video_id', $data['id'])
            ->column('group_id');

        if (empty($groupIds)) {
            return '';
        }

        $groupNames = Db::name('video_groups')
            ->whereIn('id', $groupIds)
            ->column('title');

        return !empty($groupNames) ? implode(',', $groupNames) : '';
    }

    public function getLastAdminAttr($val, $data)
    {
        if ($data['last_admin_id'] != 0) {
            $adminNames = Db::name('admin')
                            ->where('id', $data['last_admin_id'])
                            ->value('username');
            return $adminNames;
        }
        return '';
    }


    public function listData($where,$order,$page=1,$limit=10,$start=0,$field='*',$totalshow=1)
    {
        if(!is_array($where)){
            $where = json_decode($where,true);
        }
        $where2='';
        if(!empty($where['_string'])){
            $where2 = $where['_string'];
            unset($where['_string']);
        }

        $limit_str = ($limit * ($page-1) + $start) .",".$limit;
        if($totalshow==1) {
            $total = $this->countData($where,$where2);
        }
        $list = $this->field($field)->where($where)->where($where2)->order($order)->limit($limit_str)->select();
        return ['code'=>1,'msg'=>'数据列表','page'=>$page,'pagecount'=>ceil($total/$limit),'limit'=>$limit,'total'=>$total,'list'=>$list];
    }

    public function infoData($where,$field='*')
    {
        if(empty($where) || !is_array($where)){
            return ['code'=>0,'msg'=>'参数错误'];
        }

        $info = $this->field($field)->where($where)->find();
        if (empty($info)) {
            return ['code' => 0, 'msg' => '获取失败'];
        }

        return ['code'=>1,'msg'=>'获取成功','info'=>$info];
    }

    public function saveData($data)
    {
        Db::startTrans();
        try {
            $videoId = $data['id'] ?? null;
            // Prepare video data
            $videoData = [
                'mosaic'         => $data['mosaic'] ?? 0,
                'last_admin_id'  => $data['last_admin_id'] ?? 0,
                'title'          => $data['title'] ?? '',
                'title_en'       => $data['title_en'] ?? '',
                'title_ru'       => $data['title_ru'] ?? '',
                'tags'           => is_array($data['tags'] ?? '') ? implode(',', $data['tags']) : ($data['tags'] ?? ''),
                'private'        => $data['private'] ?? 0,
                'hotlist'        => $data['hotlist'] ?? 0,
                'position'       => $data['position'] ?? "right",
                'mash'           => $data['mash'] ?? '',
                'director'       => $data['director'] ?? '',
                'description'    => $data['description'] ?? '',
                'description_en' => $data['description_en'] ?? '',
                'description_ru' => $data['description_ru'] ?? '',
            ];

            // Save video data
            if ($videoId) {
                $this->where('id', $videoId)->update($videoData);
            } else {
                $videoId = $this->insertGetId($videoData);
            }

            // =====video group start=====
            // Always delete existing groups first
            Db::name('video_group_details')
                ->where('video_id', $videoId)
                ->delete();
            
            // Only save groups if private == 3
            if ($data['private'] == 3 && !empty($data['groups'])) {
                $groups = [];
                if (is_array($data['groups'])) {
                        $groups = explode(',', $data['groups'][0]);
                }
                
                $groupData = [];
                foreach ($groups as $groupId) {
                    $groupData[] = [
                        'video_id' => $videoId,
                        'group_id' => $groupId,
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                }
                
                if (!empty($groupData)) {
                    Db::name('video_group_details')->insertAll($groupData);
                }
            }
            
            // =====video group end=====

            // =====video hotlist start=====
            // Always delete existing hotlist first
            Db::name('hotlist_category_details')
                ->where('video_id', $videoId)
                ->delete();
            
            // Only save groups if private == 3
            if ($data['hotlist'] == 1 && !empty($data['hotlists'])) {
                $hotlists = [];
                if (is_array($data['hotlists'])) {
                        $hotlists = explode(',', $data['hotlists'][0]);
                }
                
                $hotlistData = [];
                foreach ($hotlists as $hotlistID) {
                    $hotlistData[] = [
                        'video_id'            => $videoId,
                        'hotlist_category_id' => $hotlistID
                    ];
                }
                
                if (!empty($hotlistData)) {
                    Db::name('hotlist_category_details')->insertAll($hotlistData);
                }
            }
            // =====video hotlist end=====
            Db::commit();
            return ['code' => 1, 'msg' => $videoId ? '保存成功' : '创建成功'];
        } catch (\Exception $e) {
            Db::rollback();
            return ['code' => 0, 'msg' => '操作失败: ' . $e->getMessage()];
        }
    }

    private function video_handle($mosaic){
        $date = date('Y-m-d');
        $is_date = VideoHandle::where('date','=',$date)->lock(true)->value('id');

        switch ($mosaic){
            case 0:
                $type = 'wuma';
                break;
            case 1:
                $type = 'youma';
                break;
        }

        if($is_date){
            $date_update = [
                'num'=>Db::raw('num+1'),
                $type=>Db::raw($type.'+1'),
            ];
            VideoHandle::where('id','=',$is_date)->update($date_update);
        }else{
            $date_add = [
                'date'=>$date,
                $type=>1,
            ];
            VideoHandle::insert($date_add);
        }
    }

    public function update_last_admin($id){
        $admin_id = session('admin_id');
        $this->where('id', $id)->update(['last_admin_id' => $admin_id]);
    }
}