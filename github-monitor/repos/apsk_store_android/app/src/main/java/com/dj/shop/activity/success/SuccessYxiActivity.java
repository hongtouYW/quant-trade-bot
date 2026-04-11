package com.dj.shop.activity.success;

import android.content.Intent;
import android.os.Bundle;
import android.view.View;

import androidx.annotation.Nullable;

import com.dj.shop.R;
import com.dj.shop.activity.BaseActivity;
import com.dj.shop.activity.PrinterActivity;
import com.dj.shop.activity.dashboard.DashboardActivity;
import com.dj.shop.activity.yxi.SearchDetailsActivity;
import com.dj.shop.databinding.ActivitySuccessYxiBinding;
import com.dj.shop.enums.ActionType;
import com.dj.shop.model.SuccessConfig;
import com.dj.shop.model.request.RequestPlayerSearch;
import com.dj.shop.model.response.BaseResponse;
import com.dj.shop.model.response.Player;
import com.dj.shop.model.response.Shop;
import com.dj.shop.util.ApiCallback;
import com.dj.shop.util.ApiClient;
import com.dj.shop.util.ApiService;
import com.dj.shop.util.CacheManager;
import com.dj.shop.util.DateFormatUtils;
import com.dj.shop.util.PrinterUtil;
import com.dj.shop.util.StringUtil;
import com.google.gson.Gson;
import com.squareup.picasso.Callback;
import com.squareup.picasso.Picasso;

public class SuccessYxiActivity extends BaseActivity {
    private ActivitySuccessYxiBinding binding;
    private Shop shop;
    private SuccessConfig config;
    private ActionType actionType;

    @Override
    protected void onCreate(@Nullable Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivitySuccessYxiBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        shop = CacheManager.getObject(this, CacheManager.KEY_SHOP_PROFILE, Shop.class);

        parseIntentData();
        if (config == null) {
            finish();
            return;
        }
        actionType = config.actionType;
        setupUI();
    }

    private void parseIntentData() {
        String json = getIntent().getStringExtra("data");
        if (json != null) {
            config = new Gson().fromJson(json, SuccessConfig.class);
        }
    }

    private void setupUI() {
        // Icon
        String icon = config.icon;
        if (!StringUtil.isNullOrEmpty(icon)) {
            if (!icon.startsWith("http")) {
                icon = String.format(getString(R.string.template_s_s), getString(R.string.image_base_url), icon);
            }
            Picasso.get().load(icon).centerCrop().fit().into(binding.imageViewYxi, new Callback() {
                @Override
                public void onSuccess() {

                }

                @Override
                public void onError(Exception e) {
                    binding.imageViewYxi.setImageResource(R.drawable.img_provider_default);
                }
            });
        } else {
            binding.imageViewYxi.setImageResource(R.drawable.img_provider_default);
        }
        // Title
        binding.textViewTitle.setText(format(config.titleFormat, config.titleArgs));
        // Message
        if (config.messageArgs != null) {
            Object[] args = config.messageArgs;
            switch (config.actionType) {
                case TOP_UP:
                case WITHDRAWAL:
                    binding.panelDetails.setVisibility(View.VISIBLE);
                    binding.panelYxi.setVisibility(View.VISIBLE);
                    binding.textViewYxi.setText(safeArg(args, 0));
                    binding.panelPhone.setVisibility(View.GONE);
                    binding.panelPassword.setVisibility(View.GONE);
                    binding.panelId.setVisibility(View.VISIBLE);
                    String id = safeArg(args, 1);
                    binding.textViewId.setText(id);
                    binding.textViewId.setOnClickListener(view -> {
                        StringUtil.copyToClipboard(this, "", id);
                    });
                    binding.panelYxiPassword.setVisibility(View.GONE);
                    binding.panelBalance.setVisibility(View.VISIBLE);
                    binding.textViewBalance.setText(safeArg(args, 2));
                    binding.panelYxiBalance.setVisibility(View.VISIBLE);
                    binding.textViewYxiBalance.setText(safeArg(args, 3));
                    break;

                case CREATE_USER:
                    binding.panelDetails.setVisibility(View.VISIBLE);
                    binding.panelYxi.setVisibility(View.GONE);
                    binding.panelPhone.setVisibility(View.VISIBLE);
                    String phone = safeArg(args, 0);
                    binding.textViewPhone.setText(phone);
                    binding.textViewPhone.setOnClickListener(view -> {
                        StringUtil.copyToClipboard(this, "", phone
                                .replaceAll("\\+", "")
                                .replaceAll("\\s+", ""));
                    });
                    binding.panelId.setVisibility(View.VISIBLE);
                    String playerId = safeArg(args, 5);
                    binding.textViewId.setText(playerId);
                    binding.textViewId.setOnClickListener(view -> {
                        StringUtil.copyToClipboard(this, "", playerId);
                    });
                    if (shop.canViewCredentials()) {
                        binding.panelPassword.setVisibility(View.VISIBLE);
                        String password = safeArg(args, 1);
                        binding.textViewPassword.setText(password);
                        binding.textViewPassword.setOnClickListener(view -> {
                            StringUtil.copyToClipboard(this, "", password);
                        });
                        binding.panelYxiPassword.setVisibility(View.VISIBLE);
                        String yxiPassword = safeArg(args, 6);
                        binding.textViewYxiPassword.setText(yxiPassword);
                        binding.textViewYxiPassword.setOnClickListener(view -> {
                            StringUtil.copyToClipboard(this, "", yxiPassword);
                        });
                    } else {
                        binding.panelPassword.setVisibility(View.GONE);
                        binding.panelYxiPassword.setVisibility(View.GONE);
                    }
                    binding.panelBalance.setVisibility(View.GONE);
                    binding.panelYxiBalance.setVisibility(View.GONE);
                    break;
            }
        } else {
            binding.panelDetails.setVisibility(View.GONE);
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

    private String safeArg(Object[] args, int index) {
        if (args != null && args.length > index && args[index] != null) {
            String value = args[index].toString().trim();
            if (!value.isEmpty()) {
                return value;
            }
        }
        return "-";
    }

    private void handleAction(SuccessConfig.SuccessAction action) {
        if (action == null) return;
        switch (action) {
            case TOP_UP:
                searchPlayer(config.messageArgs[2].toString(), config.messageArgs[3].toString());
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
                        printerUtil.printTextFormatted("---------------------", 0, false, true);
                        printerUtil.printTextFormatted(String.format(getString(R.string.print_template_yxi), config.messageArgs[4].toString()), 0, false, true);
                        printerUtil.printTextFormatted(String.format(getString(R.string.print_template_id), config.messageArgs[5].toString()), 0, false, true);
                        printerUtil.printTextFormatted(String.format(getString(R.string.print_template_password), config.messageArgs[6].toString()), 0, false, true);
                    } else if (actionType == ActionType.TOP_UP) {
                        printerUtil.printTextFormatted(String.format(getString(R.string.print_template_yxi), config.messageArgs[0].toString()), 0, false, true);
                        printerUtil.printTextFormatted(String.format(getString(R.string.print_template_id), config.messageArgs[1].toString()), 0, false, true);
                        printerUtil.printTextFormatted(String.format(getString(R.string.print_template_top_up), config.titleArgs), 0, false, true);
                        printerUtil.printTextFormatted(String.format(getString(R.string.print_template_yxi_balance), config.messageArgs[3].toString()), 0, false, true);
                        printerUtil.printTextFormatted(String.format(getString(R.string.print_template_balance), config.messageArgs[2].toString()), 0, false, true);
                    }
                    printerUtil.feedLines(4);
                } else {
                    startAppActivity(new Intent(SuccessYxiActivity.this, PrinterActivity.class),
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

    private void searchPlayer(String providerId, String playerId) {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestPlayerSearch request = new RequestPlayerSearch(shop.getShop_id(), providerId, playerId);
        executeApiCall(this, apiService.playerSearch(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Player> response) {
                Player player = response.getData();
                if (player != null) {
                    Bundle bundle = new Bundle();
                    bundle.putString("data", new Gson().toJson(ActionType.TOP_UP));
                    bundle.putString("player", new Gson().toJson(player));
                    startAppActivity(new Intent(SuccessYxiActivity.this, SearchDetailsActivity.class),
                            bundle, false, false, true);
                }
            }

            @Override
            public boolean onApiError(int code, String message) {
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                return false;
            }
        }, true);
    }
}