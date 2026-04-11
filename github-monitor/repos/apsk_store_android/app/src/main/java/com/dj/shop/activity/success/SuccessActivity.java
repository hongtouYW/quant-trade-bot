package com.dj.shop.activity.success;

import android.content.Intent;
import android.os.Bundle;
import android.view.View;

import com.dj.shop.R;
import com.dj.shop.activity.BaseActivity;
import com.dj.shop.activity.PrinterActivity;
import com.dj.shop.activity.dashboard.DashboardActivity;
import com.dj.shop.activity.transaction.ActionUserDetailsActivity;
import com.dj.shop.databinding.ActivitySuccessBinding;
import com.dj.shop.enums.ActionType;
import com.dj.shop.model.SuccessConfig;
import com.dj.shop.model.response.Shop;
import com.dj.shop.util.CacheManager;
import com.dj.shop.util.DateFormatUtils;
import com.dj.shop.util.PrinterUtil;
import com.dj.shop.util.StringUtil;
import com.google.gson.Gson;

public class SuccessActivity extends BaseActivity {
    private ActivitySuccessBinding binding;
    private Shop shop;
    private SuccessConfig config;
    private ActionType actionType;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivitySuccessBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        shop = CacheManager.getObject(this, CacheManager.KEY_SHOP_PROFILE, Shop.class);

        parseIntentData();
        if (config == null) {
            finish();
            return;
        }
        actionType = config.actionType;
        setupUI();
        if (actionType == ActionType.CONNECT_PRINTER) {
            PrinterUtil printerUtil = PrinterUtil.getInstance();
            if (printerUtil.isConnected()) {
                printerUtil.printTextFormatted(getString(R.string.print_connected), 0, false, false);
            }
        }
    }

    private void parseIntentData() {
        String json = getIntent().getStringExtra("data");
        if (json != null) {
            config = new Gson().fromJson(json, SuccessConfig.class);
        }
    }

    private void setupUI() {
        // Title
        binding.textViewTitle.setText(format(config.titleFormat, config.titleArgs));
        // Message
        if (config.messageFormat != null) {
            binding.textViewMessage.setVisibility(View.VISIBLE);
            binding.textViewMessage.setText(format(config.messageFormat, config.messageArgs));
        } else {
            binding.textViewMessage.setVisibility(View.GONE);
        }
        // Args
        if (config.messageArgs != null) {
            binding.panelDetails.setVisibility(View.VISIBLE);
            binding.panelPhone.setVisibility(View.VISIBLE);
            binding.textViewId.setText(config.messageArgs[0].toString());
            binding.textViewId.setOnClickListener(view -> {
                StringUtil.copyToClipboard(this, "", config.messageArgs[0].toString()
                        .replaceAll("\\+", "")
                        .replaceAll("\\s+", ""));
            });
            if (actionType == ActionType.TOP_UP || actionType == ActionType.WITHDRAWAL) {
                binding.panelPasswordBalance.setVisibility(View.VISIBLE);
                binding.textViewPasswordBalance.setText(getString(R.string.details_account_balance));
                binding.textViewAmount.setText(config.messageArgs[1].toString());
            } else {
                if (shop.canViewCredentials()) {
                    binding.panelPasswordBalance.setVisibility(View.VISIBLE);
                    binding.textViewPasswordBalance.setText(getString(R.string.details_password));
                    binding.textViewAmount.setText(config.messageArgs[1].toString());
                    binding.textViewAmount.setOnClickListener(view -> {
                        StringUtil.copyToClipboard(this, "", config.messageArgs[1].toString());
                    });
                } else {
                    binding.panelPasswordBalance.setVisibility(View.GONE);
                }
            }
        } else {
            binding.panelDetails.setVisibility(View.GONE);
        }
        // Image
        if (config.imageResId != 0) {
            binding.imageView.setImageResource(config.imageResId);
        } else {
            binding.imageView.setVisibility(View.GONE);
        }
        // Solid Button
        if (config.solidButtonText != null) {
            binding.buttonSolid.setVisibility(View.VISIBLE);
            binding.buttonSolid.setText(config.solidButtonText);
            binding.buttonSolid.setOnClickListener(view -> handleAction(config.solidButtonAction));
        } else {
            binding.buttonSolid.setVisibility(View.GONE);
        }
        // Bordered Button
        if (config.borderedButtonText != null) {
            binding.buttonBordered.setVisibility(View.VISIBLE);
            binding.buttonBordered.setText(config.borderedButtonText);
            binding.buttonBordered.setOnClickListener(view -> handleAction(config.borderedButtonAction));
        } else {
            binding.buttonBordered.setVisibility(View.GONE);
        }
        // Link Text 1
        if (config.link1Text != null) {
            binding.textViewLink1.setVisibility(View.VISIBLE);
            binding.textViewLink1.setText(config.link1Text);
            binding.textViewLink1.setOnClickListener(view -> handleAction(config.link1Action));
        } else {
            binding.textViewLink1.setVisibility(View.GONE);
        }
        // Link Text 2
        if (config.link2Text != null) {
            binding.textViewLink2.setVisibility(View.VISIBLE);
            binding.textViewLink2.setText(config.link2Text);
            binding.textViewLink2.setOnClickListener(view -> handleAction(config.link2Action));
        } else {
            binding.textViewLink2.setVisibility(View.GONE);
        }
    }


    private void handleAction(SuccessConfig.SuccessAction action) {
        if (action == null) return;
        switch (action) {
            case TOP_UP:
                Bundle bundle = new Bundle();
                bundle.putString("data", new Gson().toJson(ActionType.TOP_UP));
                bundle.putString("id", config.messageArgs[2].toString());
                startAppActivity(new Intent(this, ActionUserDetailsActivity.class), bundle,
                        true, false, true);
                break;
            case HOME:
                startAppActivity(new Intent(this, DashboardActivity.class), null, true, true, true);
                break;
            case PRINT:
                PrinterUtil printerUtil = PrinterUtil.getInstance();
                if (printerUtil.isConnected()) {
                    printerUtil.printTextFormatted(String.format(getString(R.string.print_template_store), shop.getShop_name()), 0, false, true);
                    printerUtil.printTextFormatted(String.format(getString(R.string.print_template_date), DateFormatUtils.getCurrentDateTime()), 0, false, true);
                    if (actionType == ActionType.CREATE_USER) {
                        printerUtil.printTextFormatted(String.format(getString(R.string.print_template_id), config.messageArgs[0].toString()), 0, false, true);
                        printerUtil.printTextFormatted(String.format(getString(R.string.print_template_password), config.messageArgs[1].toString()), 0, false, true);
                    } else if (actionType == ActionType.CREATE_USER_RANDOM) {
                        printerUtil.printTextFormatted(String.format(getString(R.string.print_template_id), config.messageArgs[0].toString()), 0, false, true);
                        printerUtil.printTextFormatted(String.format(getString(R.string.print_template_password), config.messageArgs[1].toString()), 0, false, true);
                    } else if (actionType == ActionType.TOP_UP) {
                        printerUtil.printTextFormatted(String.format(getString(R.string.print_template_id), config.messageArgs[0].toString()), 0, false, true);
                        printerUtil.printTextFormatted(String.format(getString(R.string.print_template_top_up), config.titleArgs), 0, false, true);
                        printerUtil.printTextFormatted(String.format(getString(R.string.print_template_balance), config.messageArgs[1].toString()), 0, false, true);
                    }
                    printerUtil.feedLines(4);
                } else {
                    startAppActivity(new Intent(SuccessActivity.this, PrinterActivity.class),
                            null, false, false, true);
                }
                break;
            case BACK:
                onBaseBackPressed();
                break;
            case AGAIN:
            case NONE:
            default:
                finish();
                break;
        }
    }

    private String format(String format, Object[] args) {
        if (format == null) return "";
        return args != null ? String.format(format, args) : format;
    }
}