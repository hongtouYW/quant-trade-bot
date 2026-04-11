<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment Result</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(180deg, #00143d, #0b1d48);
            margin: 0;
            padding: 40px;
            color: #fff;
            text-align: center;
        }
        .box {
            max-width: 420px;
            margin: 0 auto;
            padding: 24px;
            background: #0f255c;
            border-radius: 16px;
        }
        .icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            font-size: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
        }
        .success { background: rgba(40,167,69,0.2); color: #28a745; }
        .failed { background: rgba(220,53,69,0.2); color: #dc3545; }

        a.btn {
            display: block;
            margin-top: 20px;
            padding: 12px;
            background: #1b3a7a;
            color: #fff;
            border-radius: 10px;
            text-decoration: none;
        }
    </style>
</head>

<body>
<div class="box">
    @if ($status == 1)
        <div class="icon success">✓</div>
        <h2>Transaction Successful</h2>
    @else
        <div class="icon failed">✗</div>
        <h2>Transaction Failed</h2>
    @endif
    <p>Amount: RM {{ $credit->amount }}</p>
    <p>Order ID: {{ $credit->orderid }}</p>
    <a href="#" id="home" class="btn">Back to Home</a>
</div>
</body>
</html>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
    $(function() {
        const creditId = "{{ $credit->credit_id }}";
        const device = "{{ $device ?? 'web' }}"; // default to web if not set
        const deeplink = `apsk://payment?method=online&id=${creditId}`;

        if (creditId) {
            if (device === "web") {
                $("#home").attr("href", `{{ config('app.urldownload') }}?method=online&id=${creditId}`);
            } else if (device === "android") {
                $("#home").attr("href", deeplink);
            }
        } else {
            // fallback
            $("#home").attr("href", "/");
        }
    });
</script>
