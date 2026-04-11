<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/1/16
 * Time: 20:09
 */

namespace app\index\controller;

class System extends Base
{
    protected $loginAdmin= '';
    protected $systemLog= '';
    public function initialize()
    {
        parent::initialize();
        //复制模型专用  不是原模型 开始
        $this->loginAdmin =new \app\index\model\LoginAdmin();
        $this->systemLog =new \app\index\model\SystemLog();
    }

    /*
     * 操作日志
     */
    public function system_log(){
        $param = input();
        $param['page'] = !empty($param['page'])?$param['page']:1;
        $param['limit'] = !empty($param['limit'])?$param['limit']:10;
        $where = [];
        if(!empty($param['admin_id'])){
            $where[] = ['admin_id','eq',$param['admin_id']];
        }
        $total = $this->systemLog->where($where)->count();
        $list =  $this->systemLog->where($where)->page($param['page'],$param['limit'])->order('id desc')->select();
        $this->assign([
            'list'  => $list,
            'total' => $total,
            'page'  => $param['page'],
            'limit' => $param['limit'],
        ]);
        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);
        $adminList = model('admin')->field('id,username')->select();
        $this->assign('adminList',$adminList);
        return $this->fetch();
    }


    /*
     * 登陆日志
     */
    public function login_admin(){
        $param = input();
        $param['page'] = !empty($param['page'])?$param['page']:1;
        $param['limit'] = !empty($param['limit'])?$param['limit']:10;
        $where = [];
        if(!empty($param['admin_id'])){
            $where[] = ['admin_id','eq',$param['admin_id']];
        }
        $total = $this->loginAdmin->where($where)->count();
        $list =  $this->loginAdmin->where($where)->page($param['page'],$param['limit'])->order('id desc')->select();
        $this->assign([
            'list'  => $list,
            'total' => $total,
            'page'  => $param['page'],
            'limit' => $param['limit'],
        ]);
        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);

        $adminList = model('admin')->field('id,username')->select();
        $this->assign('adminList',$adminList);
        return $this->fetch();
    }
}