<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Baris Bahasa Pengesahan (Validation Language Lines)
    |--------------------------------------------------------------------------
    |
    | Baris bahasa berikut mengandungi mesej ralat lalai yang digunakan oleh
    | kelas validator. Sesetengah peraturan mempunyai beberapa versi seperti
    | peraturan saiz. Jangan ragu untuk mengubah suai setiap mesej di sini.
    |
    */

    'accepted' => ':attribute mesti diterima.',
    'accepted_if' => ':attribute mesti diterima apabila :other adalah :value.',
    'active_url' => ':attribute bukan URL yang sah.',
    'after' => ':attribute mestilah tarikh selepas :date.',
    'after_or_equal' => ':attribute mestilah tarikh selepas atau sama dengan :date.',
    'alpha' => ':attribute hanya boleh mengandungi huruf.',
    'alpha_dash' => ':attribute hanya boleh mengandungi huruf, nombor, sengkang dan garis bawah.',
    'alpha_num' => ':attribute hanya boleh mengandungi huruf dan nombor.',
    'array' => ':attribute mestilah sebuah tatasusunan (array).',
    'before' => ':attribute mestilah tarikh sebelum :date.',
    'before_or_equal' => ':attribute mestilah tarikh sebelum atau sama dengan :date.',
    'between' => [
        'numeric' => ':attribute mestilah antara :min dan :max.',
        'file' => ':attribute mestilah antara :min dan :max kilobait.',
        'string' => ':attribute mestilah antara :min dan :max aksara.',
        'array' => ':attribute mestilah mempunyai antara :min dan :max item.',
    ],
    'boolean' => 'Ruangan :attribute mestilah benar atau salah.',
    'confirmed' => 'Pengesahan :attribute tidak sepadan.',
    'current_password' => 'Kata laluan adalah salah.',
    'date' => ':attribute bukan tarikh yang sah.',
    'date_equals' => ':attribute mestilah tarikh yang sama dengan :date.',
    'date_format' => ':attribute tidak mengikut format :format.',
    'declined' => ':attribute mesti ditolak.',
    'declined_if' => ':attribute mesti ditolak apabila :other adalah :value.',
    'different' => ':attribute dan :other mestilah berbeza.',
    'digits' => ':attribute mestilah :digits digit.',
    'digits_between' => ':attribute mestilah antara :min dan :max digit.',
    'dimensions' => ':attribute mempunyai dimensi imej yang tidak sah.',
    'distinct' => 'Ruangan :attribute mempunyai nilai pendua.',
    'email' => ':attribute mestilah alamat e-mel yang sah.',
    'ends_with' => ':attribute mesti diakhiri dengan salah satu daripada: :values.',
    'exists' => ':attribute yang dipilih tidak sah.',
    'file' => ':attribute mestilah sebuah fail.',
    'filled' => 'Ruangan :attribute mesti mempunyai nilai.',
    'gt' => [
        'numeric' => ':attribute mestilah lebih besar daripada :value.',
        'file' => ':attribute mestilah lebih besar daripada :value kilobait.',
        'string' => ':attribute mestilah lebih panjang daripada :value aksara.',
        'array' => ':attribute mestilah mempunyai lebih daripada :value item.',
    ],
    'gte' => [
        'numeric' => ':attribute mestilah lebih besar daripada atau sama dengan :value.',
        'file' => ':attribute mestilah lebih besar daripada atau sama dengan :value kilobait.',
        'string' => ':attribute mestilah sekurang-kurangnya :value aksara.',
        'array' => ':attribute mestilah mempunyai :value item atau lebih.',
    ],
    'image' => ':attribute mestilah sebuah imej.',
    'in' => ':attribute yang dipilih tidak sah.',
    'in_array' => 'Ruangan :attribute tidak wujud dalam :other.',
    'integer' => ':attribute mestilah integer.',
    'ip' => ':attribute mestilah alamat IP yang sah.',
    'ipv4' => ':attribute mestilah alamat IPv4 yang sah.',
    'ipv6' => ':attribute mestilah alamat IPv6 yang sah.',
    'json' => ':attribute mestilah rentetan JSON yang sah.',
    'lt' => [
        'numeric' => ':attribute mestilah kurang daripada :value.',
        'file' => ':attribute mestilah kurang daripada :value kilobait.',
        'string' => ':attribute mestilah kurang daripada :value aksara.',
        'array' => ':attribute mestilah mempunyai kurang daripada :value item.',
    ],
    'lte' => [
        'numeric' => ':attribute mestilah kurang daripada atau sama dengan :value.',
        'file' => ':attribute mestilah kurang daripada atau sama dengan :value kilobait.',
        'string' => ':attribute mestilah tidak melebihi :value aksara.',
        'array' => ':attribute mestilah tidak mempunyai lebih daripada :value item.',
    ],
    'max' => [
        'numeric' => ':attribute tidak boleh lebih besar daripada :max.',
        'file' => ':attribute tidak boleh lebih besar daripada :max kilobait.',
        'string' => ':attribute tidak boleh melebihi :max aksara.',
        'array' => ':attribute tidak boleh mempunyai lebih daripada :max item.',
    ],
    'mimes' => ':attribute mestilah fail jenis: :values.',
    'mimetypes' => ':attribute mestilah fail jenis: :values.',
    'min' => [
        'numeric' => ':attribute mestilah sekurang-kurangnya :min.',
        'file' => ':attribute mestilah sekurang-kurangnya :min kilobait.',
        'string' => ':attribute mestilah sekurang-kurangnya :min aksara.',
        'array' => ':attribute mestilah mempunyai sekurang-kurangnya :min item.',
    ],
    'multiple_of' => ':attribute mestilah gandaan kepada :value.',
    'not_in' => ':attribute yang dipilih tidak sah.',
    'not_regex' => 'Format :attribute tidak sah.',
    'numeric' => ':attribute mestilah nombor.',
    'password' => 'Kata laluan adalah salah.',
    'present' => 'Ruangan :attribute mesti wujud.',
    'regex' => 'Format :attribute tidak sah.',
    'required' => 'Ruangan :attribute diperlukan.',
    'required_if' => 'Ruangan :attribute diperlukan apabila :other adalah :value.',
    'required_unless' => 'Ruangan :attribute diperlukan melainkan :other berada dalam :values.',
    'required_with' => 'Ruangan :attribute diperlukan apabila :values wujud.',
    'required_with_all' => 'Ruangan :attribute diperlukan apabila :values wujud.',
    'required_without' => 'Ruangan :attribute diperlukan apabila :values tidak wujud.',
    'required_without_all' => 'Ruangan :attribute diperlukan apabila tiada satu pun daripada :values wujud.',
    'prohibited' => 'Ruangan :attribute dilarang.',
    'prohibited_if' => 'Ruangan :attribute dilarang apabila :other adalah :value.',
    'prohibited_unless' => 'Ruangan :attribute dilarang melainkan :other berada dalam :values.',
    'prohibits' => 'Ruangan :attribute melarang :other daripada wujud.',
    'same' => ':attribute dan :other mestilah sama.',
    'size' => [
        'numeric' => ':attribute mestilah bersaiz :size.',
        'file' => ':attribute mestilah bersaiz :size kilobait.',
        'string' => ':attribute mestilah mempunyai :size aksara.',
        'array' => ':attribute mesti mengandungi :size item.',
    ],
    'starts_with' => ':attribute mesti bermula dengan salah satu daripada: :values.',
    'string' => ':attribute mestilah rentetan (string).',
    'timezone' => ':attribute mestilah zon masa yang sah.',
    'unique' => ':attribute telah pun diambil.',
    'uploaded' => ':attribute gagal dimuat naik.',
    'url' => ':attribute mestilah URL yang sah.',
    'uuid' => ':attribute mestilah UUID yang sah.',
    'phone' => 'Nombor telefon tidak sah',
    'search' => 'Carian tidak sah',

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'mesej-suai',
        ],
        'validation_error' => 'Pengesahan gagal.',
        'login' => [
            'required' => 'Ruangan nama pengguna diperlukan.',
            'max' => 'Nama pengguna tidak boleh melebihi 255 aksara.',
        ],
        'password' => [
            'required' => 'Ruangan kata laluan diperlukan.',
            'min' => 'Kata laluan mestilah sekurang-kurangnya 6 aksara.',
        ],
        'two_factor_code' => [
            'size' => 'Kod 2FA mestilah tepat 6 aksara.',
        ],
    ],

    'attributes' => [
        'email' => 'e-mel',
        'name' => 'nama',
        'password' => 'kata laluan',
    ],

    'refreshtoken_required' => 'Token penyegaran (refresh token) diperlukan.',
    'refreshtoken_invalid' => 'Token penyegaran (refresh token) tidak sah.',

];