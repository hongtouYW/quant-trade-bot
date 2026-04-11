<?php

namespace app\index\controller;

use think\Controller;
use think\facade\Log;
use app\index\model\Translate as TranslateModel;

class Translate extends Controller
{
    /**
     * Submit translate job
     * Called from admin / frontend
     */
    public function translate()
    {
        $id        = input('param.id');
        $modelType = input('param.model_type');

        if (!$id || !$modelType) {
            return json(['code' => 0, 'msg' => 'Missing params']);
        }

        try {
            TranslateModel::submit($modelType, (int)$id);
            return json(['code' => 1, 'msg' => 'Translate job submitted']);
        } catch (\Throwable $e) {
            Log::error('Translate submit failed: ' . $e->getMessage());
            return json(['code' => 0, 'msg' => 'Translate submit failed']);
        }
    }

    /**
     * Third-party callback
     */
    public function translate_callback()
    {
        $data = request()->post();
        save_log(json_encode($data, JSON_UNESCAPED_UNICODE), 'translate_callback_title_des_actor');

        if (($data['status'] ?? '') !== 'completed') {
            return response('IGNORED', 200);
        }

        try {
            TranslateModel::handleCallback($data);
            return response('OK', 200);
        } catch (\Throwable $e) {
            Log::error('translate_callback error: ' . $e->getMessage());
            return response('ERROR', 500);
        }
    }
}
