<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>明顺</title>
    <link href="{{ asset('css/bootstrap.min.css') }}" rel="stylesheet">
    <script src="{{ asset('js/bootstrap.bundle.min.js')}}"></script>
    <script src="{{ asset('js/jquery.min.js')}}"></script>
    <link rel="stylesheet" href="{{ asset('css/select2.min.css') }}" />
    <script src="{{ asset('js/select2.min.js')}}"></script>
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <link href="{{ asset('css/notification.css') }}" rel="stylesheet">
    @yield('head')
<body>
    @include('widget.header')
    @include('widget.notification')
    @include('widget.sidebar')
    <div id="loader" class="loader-container">
        <div class="loader"></div>
    </div>
    <div class='main-container'>
        @yield('content')
    </div>
    @yield('styles')
    <script>
        $(document).ready(function() {
            $('.select2-init').select2();
            if ($("body").find(".datatable-table").length == 0 || typeof $("body").find(".datatable-table").length === "undefined") {
                setTimeout(function () {
                    $('#loader').hide();
                }, 500);
            }
        });
    </script>
    @yield('scripts')
</body>
