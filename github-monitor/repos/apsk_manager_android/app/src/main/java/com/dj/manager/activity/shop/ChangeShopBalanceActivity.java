package com.dj.manager.activity.shop;

import android.graphics.Color;
import android.os.Bundle;
import android.text.Editable;
import android.text.TextWatcher;
import android.util.Log;
import android.widget.EditText;
import android.widget.TextView;

import androidx.core.content.ContextCompat;

import com.dj.manager.R;
import com.dj.manager.activity.BaseActivity;
import com.dj.manager.databinding.ActivityChangeShopBalanceBinding;
import com.dj.manager.model.request.RequestUpdateShopAmount;
import com.dj.manager.model.response.BaseResponse;
import com.dj.manager.model.response.Manager;
import com.dj.manager.model.response.Shop;
import com.dj.manager.util.ApiCallback;
import com.dj.manager.util.ApiClient;
import com.dj.manager.util.ApiService;
import com.dj.manager.util.CacheManager;
import com.dj.manager.util.DateFormatUtils;
import com.dj.manager.util.FormatUtils;
import com.dj.manager.widget.CustomToast;
import com.google.gson.Gson;

import java.text.DecimalFormat;

public class ChangeShopBalanceActivity extends BaseActivity {
    private ActivityChangeShopBalanceBinding binding;
    private boolean isConfigEnabled = false;
    private Manager manager;
    private Shop shop;
    private double selectedAmount = 0.0;
    private double minAmount = 0.0;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityChangeShopBalanceBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        manager = CacheManager.getObject(this, CacheManager.KEY_MANAGER_PROFILE, Manager.class);

        parseIntentData();
        setupToolbar(binding.toolbar.getRoot(), getString(R.string.shop_balance_title), 0, null);
        setupUI();
    }

    private void parseIntentData() {
        String json = getIntent().getStringExtra("data");
        if (json != null) {
            shop = new Gson().fromJson(json, Shop.class);
        }
    }

    private void setupUI() {
        binding.textViewBalance.setText(String.format(getString(R.string.template_currency_amount_space), FormatUtils.formatAmount(shop.getBalance())));
        binding.textViewOpenBalance.setText(String.format(getString(R.string.template_currency_amount_space), FormatUtils.formatAmount(shop.getPrincipal())));
        binding.textViewUpdated.setText(DateFormatUtils.timeAgo(this, shop.getLowestbalance_on(), true));
        binding.textViewMinBalance.setText(String.format(getString(R.string.template_currency_amount_space), FormatUtils.formatAmount(shop.getLowestbalance())));
        binding.textViewMinUpdated.setText(DateFormatUtils.timeAgo(this, shop.getLowestbalance_on(), true));
        binding.textViewBalanceSet.setText(String.format(getString(R.string.shop_balance_amount_settings_chosen), FormatUtils.formatAmount(shop.getPrincipal())));
        binding.textViewMinBalanceSet.setText(String.format(getString(R.string.shop_balance_amount_settings_chosen), FormatUtils.formatAmount(shop.getLowestbalance())));

        binding.panelConfig.setAlpha((float) (isConfigEnabled ? 1.0 : 0.4));
        binding.textViewConfig.setOnClickListener(view -> {
            isConfigEnabled = !isConfigEnabled;
            setupUI();
        });
        binding.textViewMinConfig.setOnClickListener(view -> {
            isConfigEnabled = !isConfigEnabled;
            setupUI();
        });
        addCurrencyWatcher(binding.editTextSelectedAmount);
        binding.textViewOption100.setEnabled(isConfigEnabled);
        binding.textViewOption100.setOnClickListener(view -> {
            selectedAmount = 100;
            resetOptionStyles();
            applySelectedStyle(binding.textViewOption100);
            binding.editTextSelectedAmount.setText(FormatUtils.formatAmount(100));
        });
        binding.textViewOption200.setEnabled(isConfigEnabled);
        binding.textViewOption200.setOnClickListener(view -> {
            selectedAmount = 200;
            resetOptionStyles();
            applySelectedStyle(binding.textViewOption200);
            binding.editTextSelectedAmount.setText(FormatUtils.formatAmount(200));
        });
        binding.textViewOption2000.setEnabled(isConfigEnabled);
        binding.textViewOption2000.setOnClickListener(view -> {
            selectedAmount = 2000;
            resetOptionStyles();
            applySelectedStyle(binding.textViewOption2000);
            binding.editTextSelectedAmount.setText(FormatUtils.formatAmount(2000));
        });
        binding.textViewOption5000.setEnabled(isConfigEnabled);
        binding.textViewOption5000.setOnClickListener(view -> {
            selectedAmount = 5000;
            resetOptionStyles();
            applySelectedStyle(binding.textViewOption5000);
            binding.editTextSelectedAmount.setText(FormatUtils.formatAmount(5000));
        });

        addCurrencyWatcher(binding.editTextMinAmount);
        binding.textViewMinOption100.setEnabled(isConfigEnabled);
        binding.textViewMinOption100.setOnClickListener(view -> {
            minAmount = 100;
            resetMinOptionStyles();
            applyMinSelectedStyle(binding.textViewMinOption100);
            binding.editTextMinAmount.setText(FormatUtils.formatAmount(100));
        });
        binding.textViewMinOption200.setEnabled(isConfigEnabled);
        binding.textViewMinOption200.setOnClickListener(view -> {
            minAmount = 200;
            resetMinOptionStyles();
            applyMinSelectedStyle(binding.textViewMinOption200);
            binding.editTextMinAmount.setText(FormatUtils.formatAmount(200));
        });
        binding.textViewMinOption2000.setEnabled(isConfigEnabled);
        binding.textViewMinOption2000.setOnClickListener(view -> {
            minAmount = 2000;
            resetMinOptionStyles();
            applyMinSelectedStyle(binding.textViewMinOption2000);
            binding.editTextMinAmount.setText(FormatUtils.formatAmount(2000));
        });
        binding.textViewMinOption5000.setEnabled(isConfigEnabled);
        binding.textViewMinOption5000.setOnClickListener(view -> {
            minAmount = 5000;
            resetMinOptionStyles();
            applyMinSelectedStyle(binding.textViewMinOption5000);
            binding.editTextMinAmount.setText(FormatUtils.formatAmount(5000));
        });
        resetOptionStyles();
        resetMinOptionStyles();

        binding.buttonSubmit.setEnabled(isConfigEnabled);
        binding.buttonSubmit.setOnClickListener(view -> {
            updateAmount();
        });
    }

    private void addCurrencyWatcher(EditText editText) {
        editText.addTextChangedListener(new TextWatcher() {
            private boolean editing = false;

            @Override
            public void beforeTextChanged(CharSequence s, int start, int count, int after) {
            }

            @Override
            public void onTextChanged(CharSequence s, int start, int before, int count) {
            }

            @Override
            public void afterTextChanged(Editable s) {
                if (editing) return;
                editing = true;
                try {
                    String raw = s.toString();
                    if (raw.isEmpty()) {
                        editText.setText("");
                        editing = false;
                        return;
                    }
                    String digits = raw.replaceAll("[^\\d]", "");
                    if (digits.isEmpty()) {
                        editText.setText("");
                        editing = false;
                        return;
                    }
                    double parsed = Double.parseDouble(digits.replaceAll(",", "")) / 100.0;
                    DecimalFormat formatter = new DecimalFormat("###,##0.00");
                    String formatted = formatter.format(parsed);
                    editText.setText(formatted);
                    editText.setSelection(formatted.length());

                    boolean isSelectedField = editText == binding.editTextSelectedAmount;
                    if (isSelectedField) {
                        selectedAmount = parsed;
                        handleSelectedAmount(parsed);
                    } else {
                        minAmount = parsed;
                        handleMinAmount(parsed);
                    }
                } catch (Exception e) {
                    Log.e("###", "afterTextChanged: ", e);
                }
                editing = false;
            }
        });
    }

    private void handleSelectedAmount(double amount) {
        resetOptionStyles();
        if (amount == 100) {
            applySelectedStyle(binding.textViewOption100);
        } else if (amount == 200) {
            applySelectedStyle(binding.textViewOption200);
        } else if (amount == 2000) {
            applySelectedStyle(binding.textViewOption2000);
        } else if (amount == 5000) {
            applySelectedStyle(binding.textViewOption5000);
        }
        if (amount > 0) {
            binding.editTextSelectedAmount.setTextColor(ContextCompat.getColor(this, R.color.gold_D4AF37));
        } else {
            binding.editTextSelectedAmount.setTextColor(ContextCompat.getColor(this, R.color.gray_7B7B7B));
        }
    }

    private void handleMinAmount(double amount) {
        resetMinOptionStyles();
        if (amount == 100) {
            applyMinSelectedStyle(binding.textViewMinOption100);
        } else if (amount == 200) {
            applyMinSelectedStyle(binding.textViewMinOption200);
        } else if (amount == 2000) {
            applyMinSelectedStyle(binding.textViewMinOption2000);
        } else if (amount == 5000) {
            applyMinSelectedStyle(binding.textViewMinOption5000);
        }
        if (amount > 0) {
            binding.editTextMinAmount.setTextColor(ContextCompat.getColor(this, R.color.gold_D4AF37));
        } else {
            binding.editTextMinAmount.setTextColor(ContextCompat.getColor(this, R.color.gray_7B7B7B));
        }
    }

    private void resetOptionStyles() {
//        binding.editTextSelectedAmount.setText(FormatUtils.formatAmount(0));
        binding.editTextSelectedAmount.setTextColor(ContextCompat.getColor(this, R.color.gray_7B7B7B));

        binding.textViewOption100.setTextColor(ContextCompat.getColor(this, R.color.white_FFFFFF));
        binding.textViewOption100.setBackgroundColor(Color.TRANSPARENT);

        binding.textViewOption200.setTextColor(ContextCompat.getColor(this, R.color.white_FFFFFF));
        binding.textViewOption200.setBackgroundColor(Color.TRANSPARENT);

        binding.textViewOption2000.setTextColor(ContextCompat.getColor(this, R.color.white_FFFFFF));
        binding.textViewOption2000.setBackgroundColor(Color.TRANSPARENT);

        binding.textViewOption5000.setTextColor(ContextCompat.getColor(this, R.color.white_FFFFFF));
        binding.textViewOption5000.setBackgroundColor(Color.TRANSPARENT);
    }

    private void resetMinOptionStyles() {
//        binding.editTextMinAmount.setText(FormatUtils.formatAmount(0));
        binding.editTextMinAmount.setTextColor(ContextCompat.getColor(this, R.color.gray_7B7B7B));

        binding.textViewMinOption100.setTextColor(ContextCompat.getColor(this, R.color.white_FFFFFF));
        binding.textViewMinOption100.setBackgroundColor(Color.TRANSPARENT);

        binding.textViewMinOption200.setTextColor(ContextCompat.getColor(this, R.color.white_FFFFFF));
        binding.textViewMinOption200.setBackgroundColor(Color.TRANSPARENT);

        binding.textViewMinOption2000.setTextColor(ContextCompat.getColor(this, R.color.white_FFFFFF));
        binding.textViewMinOption2000.setBackgroundColor(Color.TRANSPARENT);

        binding.textViewMinOption5000.setTextColor(ContextCompat.getColor(this, R.color.white_FFFFFF));
        binding.textViewMinOption5000.setBackgroundColor(Color.TRANSPARENT);
    }

    private void applySelectedStyle(TextView view) {
        binding.editTextSelectedAmount.setTextColor(ContextCompat.getColor(this, R.color.gold_D4AF37));
        view.setTextColor(ContextCompat.getColor(this, R.color.gold_D4AF37));
        view.setBackgroundResource(R.drawable.bg_button_bordered_gold);
    }

    private void applyMinSelectedStyle(TextView view) {
        binding.editTextMinAmount.setTextColor(ContextCompat.getColor(this, R.color.gold_D4AF37));
        view.setTextColor(ContextCompat.getColor(this, R.color.gold_D4AF37));
        view.setBackgroundResource(R.drawable.bg_button_bordered_gold);
    }

    private void updateAmount() {
        if (selectedAmount == 0) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestUpdateShopAmount request = new RequestUpdateShopAmount(manager.getManager_id(), shop.getShop_id(), selectedAmount, minAmount);
        executeApiCall(this, apiService.updateShopAmount(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Shop> response) {
                shop = response.getData();
                isConfigEnabled = false;
                setupUI();
                CustomToast.showTopToast(ChangeShopBalanceActivity.this, getString(R.string.shop_balance_update_success));
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