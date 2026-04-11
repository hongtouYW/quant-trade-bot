<?php

return [
    'import_secret' => 'a7X#9kL2mQp4Zr8f',
    'token_expire' => 300,
    'allowed_langs' => ['en'],
    
    'rsync_host' => env('import_pic.rsync_host', '127.0.0.1'),
    'rsync_module' => env('import_pic.rsync_module', 'mm_comic/mh'),
    'rsync_user' => env('import_pic.rsync_user', 'rsync_user'),
    'rsync_pass' => env('import_pic.rsync_pass', ''),
    'rsync_pass_file' => env('import_pic.rsync_pass_file', '/etc/rsyncd_mh.passwd'),
    'save_path' => env('import_pic.save_path', '/mnt/c/temp/rsync_test/'),
];
