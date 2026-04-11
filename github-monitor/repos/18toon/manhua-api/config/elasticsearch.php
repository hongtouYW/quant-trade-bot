<?php

use think\facade\Env;

return [
    'host' => Env::get('elastic.host', 'https://127.0.0.1:9200'),
    'username' => Env::get('elastic.username', 'elastic'),
    'password' => Env::get('elastic.password'),
    'verify_ssl' => filter_var(Env::get('elastic.verify_ssl', false), FILTER_VALIDATE_BOOLEAN),
];