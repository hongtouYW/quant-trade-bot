<?php

namespace app\index\controller;
use think\cache\driver\Redis;
use app\index\model\GroupPurchase;
use app\index\model\GroupCollect;
use app\index\model\Group as GroupModel;
use app\index\model\User as UserModel;
use app\index\model\Token;
use think\Db;
use app\lib\exception\BaseException;

class Group extends Base
{
    const list_limit = 20;
    /**
     * Notes:系列
     *
     * DateTime: 2023/8/13 23:29
     */
    public function lists(){
        $page         = !empty(getInput('page'))?(int)getInput('page'):1;
        $limit        = !empty(getInput('limit'))?(int)getInput('limit'):self::list_limit;
        $publisher_id = getInput('publisher_id');
        $keyword      = getInput('keyword');
        $lists        = GroupModel::lists($page, $limit, $keyword, $publisher_id);
        return show(1,$lists);
    }

    public function details(){
        $gid  = GroupModel::getGid();
        $info = GroupModel::info($gid);
        return show(1,$info);
    }

    public function purchase(){
        $uid      = Token::getCurrentUid();
        $userInfo = UserModel::getUserInfo($uid);
        $gid      = GroupModel::getGid();

        //检查冷却
        $redis_key = 'groupPurchase'.$gid.'_'.$uid;
        $redis     = new Redis();
        $cooldown  = $redis->get($redis_key);
        if($cooldown) return show(4004);

        if (GroupPurchase::hasPurchased($uid, $gid)) {
            throw new BaseException(5006);
        }

        $groupInfo = GroupModel::where('id','=',$gid)->where('is_show','=',1)->field('id,title,amount,is_show')->find();
        if (!$groupInfo) {
            throw new BaseException(5002);
        }

        if($userInfo['coin'] < $groupInfo['amount']){
            throw new BaseException(5003);
        }

        Db::startTrans();

        try {// update coin
            $newCoin    = $userInfo['coin'] - $groupInfo['amount'];
            $userUpdate = UserModel::where('id', $uid)->update(['coin' => $newCoin]);

            if (!$userUpdate) {
                throw new BaseException(5004);
            }

            // insert using GroupPurchase model
            $purchase               = new GroupPurchase();
            $purchase->uid          = $uid;
            $purchase->group_id     = $gid;
            $purchase->purchased_at = date('Y-m-d H:i:s');

            if (!$purchase->save()) {
                throw new BaseException(5005);
                // return show(5005, ['purchase' => false, 'msg' => 'Failed to create video group purchase record']);
            }

            Db::commit();
            $redis->set($redis_key, 1, 30);
            return show(1, ['purchase' => true, 'msg' => 'Video group purchased', 'currentCoin' => $newCoin]);

        } catch (\Exception $e) {
            Db::rollback();
            return show(0, ['purchase' => false, 'msg' => 'Something worng']);
        }
    }

    public function collect(){
        $uid         = Token::getCurrentUid();
        $gid         = GroupModel::getGid();
        $collect_id  = GroupCollect::where('uid',$uid)->where('gid',$gid)->value('id');
        if($collect_id){
            GroupCollect::destroy($collect_id);
            $res  = false;
            $msg  = "Video group uncollected";
        }else{
            $add_data = [
                'uid'      => $uid,
                'gid'      => $gid,
                'add_time' => time()
            ];
            GroupCollect::insert($add_data);
            $res = true;
            $msg  = "Video group collected";
        }
        return show(1, ['isCollected' => $res, 'msg' => $msg]);
    }

    public function myCollect(){
        $uid   = Token::getCurrentUid();
        $page  = !empty(getInput('page'))?(int)getInput('page'):1;
        $limit = !empty(getInput('limit'))?(int)getInput('limit'):12;
        $lists = GroupModel::myCollect($uid,$page,$limit);
        return show(1,$lists);
    }

    public function myPurchase(){
        $uid   = Token::getCurrentUid();
        $page  = !empty(getInput('page'))?(int)getInput('page'):1;
        $limit = !empty(getInput('limit'))?(int)getInput('limit'):20;
        $lists = GroupModel::myPurchase($uid,$page,$limit);
        return show(1,$lists);
    }
}