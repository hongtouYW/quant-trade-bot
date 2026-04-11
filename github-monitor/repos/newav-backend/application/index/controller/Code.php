<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2023/1/12
 * Time: 15:14
 */

namespace app\index\controller;

use think\Queue;

class Code extends Base
{


    protected $model= '';
    public function initialize()
    {
        parent::initialize();
        //复制模型专用  不是原模型 开始
        $this->model =new \app\index\model\Code();
    }

    public function index(){

        $param = input();
        $param['page'] = !empty($param['page'])?$param['page']:1;
        $param['limit'] = !empty($param['limit'])?$param['limit']:20;
        $where = [];
        // if(in_array($param['type'],['1','2','3','4'],true)){
        //     $where[] = ['type','eq',$param['type']];
        // }
        if(in_array($param['status'],['0','1'],true)){
            $where[] = ['status','eq',$param['status']];
        }
        if(in_array($param['day'],['3','7','30','60','360'],true)){
            $where[] = ['day','eq',$param['day']];
        }
        $total = $this->model->where($where)->count();
        $list =  $this->model->where($where)->page($param['page'],$param['limit'])->order('id desc')->select();
        $this->assign([
            'list'  => $list,
            'total' => $total,
            'page'  => $param['page'],
            'limit' => $param['limit'],
        ]);
        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);
        return $this->fetch("index");
    }

    public function create()
    {
        if (request()->isPost()) {
            $p1    = getRandChar(2,'0123456789');
            $p2    = getRandChar(3,'0123456789');
            $batch = $p1.time().$p2;
            $param = input('post.');
            $num   = $param['num'];

            $dataList = [];
            for ($i = 1; $i <= $num; $i++) {
                $dataList[] = [
                    'batch'     => $batch,
                    'day'       => $param['day'],
                    'code'      => $this->create_code(),
                    'day_value' => $param['day_value'],
                    'diamonds'  => $param['diamonds'],
                    'points'    => $param['points'],
                ];
            }

            // 插入数据库
            $this->model->saveAll($dataList);

            return json(["code" => 1, "msg" => "生成成功", "batch" => $batch, "count" => $num]);
        }
        return $this->fetch();
    }

    // dont use queue 
    // public function create(){
    //     if (Request()->isPost()) {

    //         $p1 = getRandChar(2,'0123456789');
    //         $p2 = getRandChar(3,'0123456789');
    //         $batch = $p1.time().$p2;

    //         $param = input('post.');
    //         $num = $param['num'];
    //         $data = [
    //             // 'type'=>$param['type'],
    //             'batch'=>$batch,
    //             'day'=>$param['day']
    //         ];
    //         $jobHandlerClassName  = 'app\index\job\Createcode';
    //         $jobQueueName  	  = "createcode";
    //         for ($i=1;$i<=$num;$i++)
    //         {
    //             $data['code'] = $this->create_code();
    //             Queue::push($jobHandlerClassName , $data, $jobQueueName);
    //         }
    //         return json(["code"=>1,"msg"=>"队列任务添加成功"]);
    //     }
    //     return $this->fetch();
    // }

    private function create_code(){
        $code = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $rand = $code[rand(0,25)]
            .strtoupper(dechex(date('m')))
            .date('d').substr(time(),-5)
            .substr(microtime(),2,5)
            .sprintf('%02d',rand(0,99));
        for(
            $a = md5( $rand, true ),
            $s = '0123456789ABCDEFGHIJKLMNOPQRSTUV',
            $d = '',
            $f = 0;
            $f < 8;
            $g = ord( $a[ $f ] ),
            $d .= $s[ ( $g ^ ord( $a[ $f + 8 ] ) ) - $g & 0x1F ],
            $f++
        );
        return $d;
    }
}