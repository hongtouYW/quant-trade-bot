<?php

namespace app\index\controller;

use app\index\model\Order;
use app\index\model\Pro;
use app\index\model\User as UserModel;
use app\service\GiftService;
use app\service\ChannelStatsService;
use think\Db;
use think\Request;

class Callback extends Base
{
    public function translatedImg(Request $request)
    {
        $data = $request->post();

        save_log($data, "translated_img_callback");

        // 允许的语言代码
        $allowedLanguages = ["ru", "vi", "hi", "en", "es"];

        // 1. 校验回调状态码
        if (!isset($data['code'])) {
            return json(["code" => 1, "message" => "缺少状态码"]);
        }

        // 翻译失败处理
        if ($data['code'] != "200") {
            // 有效 external_id 才处理
            if (!empty($data['external_id'])) {
                $capterId = $data['external_id'];
                // 标记章节为翻译失败
                Db::name('capter')->where('id', $capterId)->update([
                    'translate_img' => 4, // 4 = 翻译失败
                    'update_time' => time(),
                ]);
            }

            return json(["code" => 1, "message" => "翻译失败，状态码不为200"]);
        }

        // 2. 校验必要参数
        if (empty($data['external_id']) || empty($data['translated_images']) || !is_array($data['translated_images'])) {
            return json(["code" => 1, "message" => "参数不完整或格式错误"]);
        }

        $capterId = $data['external_id'];

        // 3. 查找章节信息
        $capter = Db::name('capter')->where('id', $capterId)->find();
        if (!$capter) {
            return json(["code" => 1, "message" => "无效的 external_id"]);
        }

        // 获取所属漫画 ID
        $manhuaId = $capter['manhua_id'] ?? null;
        $currentSort = $capter['sort'] ?? 0;

        if ($capter['translate_img'] == 2) {
            return json(["code" => 1, "message" => "该章节已完成翻译，跳过"]);
        }

        // 4. 遍历并保存翻译图片
        foreach ($data['translated_images'] as $lang => $images) {
            // 跳过不允许的语言
            if (!in_array($lang, $allowedLanguages)) {
                continue;
            }

            $exists = Db::name('capter_trans')->where([
                'capter_id' => $capterId,
                'lang_code' => $lang
            ])->find();

            // 加上自然排序
            if (is_array($images)) {
                natsort($images);
                $images = array_values($images); // 重建索引，防止出现不连续键
            }

            $imagelist = implode(",", $images);

            if ($exists) {
                // 执行更新
                Db::name('capter_trans')->where('id', $exists['id'])->update([
                    'imagelist' => $imagelist,
                ]);
            } else {
                // 执行插入
                Db::name('capter_trans')->insert([
                    'capter_id' => $capterId,
                    'lang_code' => $lang,
                    'imagelist' => $imagelist,
                ]);
            }
        }

        // 5. 标记当前章节翻译完成，但不直接开启
        Db::name('capter')->where('id', $capterId)->update([
            'translate_img' => 2,
            'switch' => 0,
            'update_time' => time(),
        ]);

        // 6. 检查前面章节是否都翻译完成（当前章节也算进来）
        $preUntranslated = Db::name('capter')
            ->where('manhua_id', $manhuaId)
            ->where('sort', '<=', $currentSort) // 包含当前章节
            ->where('translate_img', '<>', 2)
            ->count();

        if ($preUntranslated == 0) {
            // 所有 <= 当前sort 的章节都翻译完成，一起开启
            Db::name('capter')
                ->where('manhua_id', $manhuaId)
                ->where('sort', '<=', $currentSort)
                ->where('translate_img', 2)
                ->where('switch', 0)
                ->update([
                    'switch' => 1,
                    'update_time' => time(),
                ]);

            // // 开启漫画状态
            // Db::name('manhua')->where('id', $manhuaId)->update([
            //     'status' => 1,
            //     'update_time' => time(),
            // ]);
        }

        return json(["code" => 0, "message" => "翻译图片保存成功"]);
    }

    public function epayCallback(Request $request)
    {
        $data = $request->post();
        payment_log($data, "epay/callback");

        // ✅ Step 1: 基本参数校验
        $required = ['reference', 'transID', 'bill_amt', 'order_status', 'bill_currency'];
        foreach ($required as $key) {
            if (empty($data[$key])) {
                return json(['code' => 0, 'msg' => "缺少字段：{$key}"]);
            }
        }

        // ✅ Step 2: 解析参数
        $orderNo = $data['reference'];
        $invoiceNo = $data['transID'];
        $amount = (float) $data['bill_amt'];
        $status = $data['order_status'];
        $currency = strtolower($data['bill_currency']);

        // Step 3: 非成功状态直接返回
        if ($status != '1') {
            return json(['code' => 0, 'msg' => '订单未成功']);
        }

        // Step 4: 执行订单回调逻辑
        $res = $this->doCallback($amount, $orderNo, $invoiceNo, $currency);
        payment_log($res, $res['status'] == 1 ? 'epay/callback_success' : 'epay/callback_fail');

        echo "OK";
        exit;
    }

    public function wxrCallback(Request $request)
    {
        $data = $request->post();
        payment_log($data, "wxr/callback");

        // ✅ Step 1: 基本参数校验
        $required = ['orderId', 'sourceId', 'amount', 'success'];
        foreach ($required as $key) {
            if (empty($data[$key])) {
                return json(['code' => 0, 'msg' => "缺少字段：{$key}"]);
            }
        }

        // ✅ Step 2: 解析参数
        $orderNo = $data['orderId'];
        $invoiceNo = $data['sourceId'];
        $amount = (float) $data['amount'];
        $status = $data['success'];
        $currency = "rmb";

        // Step 3: 非成功状态直接返回
        if ($status != '1') {
            return json(['code' => 0, 'msg' => '订单未成功']);
        }

        // Step 4: 执行订单回调逻辑
        $res = $this->doCallback($amount, $orderNo, $invoiceNo, $currency);
        payment_log($res, $res['status'] == 1 ? 'wxr/callback_success' : 'wxr/callback_fail');

        echo "OK";
        exit;
    }

    public function guozhiCallback(Request $request)
    {
        $data = $request->post();
        payment_log($data, "guozhi/callback");

        // ✅ Step 1: 基本参数校验
        $required = ['order_no', 'amount', 'success', 'encode_sign'];
        foreach ($required as $key) {
            if (empty($data[$key])) {
                payment_log("果子支付回调缺少字段: {$key}", "guozhi/callback_fail");
                return response('FAIL', 400);
            }
        }

        $orderNo = $data['order_no'];
        $amount = $data['amount'];
        $invoiceNo = $data['invoice_no'] ?? null;
        $status = $data['success'];
        $currency = strtolower($data['currency'] ?? 'rmb');

        // ✅ Step 2: 解码签名
        $decodedSign = base64_decode($data['encode_sign']);
        unset($data['encode_sign']); // 签名字段不参与验签

        // ✅ Step 3: 构建待签字符串（使用回调所有字段 JSON encode）
        $signStr = json_encode($data, JSON_UNESCAPED_UNICODE);
        payment_log("果子支付待验签字符串: {$signStr}", "guozhi/sign");

        // ✅ Step 4: 获取公钥
        $order = Db::name('order')
            ->where('ordernums', $orderNo)
            ->field('is_kl')
            ->find();
        $isKl = $order['is_kl'] ?? 0;

        $keyFile = $isKl == 1
            ? ROOT_PATH . 'public/key/key_22_18tooncn.txt'
            : ROOT_PATH . 'public/key/key_22_18tooncn.txt';

        if (!file_exists($keyFile)) {
            payment_log("果子公钥文件不存在: {$keyFile}", "guozhi/callback_fail");
            return response('FAIL', 500);
        }

        $content = file_get_contents($keyFile);
        if (preg_match('/-----BEGIN PUBLIC KEY-----(.*?)-----END PUBLIC KEY-----/s', $content, $matches)) {
            $publicKeyPem = "-----BEGIN PUBLIC KEY-----\n" . trim($matches[1]) . "\n-----END PUBLIC KEY-----";
        } else {
            // txt 文件里只有纯 key 内容
            $publicKeyPem = "-----BEGIN PUBLIC KEY-----\n" . trim($content) . "\n-----END PUBLIC KEY-----";
        }

        $publicKey = openssl_get_publickey($publicKeyPem);
        if (!$publicKey) {
            payment_log("果子公钥加载失败: {$keyFile}", "guozhi/callback_fail");
            return response('FAIL', 500);
        }

        // ✅ Step 5: 验签
        $verify = openssl_public_decrypt($decodedSign, $decrypted, $publicKey, OPENSSL_PKCS1_PADDING);
        if (!$verify) {
            payment_log("果子支付回调验签失败", "guozhi/callback_fail");
            return response('FAIL', 400);
        }

        // 可选：这里也可以比对 $decrypted 与 $signStr，如果需要安全性
        payment_log("果子支付验签通过", "guozhi/sign");

        // ✅ Step 6: 非成功状态直接返回
        if ((int) $status !== 1) {
            payment_log("果子支付回调状态不为成功", "guozhi/callback_fail");
            return response('FAIL', 400);
        }

        // ✅ Step 7: 执行订单回调逻辑
        $res = $this->doCallback($amount, $orderNo, $invoiceNo, $currency);
        payment_log($res, $res['status'] == 1 ? 'guozhi/callback_success' : 'guozhi/callback_fail');

        echo "OK";
        exit;
    }


    private function doCallback($amount, $order_no, $invoice_no, $currency)
    {
        $time = time();
        $result = ['status' => 0, 'msg' => '系统繁忙', 'order_no' => $order_no];

        $orderInfo = Order::field('id,member_id,pro_id,orderswitch,money,discount,currency,pay_amount')
            ->where('ordernums', $order_no)
            ->find();

        if (!$orderInfo) {
            $result['msg'] = '订单不存在';
            return $result;
        }

        if ($orderInfo['orderswitch'] == 1) {
            $result['msg'] = '该订单已支付';
            return $result;
        }

        // ✅ Step: 判断货币类型是否一致
        $orderCurrency = strtolower($orderInfo['currency'] ?? 'usd');
        if ($orderCurrency !== $currency) {
            $result['msg'] = "货币类型不一致，订单为{$orderCurrency}，支付为{$currency}";
            return $result;
        }

        // ✅ Step: 金额判断（防止精度问题，用bccomp）
        $expectedAmount = (string) $orderInfo['pay_amount'];
        if (bccomp((string) $amount, $expectedAmount, 2) !== 0) {
            $result['msg'] = "订单金额与支付金额不一致，期望：{$expectedAmount} {$currency}，实际：{$amount}";
            return $result;
        }

        $prodata = Pro::field('type_status,addcoin,addvip')->where('id', $orderInfo['pro_id'])->find();
        if (!$prodata) {
            $result['msg'] = '充值商品不存在';
            return $result;
        }

        Db::startTrans();
        try {
            Order::where('id', $orderInfo['id'])->update([
                'pay_code' => $invoice_no,
                'orderswitch' => 1,
            ]);

            if ($prodata['type_status'] == 1) {
                // 积分充值
                UserModel::where('id', $orderInfo['member_id'])->setInc('score', $prodata['addcoin']);
            } elseif ($prodata['type_status'] == 2) {
                // VIP 充值
                $user = UserModel::field('id,viptime,discount_time')->find($orderInfo['member_id']);
                $vipBaseTime = max($user['viptime'], $time);
                $vipDays = $prodata['addvip'];


                $newVipTime = $vipBaseTime + $vipDays * 86400;
                UserModel::where('id', $user['id'])->update(['viptime' => $newVipTime]);
            }

            GiftService::checkGiftActivity($orderInfo->toArray(), $prodata->toArray(), $orderInfo['member_id']);

            $channelId = ChannelStatsService::getValidChannelIdByUser($orderInfo['member_id']);
            if (!empty($channelId)) {
                ChannelStatsService::recordRecharge($orderInfo['money'], 1, (int) $user['channel_id']);
            }
            Db::commit();
            $result['status'] = 1;
            $result['msg'] = '支付成功';
        } catch (\Exception $e) {
            Db::rollback();
            $result['msg'] = '事务处理失败：' . $e->getMessage();
        }

        return $result;
    }
}
