<?php

namespace app\index\job;

use app\service\ImageTranslateService;
use think\queue\Job;
use think\Db;

// php think queue:listen --queue tran_capter_img

class TranslateCapterImgJob
{
    public function fire(Job $job, $data)
    {
        if ($job->attempts() > 3) {
            $job->delete();
            return;
        }

        $capterId = $data['capter_id'] ?? null;
        $languages = $data['languages'] ?? null;

        if (!$capterId) {
            return $job->delete();
        }

        $capter = Db::name('capter')->where('id', $capterId)->find();

        if (!$capter) {
            return $job->delete();
        }

        $images = array_filter(array_map('trim', explode(',', $capter['imagelist'] ?? '')));

        if (empty($images)) {
            return $job->delete();
        }

        $imagesString = implode(',', $images);

        try {
            $service = new ImageTranslateService();
            $result = $service->translateImage($imagesString, $capterId, $languages);

            $translateImg = $result == null ? "3" : "1";

            Db::name('capter')->where('id', $capterId)->update([
                'translate_img' => $translateImg,
                'update_time' => time(),
            ]);

            $job->delete();
        } catch (\Exception $e) {
            $job->release(10);
        }
    }
}
