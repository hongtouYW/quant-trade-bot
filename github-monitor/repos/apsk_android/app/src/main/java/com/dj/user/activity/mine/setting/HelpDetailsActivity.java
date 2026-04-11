package com.dj.user.activity.mine.setting;

import android.os.Bundle;
import android.view.View;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.databinding.ActivityHelpDetailsBinding;
import com.dj.user.model.response.FAQ;
import com.dj.user.util.StringUtil;
import com.google.gson.Gson;
import com.squareup.picasso.Callback;
import com.squareup.picasso.Picasso;

public class HelpDetailsActivity extends BaseActivity {

    private ActivityHelpDetailsBinding binding;
    private FAQ faq;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityHelpDetailsBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        parseIntentData();
        setupToolbar(binding.toolbar.getRoot(), getString(R.string.help_details_title), 0, null);
        setupUI();
    }

    private void parseIntentData() {
        String json = getIntent().getStringExtra("data");
        if (json != null) {
            faq = new Gson().fromJson(json, FAQ.class);
        }
    }

    private void setupUI() {
        binding.textViewSubject.setText(faq.getTitle());
        binding.textViewSubject.setVisibility(!StringUtil.isNullOrEmpty(faq.getTitle()) ? View.VISIBLE : View.GONE);
        binding.textViewDesc.setText(faq.getQuestion_desc().replace("\\n", "\n"));
        binding.textViewDesc.setVisibility(!StringUtil.isNullOrEmpty(faq.getQuestion_desc()) ? View.VISIBLE : View.GONE);

        String icon = faq.getPicture();
        if (!StringUtil.isNullOrEmpty(icon)) {
            if (!icon.startsWith("http")) {
                icon = String.format(getString(R.string.template_s_s),
                        getString(R.string.image_base_url), icon);
            }
            Picasso.get().load(icon).into(binding.imageView, new Callback() {
                @Override
                public void onSuccess() {
                    binding.imageView.setVisibility(View.VISIBLE);
                }

                @Override
                public void onError(Exception e) {
                    binding.imageView.setVisibility(View.GONE);
                }
            });
        } else {
            binding.imageView.setVisibility(View.GONE);
        }
    }
}