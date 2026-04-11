<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/1/15
 * Time: 16:43
 */

namespace app\index\model;

class Authmenu extends BaseModel
{
    protected $table="auth_menu";



    /*
     * 获取父级菜单名称
     */
    public function getParentNameAttr($value,$data){

        return $this->where('id','=',$data['parent_id'])->value('name');
    }

    /*
     * 获取所有的菜单栏
     */
    public function getMenu($menuid_array){

        $menu=$this
            ->where(array("parent_id"=>0,"type"=>"menu"))
            ->where('id','in',$menuid_array)
            ->order("sort desc")
            ->select();

        $nmenu=array();
        if (!empty($menu)){
            foreach ($menu as $key=>$value){
                $pid=$value['id'];
                $nmenu[$key]=$value;
                $cmenu=$this->where(array('parent_id'=>$pid,'type'=>'menu'))
                    ->where('id','in',$menuid_array)
                    ->order("sort desc")
                    ->select();
                $nmenu[$key]['cmenu'] = $cmenu;
            }
        }
        return $nmenu;
    }


    /*
     * 删除菜单
     *
     */
    public function del_menu($id){
        $info=$this->where(array("id"=>$id))->find();
        if(empty($info)){
            return array("code"=>0,"msg"=>"信息错误");
        }

        $isXj = $this->where(['parent_id'=>$id])->count();
        if($isXj){
            return array("code"=>0,"msg"=>"请先删除该栏目的下级菜单");
        }
        $count=model('auth_role')->where('find_in_set(:id,menu_id)',['id'=>$id])->count();
        if($count > 0){
            return array("code"=>0,"msg"=>"当前的子权限已经绑定角色，需要解除绑定才可以删除");
        }
        $res=$this->where(array("id"=>$id))->delete();
        if($res){
            return array("code"=>1,"msg"=>"删除成功");
        }else{
            return array("code"=>0,"msg"=>"删除失败");
        }
    }


}
