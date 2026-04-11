<?php
namespace app\index\job;

use think\queue\Job;
use think\facade\Log;

class ConvertImportManhuaJob
{
    public function fire(Job $job, $data)
    {
        try {
            if (isset($data['service'], $data['method'])) {
                $serviceClass = $data['service'];
                $method = $data['method'];

                $service = new $serviceClass();
                $service->$method();
            }
            $job->delete();
        } catch (\Exception $e) {
            Log::error("队列执行失败: " . $e->getMessage());
            if ($job->attempts() < 2) {
                $job->release(30);
            } else {
                $job->delete();
            }
        }
    }
}
