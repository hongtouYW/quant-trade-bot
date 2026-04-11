<?php
namespace app\index\behavior;

class Cors
{

    public function run(){
        header('Access-Control-Allow-Origin:*');
        header('Access-Control-Allow-Methods:*');
        header('Access-Control-Allow-Headers:token,suffix,Origin,X-Requested-With,Content-Type,content-type,Accept,Authorized-Token,Authori-zation,Authorization,authorized-token,If-Match, If-Modified-Since, If-None-Match, If-Unmodified-Since');
        header('Access-Control-Allow-Credentials:false');
        if (request()->isOptions()) {
            header('Access-Control-Allow-Origin:*');
            header('Access-Control-Allow-Methods:*');
            header('Access-Control-Allow-Headers:token,suffix,Origin,X-Requested-With,Content-Type,content-type,Accept,Authorized-Token,Authori-zation,Authorization,authorized-token,If-Match, If-Modified-Since, If-None-Match, If-Unmodified-Since');
            header('Access-Control-Allow-Credentials:false');
        }

    }

}