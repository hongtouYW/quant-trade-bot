<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/4/26
 * Time: 17:01
 */

namespace app\lib\exception;

use think\Exception;

class BaseException extends Exception
{
    public $code = 0;
    public $data = [];

    public function __construct($code='',$data=[])
    {
        if($code){
            $this->code = $code;
        }
        if($data){
            $this->data = $data;
        }

    }

}