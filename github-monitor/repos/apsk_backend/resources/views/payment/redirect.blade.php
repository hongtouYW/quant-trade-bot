@php
    use Illuminate\Support\Facades\Crypt;
    use Carbon\Carbon;

    $data = json_decode(Crypt::decryptString($payload), true);
    $paymentUrl = $data['paymentUrl'];
    $device = $data['device'];
    $provider = $data['provider'];
    $creditId = $data['credit_id'];
@endphp

        <!DOCTYPE html>
<html lang="en">
<meta name="viewport" content="width=device-width, initial-scale=1">
<head>
    <meta charset="UTF-8">
    <title>Processing Payment...</title>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(180deg, #00143d, #0b1d48);
            margin: 0;
            padding: 0;
            color: #fff;
        }
        iframe {
            position: absolute;
            top: 0;
            left: 0;
            border: none;
            width: 100vw;
            height: 100vh;
        }
        .container {
            max-width: 420px;
            margin: 80px auto;
            padding: 24px;
            background: #0f255c;
            border-radius: 16px;
            text-align: center;
            display:none;
        }
        .icon {
            width: 80px; height: 80px; border-radius: 50%;
            display:flex; align-items:center; justify-content:center;
            margin:0 auto 16px; font-size:40px;
        }
        .success-icon { background:rgba(40,167,69,0.2); color:#28a745; }
        .failed-icon { background:rgba(220,53,69,0.2); color:#dc3545; }
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

    <script>
        document.cookie = "credit_id={{ $creditId }}; path=/;";
        window.location.href = "{!! $paymentUrl !!}";
    </script>


<div class="container" id="result">
    <div id="icon" class="icon"></div>
    <div id="status" class="status"></div>
    <div id="amount" class="amount"></div>
    <div id="detail" class="detail"></div>
    <a href="/" class="btn" id="home">Back to Home</a>
</div>


<script>
    $(function() {
        const payload = "{{ $payload }}";
        const device = "{{ $device }}";

        // Poll status every 1 second
        const interval = setInterval(() => {
            $.ajax({
                url: `{{ config('app.urlapi').'payment/status' }}/${payload}`,
                method: "GET",
                dataType: "json",
                success: function (response) {
                    if (!response || !response.status) return;

                    const tbl_credit = response.credit;
                    if (!tbl_credit) return;

                    // Pending
                    if (tbl_credit.status === 0) return;

                    // Stop polling
                    clearInterval(interval);

                    // Hide iframe if superpay was used
                    const iframe = document.getElementById('paymentFrame');
                    if (iframe) iframe.style.display = 'none';

                    // Fill result
                    $("#amount").text("RM " + tbl_credit.amount);
                    $("#detail").text(response.message);
                    $("#status").text(response.message);

                    if (tbl_credit.status === 1)
                        $("#icon").addClass("success-icon").text("✓");
                    else
                        $("#icon").addClass("failed-icon").text("✗");

                    let credit_id = tbl_credit.credit_id;

                    // Device redirect
                    if (device === "web") {
                        $("#home").attr("href", `{{config('app.urldownload')}}?method=online&id=${credit_id}`);
                    } else if (device === "android") {
                        $("#home").attr("href", `apsk://payment?id=${credit_id}&method=online`);
                    }

                    $("#result").show();
                }
            });
        }, 1000);
    });
</script>

</body>
</html>
