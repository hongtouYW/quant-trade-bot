<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/1/15
 * Time: 16:40
 */

namespace app\index\model;

class Authrole extends BaseModel
{
    protected $table="auth_role";

    /**
     * Notes:获取当前用户可以访问的权限  子节点
     * User: joker
     * Date: 2022/1/18
     * Time: 11:43
     * @param $role_id
     */
    public function getAuthInfo($role_id){
        if (empty($role_id)){
            return null;
        }
        $menu_id=$this
            ->where(array('role_id'=>$role_id))
            ->value('menu_id');
        $menuid_array=explode(',',$menu_id);
        $urlList=model("authmenu")
            ->where('id','in',$menuid_array)
            ->column('url');
        if (!empty($urlList)){
            return $urlList;
        }else{
            return null;
        }
    }
    /*
     * 获取用户当前可以访问的菜单栏
     */
    public function getMenuInfo($admin_id,$role_id){

        $menu=new Authmenu();
        if($admin_id == 1){
            $menuid_array = $menu->column('id');
        }else{
            if (empty($role_id)){
                return null;
            }
            $menu_id=$this->where(array('role_id'=>$role_id))->value('menu_id');
            $menuid_array=explode(',',$menu_id);
        }
        //获取可以访问的菜单
        $info=$menu->getMenu($menuid_array);
        return $info;
    }

    /*
     * 获取角色信息
     */
    public function getRoleInfo($role_id){
        $info=$this->where(array("role_id"=>$role_id))
            ->find();
        if (empty($info)){
            return false;
        }
        $menuid_array=explode(',',$info["menu_id"]);
        $info["menu"]=$menuid_array;
        if (empty($info)){
            return false;
        }else{
            return $info;
        }
    }
    /*
     * 删除角色
     */
    public function del_role($role_id){
        $info=$this->where(array("role_id"=>$role_id))->find();
        if (empty($info)){

            return array('code'=>0,'msg'=>'信息错误');
        }
        $data=model('admin')->where(array("role_id"=>$role_id))->select();
        if (!empty($data)){
            return array('code'=>0,'msg'=>'该角色有所属用户,不可删除');
        }
        $res = $this->where(array('role_id'=>$role_id))->delete();
        if (!$res) {
            return array('code'=>0,'msg'=>'删除失败');
        } else {
            return array('code'=>1,'msg'=>'删除成功');
        }
    }

    /*
     *获取当前用户可以的操作的权限 以及新的权限
     */
    public function getNodeInfo($role_id){
        //获取权限
        $rule = $this->where(["role_id"=>$role_id])->field('menu_id')->find();
        $menu=new Authmenu();
        $result=$menu->field("id,name,parent_id as pid")->select();
        $str = "";
        if(!empty($rule["menu_id"])){
            $rules = explode(',', $rule["menu_id"]);
        }
        foreach($result as $key=>$vo){
            $str .= '{ "id": "' . $vo['id'] . '", "pId":"' . $vo['pid'] . '", "name":"' . $vo['name'].'"';

            if(!empty($rules) && in_array($vo['id'], $rules)){
                $str .= ' ,"checked":1';
            }

            $str .= '},';
        }

        return "[" . substr($str, 0, -1) . "]";

    }

}