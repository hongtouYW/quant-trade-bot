<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>明顺</title>
</head>

<body>
    <link href="{{ asset('css/notification.css') }}" rel="stylesheet">
    <link href="{{ asset('css/login.css') }}" rel="stylesheet">
    @include('widget.notification')
    <div id="bg"></div>
    <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="form-field">
            <input id='username' type="text" placeholder="用户名字" name='username' required value='{{ old('username') }}'>
        </div>

        <div class="form-field">
            <input id="password" type="password" placeholder="密码" name='password' required />
        </div>

        <div class="form-field">
            <button id="loginButton" class="btn" type="submit">登入</button>
        </div>
    </form>
</body>
