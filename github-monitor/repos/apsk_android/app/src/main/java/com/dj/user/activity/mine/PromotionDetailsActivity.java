package com.dj.user.activity.mine;

import android.animation.ObjectAnimator;
import android.animation.ValueAnimator;
import android.os.Bundle;
import android.view.animation.LinearInterpolator;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.databinding.ActivityPromotionDetailsBinding;
import com.dj.user.model.request.RequestProfile;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Member;
import com.dj.user.model.response.Player;
import com.dj.user.model.response.Promotion;
import com.dj.user.model.response.YxiProvider;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.dj.user.util.FormatUtils;
import com.dj.user.util.StringUtil;
import com.google.gson.Gson;
import com.squareup.picasso.Callback;
import com.squareup.picasso.Picasso;

import java.util.List;

public class PromotionDetailsActivity extends BaseActivity {
    private ActivityPromotionDetailsBinding binding;
    private Member member;
    private Promotion promotion;
    private double totalPoints = 0.0;
    private ObjectAnimator refreshAnimator;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityPromotionDetailsBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        member = CacheManager.getObject(this, CacheManager.KEY_USER_PROFILE, Member.class);

        parseIntentData();
        setupToolbar(binding.toolbar.getRoot(), promotion.getTitle(), 0, null);
        setupUI();
    }

    @Override
    protected void onResume() {
        super.onResume();
        getProfile();
        getYxiTransferList();
    }

    private void parseIntentData() {
        String json = getIntent().getStringExtra("data");
        if (json != null) {
            promotion = new Gson().fromJson(json, Promotion.class);
        }
    }

    private void setupUI() {
        binding.textViewBalance.setText(FormatUtils.formatAmount(member.getBalance()));
        binding.imageViewRefresh.setOnClickListener(view -> {
            getProfile();
            getYxiTransferList();
        });

        String image = promotion.getPhoto();
        if (!StringUtil.isNullOrEmpty(image)) {
            if (!image.startsWith("http")) {
                image = String.format(getString(R.string.template_s_s), getString(R.string.image_base_url), image);
            }
            Picasso.get().load(image).into(binding.imageView, new Callback() {
                @Override
                public void onSuccess() {
                }

                @Override
                public void onError(Exception e) {
                    binding.imageView.setImageResource(R.drawable.img_promotion_default);
                }
            });
        } else {
            binding.imageView.setImageResource(R.drawable.img_promotion_default);
        }
        binding.textViewPromotionTitle.setText(promotion.getTitle());
        binding.textViewDesc.setText(promotion.getPromotion_desc());
    }

    private void startRefreshAnimation() {
        if (refreshAnimator == null) {
            refreshAnimator = ObjectAnimator.ofFloat(binding.imageViewRefresh, "rotation", 0f, 360f);
            refreshAnimator.setDuration(300);
            refreshAnimator.setRepeatCount(ValueAnimator.INFINITE);
            refreshAnimator.setInterpolator(new LinearInterpolator());
        }
        refreshAnimator.start();
    }

    private void stopRefreshAnimation() {
        if (refreshAnimator != null && refreshAnimator.isRunning()) {
            refreshAnimator.cancel();
            binding.imageViewRefresh.setRotation(0f);
        }
    }

    private void getProfile() {
        startRefreshAnimation();
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        executeApiCall(this, apiService.getProfile(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Member> response) {
                member = response.getData();
                if (member != null) {
                    CacheManager.saveObject(PromotionDetailsActivity.this, CacheManager.KEY_USER_PROFILE, member);
                    binding.textViewBalance.setText(FormatUtils.formatAmount(member.getBalance()));
                }
                stopRefreshAnimation();
            }

            @Override
            public boolean onApiError(int code, String message) {
                stopRefreshAnimation();
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                stopRefreshAnimation();
                return false;
            }
        }, false);
    }

    private void getYxiTransferList() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        executeApiCall(this, apiService.getYxiTransferList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<YxiProvider>> response) {
                totalPoints = 0.0;
                List<YxiProvider> providerList = response.getData();
                // Handle empty or null list early
                if (providerList == null || providerList.isEmpty()) {
                    binding.textViewPoint.setText(FormatUtils.formatAmount(totalPoints));
                    stopRefreshAnimation();
                    return;
                }
                // Calculate total points
                totalPoints = providerList.stream()
                        .filter(p -> p.getPlayer() != null)
                        .flatMap(p -> p.getPlayer().stream())
                        .mapToDouble(Player::getBalance)
                        .sum();
                binding.textViewPoint.setText(FormatUtils.formatAmount(totalPoints));
                stopRefreshAnimation();
            }

            @Override
            public boolean onApiError(int code, String message) {
                stopRefreshAnimation();
                return true;
            }

            @Override
            public boolean onFailure(Throwable t) {
                stopRefreshAnimation();
                return true;
            }
        }, false);
    }
}