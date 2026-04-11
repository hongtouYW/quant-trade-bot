<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2023/3/15
 * Time: 13:24
 */

namespace app\index\controller;


use think\Controller;
use app\index\model\Comic as ComicModel;
use app\index\model\Video4k as Video4kModel;
class Callback extends Controller
{


    public function test(){

        echo 4;
    }

    public function dm()
    {
        try {
            $data = file_get_contents('php://input');
            save_log("hugo: ".json_encode(input(),JSON_UNESCAPED_UNICODE),'hugo_video');
            $data = json_decode($data, true);
            if (!$data) {
                return json(['code' => 1, 'msg' => '参数格式不正确']);
            }
            $this->save_dm($data);
/*            $res = $this->save_dm($data);
            if ($res['code'] == 0) {
                save_log($res,'callbackdm_success');
            } else {
                save_log($res,'callbackdm_error');
            }*/
        } catch (\Exception $e) {
            return json(['code' => 1, 'msg' => $e->getMessage()]);
        }
        return json(['code' => 0, 'msg' => 'success']);

    }



    public function call_4k()
    {
        try {
            $data = file_get_contents('php://input');
            save_log("hugo: ".json_encode(input(),JSON_UNESCAPED_UNICODE),'hugo_video_4k');
            $data = json_decode($data, true);
            if (!$data) {
                return json(['code' => 1, 'msg' => '参数格式不正确']);
            }
            $this->save_4k($data);
            /*            $res = $this->save_dm($data);
                        if ($res['code'] == 0) {
                            save_log($res,'callbackdm_success');
                        } else {
                            save_log($res,'callbackdm_error');
                        }*/
        } catch (\Exception $e) {
            return json(['code' => 1, 'msg' => $e->getMessage()]);
        }
        return json(['code' => 0, 'msg' => 'success']);

    }



    private function save_dm($data){

        $info = ComicModel::field('id,duration')->where('source_id','=',$data['identifier'])->find();
        $result = ['code'=>1,'msg'=>'','source_id'=>$data['identifier']];

        if(!$info){
            $result['msg'] =  '数据不存在';
            return $result;
        }

        if($info['duration'] > 0){
            $result['msg'] =  '已经切片';
            return $result;
        }

        $update = [
            'duration'=>$data['postparam']['duration'],
/*            'thumb'=>$data['postparam']['cover'],
            'preview'=>$data['postparam']['cover'],*/
            'panorama'=>$data['postparam']['thumb_longview'],
            'video_url'=>$data['postparam']['play_url'],
            'is_handle'=>1,
            'status'=>1
        ];

        $update = ComicModel::where('id','=',$info['id'])->update($update);

        if($update === false){
            $result['msg'] =  '更新失败';
            return $result;
        }

        $result['code'] = 0;
        $result['msg'] = '更新成功';
        return $result;

    }


    private function save_4k($data){

        $where = [];
        $where[] = ['mash','=',$data['identifier']];
        $where[] = ['source','=',0];

        $info = Video4kModel::field('id,is_handle')->where($where)->find();
        $result = ['code'=>1,'msg'=>'','source_id'=>$data['identifier']];

        if(!$info){
            $result['msg'] =  '数据不存在';
            return $result;
        }

        if($info['is_handle'] == 1){
            $result['msg'] =  '已经切片';
            return $result;
        }

        $update = [
            'duration'=>$data['postparam']['duration'],
            'thumb'=>$data['postparam']['cover'],
            'preview'=>$data['postparam']['cover'],
            'panorama'=>$data['postparam']['thumb_longview'],
            'video_url'=>$data['postparam']['play_url'],
            'is_handle'=>1,
            'status'=>1
        ];

        $update = Video4kModel::where('id','=',$info['id'])->update($update);

        if($update === false){
            $result['msg'] =  '更新失败';
            return $result;
        }

        $result['code'] = 0;
        $result['msg'] = '更新成功';
        return $result;

    }



}