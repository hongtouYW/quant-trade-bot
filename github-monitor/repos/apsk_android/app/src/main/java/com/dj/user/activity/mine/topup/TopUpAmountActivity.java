package com.dj.user.activity.mine.topup;

import android.content.Intent;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.widget.TextView;

import androidx.core.content.ContextCompat;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.activity.mine.bank.BankOptionActivity;
import com.dj.user.databinding.ActivityTopUpAmountBinding;
import com.dj.user.model.ItemChip;
import com.google.android.flexbox.FlexboxLayout;

import java.util.Arrays;
import java.util.List;

public class TopUpAmountActivity extends BaseActivity {

    private ActivityTopUpAmountBinding binding;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityTopUpAmountBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        setupToolbar(binding.toolbar.getRoot(), "线上银行充值", 0, null);
        setupAmountFlexbox();
        setupUI();
    }

    private void setupAmountFlexbox() {
        FlexboxLayout flexboxLayout = binding.flexboxLayoutAmount;
        LayoutInflater inflater = LayoutInflater.from(this);

        List<ItemChip> options = Arrays.asList(
                new ItemChip("50"),
                new ItemChip("100"),
                new ItemChip("250"),
                new ItemChip("500"),
                new ItemChip("1000")
        );

        for (ItemChip chip : options) {
            View chipView = inflater.inflate(R.layout.item_chip_filter, flexboxLayout, false);
            TextView chipLabel = chipView.findViewById(R.id.chip_label);

            chipLabel.setText(chip.getLabel());
            chipLabel.setTextColor(ContextCompat.getColor(this, R.color.orange_F8AF07));
            chipView.setOnClickListener(v -> {
                for (int i = 0; i < flexboxLayout.getChildCount(); i++) {
                    View child = flexboxLayout.getChildAt(i);
                    TextView textView = child.findViewById(R.id.chip_label);

                    textView.setTextColor(ContextCompat.getColor(this, R.color.orange_F8AF07));
                    child.setBackgroundResource(R.drawable.bg_button_bordered_transparent);
                }
                chipLabel.setTextColor(ContextCompat.getColor(this, R.color.black_000000));
                chipView.setBackgroundResource(R.drawable.bg_button_orange);

                binding.editTextAmount.setText(chip.getLabel());
            });
            flexboxLayout.addView(chipView);
        }
    }

    private void setupUI() {
        binding.buttonChoose.setOnClickListener(view -> {
            startAppActivity(new Intent(TopUpAmountActivity.this, BankOptionActivity.class),
                    null, false, false, true);
        });
    }
}