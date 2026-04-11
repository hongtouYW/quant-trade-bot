package com.dj.manager.activity.point;

import android.content.Intent;
import android.graphics.Paint;
import android.graphics.Typeface;
import android.os.Bundle;
import android.view.View;

import androidx.core.content.ContextCompat;
import androidx.core.content.res.ResourcesCompat;

import com.dj.manager.R;
import com.dj.manager.activity.BaseActivity;
import com.dj.manager.activity.shop.ShopDetailsActivity;
import com.dj.manager.activity.user.UserDetailsActivity;
import com.dj.manager.activity.yxi.YxiPlayerDetailsActivity;
import com.dj.manager.databinding.ActivityPointDetailsBinding;
import com.dj.manager.enums.PointType;
import com.dj.manager.model.response.Point;
import com.dj.manager.util.DateFormatUtils;
import com.dj.manager.util.FormatUtils;
import com.google.gson.Gson;

public class PointsDetailsActivity extends BaseActivity {
    private ActivityPointDetailsBinding binding;
    private Point point;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityPointDetailsBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        parseIntentData();
        setupToolbar(binding.toolbar.getRoot(), getString(R.string.point_details_title), 0, null);
        setupUI();
    }

    private void parseIntentData() {
        String json = getIntent().getStringExtra("data");
        if (json != null) {
            point = new Gson().fromJson(json, Point.class);
        }
    }

    private void setupUI() {
        PointType pointType = PointType.fromValue(point.getType());

        binding.textViewType.setText(pointType.getTitle());
        binding.imageViewType.setImageResource(pointType.getIconResId());
        binding.textViewStatus.setVisibility(point.getStatus() == 0 ? View.VISIBLE : View.GONE);

        double amount = point.getAmount();
        String amountStr = FormatUtils.formatAmount(Math.abs(amount));
        binding.textViewAmount.setText(String.format(getString(R.string.template_s_s), pointType.getSymbol(), amountStr));
        binding.textViewAmount.setTextColor(ContextCompat.getColor(this, pointType.getColorResId()));
        if (point.getStatus() == 0) {
            binding.textViewAmount.setPaintFlags(binding.textViewAmount.getPaintFlags() | Paint.STRIKE_THRU_TEXT_FLAG);
        }
        binding.textViewShopName.setText(point.getShopName());
        if (point.getShopName().equalsIgnoreCase("-")) {
            binding.textViewShopName.setTextColor(ContextCompat.getColor(this, R.color.white_FFFFFF));
        } else {
            binding.textViewShopName.setTextColor(ContextCompat.getColor(this, R.color.gold_D4AF37));
            binding.textViewShopName.setOnClickListener(view -> {
                Bundle bundle = new Bundle();
                bundle.putString("data", new Gson().toJson(point.getShop()));
                startAppActivity(new Intent(PointsDetailsActivity.this, ShopDetailsActivity.class),
                        bundle, false, false, false, true);
            });
        }
        binding.textViewId.setText(point.getPlayerLogin());
        binding.textViewId.setOnClickListener(view -> {
            Bundle bundle = new Bundle();
            bundle.putString("data", new Gson().toJson(point.getGamemember()));
            startAppActivity(new Intent(PointsDetailsActivity.this, YxiPlayerDetailsActivity.class),
                    bundle, false, false, false, true);
        });
        binding.textViewDateTime.setText(DateFormatUtils.formatIsoDate(point.getCreated_on(), DateFormatUtils.FORMAT_YYYY_MM_DD_HH_MM_A));
        binding.textViewMemberPhone.setText(point.getPhoneNumber());
        binding.textViewMemberPhone.setOnClickListener(view -> {
            Bundle bundle = new Bundle();
            bundle.putString("data", new Gson().toJson(point.getMember()));
            startAppActivity(new Intent(PointsDetailsActivity.this, UserDetailsActivity.class),
                    bundle, false, false, false, true);
        });
        binding.textViewOperatedBy.setText(point.isCreatedByShop() ? point.getShopName() : getString(R.string.point_details_operated_by_user));
        binding.textViewIp.setText(point.getIp());
        binding.textViewShopBeforeBalance.setText(String.format(getString(R.string.template_currency_amount), FormatUtils.formatAmount(point.getShopBalance()))); // TODO: 12/02/2026  
        binding.textViewShopAfterBalance.setText(String.format(getString(R.string.template_currency_amount), FormatUtils.formatAmount(point.getShopBalance())));
        binding.textViewBeforeBalance.setText(String.format(getString(R.string.template_currency_amount), FormatUtils.formatAmount(point.getBefore_balance())));
        binding.textViewAfterBalance.setText(String.format(getString(R.string.template_currency_amount), FormatUtils.formatAmount(point.getAfter_balance())));

        binding.textViewShopBeforeBalance.setTextColor(ContextCompat.getColor(this, R.color.gray_C2C3CB));
        binding.textViewShopBeforeBalance.setAlpha(1F);
        binding.textViewShopAfterBalance.setTextColor(ContextCompat.getColor(this, R.color.gray_C2C3CB));
        binding.textViewShopAfterBalance.setAlpha(1F);
        binding.textViewBeforeBalance.setTextColor(ContextCompat.getColor(this, R.color.gray_C2C3CB));
        binding.textViewBeforeBalance.setAlpha(1F);
        binding.textViewAfterBalance.setTextColor(ContextCompat.getColor(this, R.color.gray_C2C3CB));
        binding.textViewAfterBalance.setAlpha(1F);
        Typeface boldTypeface = ResourcesCompat.getFont(this, R.font.poppins_semi_bold);
        if (pointType == PointType.WITHDRAWAL) {
            if (point.getStatus() == 1) {
                binding.textViewShopAfterBalance.setTextColor(ContextCompat.getColor(this, R.color.white_FFFFFF));
                binding.textViewShopAfterBalance.setTypeface(boldTypeface);
            } else {
                binding.textViewShopBeforeBalance.setAlpha(0.4F);
                binding.textViewShopAfterBalance.setAlpha(0.4F);
            }
            binding.textViewBeforeBalance.setAlpha(0.4F);
            binding.textViewAfterBalance.setAlpha(0.4F);
        } else {
            binding.textViewShopBeforeBalance.setAlpha(0.4F);
            binding.textViewShopAfterBalance.setAlpha(0.4F);
            if (point.getStatus() == 1) {
                binding.textViewAfterBalance.setTextColor(ContextCompat.getColor(this, R.color.white_FFFFFF));
                binding.textViewAfterBalance.setTypeface(boldTypeface);
            } else {
                binding.textViewBeforeBalance.setAlpha(0.4F);
                binding.textViewAfterBalance.setAlpha(0.4F);
            }
        }
    }
}