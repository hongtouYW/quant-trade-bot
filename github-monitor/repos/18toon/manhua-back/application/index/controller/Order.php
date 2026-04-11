<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/9/14
 * Time: 16:12
 */

namespace app\index\controller;

use app\index\model\Platforms as PlatformsModel;
use app\index\model\Pro as ProModel;
use app\index\model\User as UserModel;
use app\service\GiftService;
use app\service\ChannelStatsService;
use think\Db;

class Order extends Base
{

    protected $model = '';
    public function initialize()
    {
        parent::initialize();
        //复制模型专用  不是原模型 开始
        $this->model = new \app\index\model\Order();
    }

    public function index()
    {

        $param = input();
        $param['page'] = !empty($param['page']) ? $param['page'] : 1;
        $param['limit'] = !empty($param['limit']) ? $param['limit'] : 20;
        $where = [];
        $where[] = ['is_kl', '=', 0];
        if (!empty($param['member_id'])) {
            $param['member_id'] = trim($param['member_id']);
            $where[] = ['member_id', '=', $param['member_id']];
        }
        /*        if(!empty($param['pro_id'])){
                    $param['pro_id'] = trim($param['pro_id']);
                    $where[] =['pro_id','=',$param['pro_id']];
                }
                if(!empty($param['paydata'])){
                    $param['paydata'] = trim($param['paydata']);
                    $where[] =['paydata','=',$param['paydata']];
                }*/

        if (in_array($param['orderswitch'], ['0', '1'], true)) {
            $where[] = ['orderswitch', 'eq', $param['orderswitch']];
        }
        if (!empty($param['timegap'])) {
            $gap = explode('至', $param['timegap']);
            $begin_time = strtotime($gap[0]);
            $end_time = strtotime($gap[1]);
            $where[] = ['addtime2', 'between time', [$begin_time, $end_time]];
            if ($end_time - $begin_time <= 3600) {
                array_splice($where, 0, 1);
            }
        }
        if (!empty($param['ordernums'])) {
            $param['ordernums'] = trim($param['ordernums']);
            $where[] = ['ordernums', '=', $param['ordernums']];
        }
        if (!empty($param['pay_code'])) {
            $param['pay_code'] = trim($param['pay_code']);
            $where[] = ['pay_code', '=', $param['pay_code']];
        }
        $where2 = [
            'bounded' => 1,
        ];

        if (count($param) > 2) {
            unset($where2['bounded']);
        }
        if (!empty($param['member_id']) || !empty($param['ordernums']) || !empty($param['pay_code'])) {
            array_splice($where, 0, 1);
        }
        $total = $this->model->where($where)->where('is_kl', '=', 0)->count();
        $list = $this->model->where($where)->whereOr($where2)->page($param['page'], $param['limit'])->order('id desc')->select();

        $this->assign([
            'list' => $list,
            'total' => $total,
            'page' => $param['page'],
            'limit' => $param['limit'],
        ]);
        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);

        /*        $platList = PlatformsModel::field('id,qudaodes')->select();
                $this->assign('platList',$platList);

                $proList = ProModel::field('id,intro')->select();
                $this->assign('proList',$proList);*/
        return $this->fetch();
    }


    public function make_up()
    {
        if (!request()->isPost()) {
            return json(['code' => 0, 'msg' => '非法请求']);
        }

        $param = input('post.');
        $orderId = intval($param['id']);
        $remark = trim($param['remark'] ?? '');
        $time = time();

        $remark = trim($param['remark'] ?? '');
        if (empty($remark)) {
            return json(['code' => 0, 'msg' => '请填写备注信息']);
        }

        $orderInfo = $this->model
            ->where('id', $orderId)
            ->field('id,member_id,pro_id,orderswitch,money,discount,currency,pay_amount,ordernums')
            ->find();

        if (!$orderInfo) {
            return json(['code' => 0, 'msg' => '订单不存在']);
        }

        if ($orderInfo['orderswitch'] == 1) {
            return json(['code' => 0, 'msg' => '该订单已支付，不能重复补单']);
        }

        $prodata = ProModel::field('type_status,addcoin,addvip')->where('id', $orderInfo['pro_id'])->find();
        if (!$prodata) {
            return json(['code' => 0, 'msg' => '充值商品不存在']);
        }

        Db::startTrans();
        try {
            // 更新订单状态
            $this->model->where('id', $orderInfo['id'])->update([
                'orderswitch' => 1,
                'pay_code' => 'manual_' . $time,
                'remark' => $remark,
            ]);

            // 发放充值
            if ($prodata['type_status'] == 1) {
                // 积分充值
                UserModel::where('id', $orderInfo['member_id'])->setInc('score', $prodata['addcoin']);
            } elseif ($prodata['type_status'] == 2) {
                // VIP 充值
                $user = UserModel::field('id,viptime')->find($orderInfo['member_id']);
                $vipBaseTime = max($user['viptime'], $time);
                $vipDays = $prodata['addvip'];
                $newVipTime = $vipBaseTime + $vipDays * 86400;
                UserModel::where('id', $user['id'])->update(['viptime' => $newVipTime]);
            }

            // 赠品活动
            GiftService::checkGiftActivity($orderInfo->toArray(), $prodata->toArray(), $orderInfo['member_id']);

            $user = UserModel::where('id', $orderInfo['member_id'])->field('channel_id')->find();
            if (!empty($user['channel_id'])) {
                ChannelStatsService::recordRecharge($orderInfo['money'], 1, (int) $user['channel_id']);
            }

            Db::commit();
            return json(['code' => 1, 'msg' => '补单成功']);
        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => 0, 'msg' => '补单失败：' . $e->getMessage()]);
        }
    }

}