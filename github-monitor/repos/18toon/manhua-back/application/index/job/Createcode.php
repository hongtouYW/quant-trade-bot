<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2023/1/14
 * Time: 0:36
 */

namespace app\index\job;


use app\index\model\Code;
use think\facade\Log;
use think\queue\Job;
class Createcode
{
    /**
     * fire方法是消息队列默认调用的方法
     * @param Job            $job      当前的任务对象
     * @param array|mixed    $data     发布任务时自定义的数据
     */
    public function fire(Job $job,$data){
        $isJobDone = $this->doJob($data);
        if ($isJobDone) {
            //如果任务执行成功， 记得删除任务
            $job->delete();
        }else{
            if ($job->attempts() > 3) {
                $job->delete();
            }
        }
    }

    /**
     * 根据消息中的数据进行实际的业务处理
     * @param array|mixed    $data     发布任务时自定义的数据
     * @return boolean                 任务执行的结果
     */
    private function doJob($data) {
        // 根据消息中的数据进行实际的业务处理...
        $data['create_time'] = time();
        $res = Code::insert($data);
        return $res;
    }
}