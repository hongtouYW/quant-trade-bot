package com.dj.user.activity;

import android.media.AudioManager;
import android.media.MediaMetadataRetriever;
import android.media.MediaPlayer;
import android.net.Uri;
import android.os.Bundle;
import android.os.Handler;
import android.view.animation.Animation;
import android.view.animation.LinearInterpolator;
import android.view.animation.RotateAnimation;

import androidx.activity.OnBackPressedCallback;
import androidx.appcompat.app.AppCompatActivity;

import com.dj.user.R;
import com.dj.user.databinding.ActivityAudioPlayerBinding;

import java.io.IOException;
import java.util.Locale;

public class AudioPlayerActivity extends AppCompatActivity {

    private ActivityAudioPlayerBinding binding;
    private MediaPlayer mediaPlayer;
    private Handler handler = new Handler();
    private Animation rotateAnimation;

    private final int[] audioResIds = {R.raw.sample_audio, R.raw.sample_audio_2};
    private int currentTrackIndex = 0;
    private boolean isPlaying = false;
    private boolean isRepeat = false;
    private boolean isShuffle = false;

    private final Runnable updateSeekbar = new Runnable() {
        @Override
        public void run() {
            if (mediaPlayer != null && isPlaying) {
                int currentPos = mediaPlayer.getCurrentPosition();
                binding.seekBar.setProgress(currentPos);
                binding.textViewStart.setText(formatTime(currentPos));
                handler.postDelayed(this, 1000);
            }
        }
    };

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setVolumeControlStream(AudioManager.STREAM_MUSIC);
        binding = ActivityAudioPlayerBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        getOnBackPressedDispatcher().addCallback(this, new OnBackPressedCallback(true) {
            @Override
            public void handleOnBackPressed() {
                stopPlaybackAndFinish();
            }
        });

        binding.textViewSongTitle.setSelected(true); // Enable marquee

        setupRotationAnimation();
        initMediaPlayer();

        // Playback controls
        binding.btnPlay.setOnClickListener(v -> togglePlayback());
        binding.btnNext.setOnClickListener(v -> switchTrack(true));
        binding.btnPrev.setOnClickListener(v -> switchTrack(false));

        // Repeat
        binding.btnRepeat.setOnClickListener(v -> {
            isRepeat = !isRepeat;
            binding.btnRepeat.setImageResource(isRepeat ? R.drawable.ic_repeat : R.drawable.ic_repeat_off);
        });

        // Shuffle
        binding.btnShuffle.setOnClickListener(v -> {
            isShuffle = !isShuffle;
            binding.btnShuffle.setImageResource(isShuffle ? R.drawable.ic_shuffle : R.drawable.ic_shuffle_off);
        });

        // SeekBar
        binding.seekBar.setOnSeekBarChangeListener(new android.widget.SeekBar.OnSeekBarChangeListener() {
            @Override
            public void onProgressChanged(android.widget.SeekBar seekBar, int progress, boolean fromUser) {
                if (fromUser && mediaPlayer != null) {
                    mediaPlayer.seekTo(progress);
                    binding.textViewStart.setText(formatTime(progress));
                }
            }

            @Override
            public void onStartTrackingTouch(android.widget.SeekBar seekBar) {
            }

            @Override
            public void onStopTrackingTouch(android.widget.SeekBar seekBar) {
            }
        });
    }

    private void stopPlaybackAndFinish() {
        handler.removeCallbacks(updateSeekbar);
        if (mediaPlayer != null) {
            mediaPlayer.stop();
            mediaPlayer.release();
            mediaPlayer = null;
        }
        binding.imageViewAlbum.clearAnimation();
        finish();
    }

    private void togglePlayback() {
        if (mediaPlayer == null) return;

        if (mediaPlayer.isPlaying()) {
            mediaPlayer.pause();
            isPlaying = false;
            binding.btnPlay.setImageResource(R.drawable.ic_play);
            binding.imageViewAlbum.clearAnimation();
            handler.removeCallbacks(updateSeekbar);
        } else {
            mediaPlayer.start();
            isPlaying = true;
            binding.btnPlay.setImageResource(R.drawable.ic_pause);
            binding.imageViewAlbum.startAnimation(rotateAnimation);
            handler.post(updateSeekbar);
        }
    }

    private void switchTrack(boolean isNext) {
        handler.removeCallbacks(updateSeekbar);
        binding.imageViewAlbum.clearAnimation();

        if (isShuffle) {
            int newIndex;
            do {
                newIndex = (int) (Math.random() * audioResIds.length);
            } while (newIndex == currentTrackIndex && audioResIds.length > 1);
            currentTrackIndex = newIndex;
        } else {
            if (isNext) {
                currentTrackIndex = (currentTrackIndex + 1) % audioResIds.length;
            } else {
                currentTrackIndex = (currentTrackIndex - 1 + audioResIds.length) % audioResIds.length;
            }
        }

        if (mediaPlayer != null) {
            mediaPlayer.stop();
            mediaPlayer.release();
        }

        initMediaPlayer();
        togglePlayback();
    }

    private void initMediaPlayer() {
        mediaPlayer = MediaPlayer.create(this, audioResIds[currentTrackIndex]);
        binding.seekBar.setMax(mediaPlayer.getDuration());
        binding.textViewEnd.setText(formatTime(mediaPlayer.getDuration()));
        binding.textViewStart.setText(formatTime(0));
        loadMetadata(audioResIds[currentTrackIndex]);

        mediaPlayer.setOnCompletionListener(mp -> {
            handler.removeCallbacks(updateSeekbar);
            binding.imageViewAlbum.clearAnimation();

            if (isRepeat) {
                mediaPlayer.seekTo(0);
                mediaPlayer.start();
                binding.imageViewAlbum.startAnimation(rotateAnimation);
                isPlaying = true;
                handler.post(updateSeekbar);
            } else {
                playNextTrack();
            }
        });
    }

    private void playNextTrack() {
        currentTrackIndex = isShuffle
                ? (int) (Math.random() * audioResIds.length)
                : (currentTrackIndex + 1) % audioResIds.length;

        if (mediaPlayer != null) {
            mediaPlayer.stop();
            mediaPlayer.release();
        }

        initMediaPlayer();
        togglePlayback();
    }

    private void loadMetadata(int resId) {
        MediaMetadataRetriever retriever = new MediaMetadataRetriever();
        try {
            Uri uri = Uri.parse("android.resource://" + getPackageName() + "/" + resId);
            retriever.setDataSource(this, uri);

            String title = retriever.extractMetadata(MediaMetadataRetriever.METADATA_KEY_TITLE);
            String artist = retriever.extractMetadata(MediaMetadataRetriever.METADATA_KEY_ARTIST);

            binding.textViewSongTitle.setText(title != null ? title : "无标题");
            binding.textViewArtist.setText(artist != null ? artist : "未知艺术家");
        } catch (Exception e) {
            binding.textViewSongTitle.setText("无标题");
            binding.textViewArtist.setText("未知艺术家");
        } finally {
            try {
                retriever.release();
            } catch (RuntimeException | IOException ignored) {
            }
        }
    }

    private void setupRotationAnimation() {
        rotateAnimation = new RotateAnimation(0f, 360f,
                Animation.RELATIVE_TO_SELF, 0.5f,
                Animation.RELATIVE_TO_SELF, 0.5f);
        rotateAnimation.setDuration(8000);
        rotateAnimation.setRepeatCount(Animation.INFINITE);
        rotateAnimation.setInterpolator(new LinearInterpolator());
    }

    private String formatTime(int millis) {
        int minutes = millis / 1000 / 60;
        int seconds = (millis / 1000) % 60;
        return String.format(Locale.getDefault(), "%02d:%02d", minutes, seconds);
    }

    @Override
    protected void onDestroy() {
        super.onDestroy();
        handler.removeCallbacks(updateSeekbar);
        if (mediaPlayer != null) {
            mediaPlayer.release();
            mediaPlayer = null;
        }
        binding.imageViewAlbum.clearAnimation();
    }
}
