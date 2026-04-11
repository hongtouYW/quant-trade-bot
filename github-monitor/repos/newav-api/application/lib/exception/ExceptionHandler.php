<?php

namespace app\lib\exception;

use think\exception\Handle;
use Exception;


class ExceptionHandler extends Handle
{
    private $code;
    public function render(Exception $e)
    {
        if ($e instanceof BaseException)
        {
            $this->code = $e->code;
        }
        else{
            if(config('app_debug')){
                return parent::render($e);
            }
            $this->code = 999;
        }
        return show($this->code);
    }

}