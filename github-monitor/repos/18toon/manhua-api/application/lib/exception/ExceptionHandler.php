<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2019/10/8
 * Time: 14:52
 */

namespace app\lib\exception;

use think\exception\Handle;
use Exception;


class ExceptionHandler extends Handle
{
    private $code;
    private $data;
    public function render(Exception $e)
    {
        if ($e instanceof BaseException)
        {
            $this->code = $e->code;
            $this->data = $e->data;
        }
        else{
            if(config('app_debug')){
                return parent::render($e);
            }
            $this->code = 999;
        }
        return show($this->code,$this->data);
    }

}