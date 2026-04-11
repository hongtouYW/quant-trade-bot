package com.dj.user.activity.mine;

import android.content.Intent;
import android.os.Bundle;
import android.view.View;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.activity.FullScreenImageActivity;
import com.dj.user.databinding.ActivityFeedbackDetailsBinding;
import com.dj.user.model.response.Feedback;
import com.dj.user.util.StringUtil;
import com.google.gson.Gson;
import com.squareup.picasso.Picasso;

public class FeedbackDetailsActivity extends BaseActivity {

    private ActivityFeedbackDetailsBinding binding;
    private Feedback feedback;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityFeedbackDetailsBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        parseIntentData();
        setupToolbar(binding.toolbar.getRoot(), getString(R.string.app_feedback_details_title), 0, null);
        setupUI(feedback);
    }

    private void parseIntentData() {
        String json = getIntent().getStringExtra("data");
        if (json != null) {
            feedback = new Gson().fromJson(json, Feedback.class);
        }
    }

    private void setupUI(Feedback feedback) {
        if (feedback == null) {
            return;
        }
        binding.textViewSubject.setText(feedback.getTitle());
        binding.textViewMessage.setText(feedback.getFeedback_desc());
        String path = feedback.getPhoto();
        if (!StringUtil.isNullOrEmpty(path)) {
            String imageUrl = String.format(getString(R.string.template_s_s), getString(R.string.image_base_url), path);
            Picasso.get().load(imageUrl).centerInside().fit().into(binding.imageView);
            binding.imageView.setVisibility(View.VISIBLE);
            binding.imageView.setOnClickListener(view -> {
                Bundle bundle = new Bundle();
                bundle.putString("data", imageUrl);
                startAppActivity(new Intent(FeedbackDetailsActivity.this, FullScreenImageActivity.class),
                        bundle, false, false, true);
            });
        } else {
            binding.imageView.setVisibility(View.GONE);
        }
    }
}