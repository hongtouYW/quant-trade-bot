<link href="{{ asset('css/video-js.min.css') }}" rel="stylesheet">
<script src="{{ asset('js/video.min.js') }}"></script>
<div class="modal video-modal" id="video-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" id="close-hls-video">
                    <span>×</span>
                </button>
            </div>
            <div class="video-modal-body modal-body">
                <video id="hls" class="video-js vjs-default-skin" width="875" height="500" controls>
                    <source id='hls-video-source' type="video/mp4" src="">
                </video>
            </div>
        </div>
    </div>
</div>

@if ($script)
    <script>
        $(document).ready(function() {
            $(".open-hls-video").click(function() {
                $('#video-modal').show();
                $("body").addClass("modal-open");
                $('#hls-video-source').attr('src', $(this).attr('data'));
                $('#hls').attr('src', $(this).attr('data'));
                var player = videojs("hls");
                player.play();
            });
            $('#close-hls-video').click(function() {
                closeVideo();
            });

            var modal = document.getElementById('video-modal');
            window.onclick = function(event) {
                if (event.target == modal) {
                    closeVideo();
                }
            }

            function closeVideo() {
                $('#video-modal').hide();
                $("body").removeClass("modal-open");
                var player = videojs("hls");
                player.pause();
                player.dispose();
                $('.video-modal-body').append(`<video id="hls" class="video-js vjs-default-skin" width="875" height="500" controls>
                        <source id='hls-video-source' type="video/mp4" src="">
                    </video>`);
            }
        });
    </script>
@endif
