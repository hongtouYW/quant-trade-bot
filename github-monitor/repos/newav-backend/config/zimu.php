<?php

return [
    'rsync' => [
        'user'     => 'rsyncuser',
        'host'     => '192.168.100.9',
        'module'   => 'ins',
        // 'password' => '/etc/rsyncd_ins.passwd',
        'password' => 'jw63$^fwqf8',
    ],
    
    'translate' => [ //thirdparty
        'api_url'      => 'https://qksf73pdev.com/api/jobs',
        'api_key'      => 'E04uOhBx3fHfr3YGmTKvbzbXI5e9Uqevxv8z7NOXcTPa12MAkfHFtWH6EURdSmZx',
        'merchant'     => 'insav',
        'timeout'      => 30,
    ],

    

    // 'translate' => [ //my own translate api
    //     'api_url'  => 'http://aijavtra.9xyrp3kg4b86.com/api/translate-text/',
    //     'api_key'  => 'anything',   // not validated by Django currently
    //     'merchant' => 'insav',   // not merchant by Django currently
    //     'timeout'  => 30,
    // ],
];
