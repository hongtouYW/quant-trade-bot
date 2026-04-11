<?php

namespace app\lib\exception;

use think\Exception;

class BaseException extends Exception
{
    public $code = 0;

    public function __construct($code='')
    {
        if($code){
            $this->code = $code;
        }

    }

}