package com.dj.shop.activity.transaction;

import android.app.Activity;
import android.content.Intent;
import android.os.Bundle;
import android.view.View;

import androidx.activity.result.ActivityResultLauncher;
import androidx.activity.result.contract.ActivityResultContracts;
import androidx.annotation.Nullable;

import com.dj.shop.R;
import com.dj.shop.activity.BaseActivity;
import com.dj.shop.activity.qr.QrScannerActivity;
import com.dj.shop.activity.success.SuccessActivity;
import com.dj.shop.activity.yxi.YxiListActivity;
import com.dj.shop.databinding.ActivityActionMainBinding;
import com.dj.shop.enums.ActionType;
import com.dj.shop.enums.ScanMode;
import com.dj.shop.model.SuccessConfig;
import com.dj.shop.model.SuccessConfigFactory;
import com.dj.shop.model.request.RequestMemberNew;
import com.dj.shop.model.request.RequestMemberSearch;
import com.dj.shop.model.request.RequestProfile;
import com.dj.shop.model.request.RequestProfileGeneral;
import com.dj.shop.model.response.BaseResponse;
import com.dj.shop.model.response.Country;
import com.dj.shop.model.response.Member;
import com.dj.shop.model.response.Shop;
import com.dj.shop.model.response.Transaction;
import com.dj.shop.util.ApiCallback;
import com.dj.shop.util.ApiClient;
import com.dj.shop.util.ApiService;
import com.dj.shop.util.CacheManager;
import com.dj.shop.util.FormatUtils;
import com.dj.shop.util.StringUtil;
import com.dj.shop.widget.AndroidBug5497WorkaroundBg;
import com.dj.shop.widget.CountryBottomSheetDialogFragment;
import com.dj.shop.widget.RoundedEditTextView;
import com.google.gson.Gson;

import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;

public class ActionMainActivity extends BaseActivity {
    ActivityActionMainBinding binding;
    ActionType actionType;
    private Shop shop;
    Country selectedCountry;
    List<Country> countryList;
    private final ActivityResultLauncher<Intent> qrScannerLauncher =
            registerForActivityResult(new ActivityResultContracts.StartActivityForResult(), result -> {
                if (result.getResultCode() == RESULT_OK && result.getData() != null) {
                    String qrResult = result.getData().getStringExtra("data");
                    if (qrResult != null) {
                        if (actionType == ActionType.WITHDRAWAL) {
                            String apiUrl = qrResult;
                            handleGetApi(apiUrl);
                        }
                    }
                }
            });

    @Override
    protected void onCreate(@Nullable Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityActionMainBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        AndroidBug5497WorkaroundBg.assistActivity(this);
        shop = CacheManager.getObject(this, CacheManager.KEY_SHOP_PROFILE, Shop.class);

        parseIntentData();
        setupToolbar(binding.toolbar.getRoot(), "",
                actionType == ActionType.CHANGE_PASSWORD ? R.drawable.ic_scan : 0,
                view -> openQrScanner(ActionMainActivity.this, ScanMode.CHANGE_PASSWORD));
        setupInputField();
        applyActionConfig();
    }

    @Override
    protected void onResume() {
        super.onResume();
        getShopProfile();
    }

    private void parseIntentData() {
        String json = getIntent().getStringExtra("data");
        if (json != null) {
            actionType = new Gson().fromJson(json, ActionType.class);
            if (actionType == ActionType.CREATE_USER || actionType == ActionType.BLOCK_USER) {
                Country[] cached = CacheManager.getObject(this, CacheManager.KEY_COUNTRY_LIST, Country[].class);
                if (cached != null) {
                    countryList = new ArrayList<>(Arrays.asList(cached));
                } else {
                    getCountryList();
                }
            }
        }
    }

    private void setupInputField() {
        binding.editTextSearch.setInputFieldType(RoundedEditTextView.InputFieldType.TEXT);
        binding.editTextSearch.setHint(getString(R.string.action_main_hint_search));

        binding.panelCountryCode.setOnClickListener(view -> {
            if (countryList == null) {
                return;
            }
            CountryBottomSheetDialogFragment.newInstance(getString(R.string.country_code), new ArrayList<>(countryList), (country, pos) -> {
                selectedCountry = country;
                binding.textViewCountryCode.setText(String.format("+%s", country.getPhone_code()));
            }).show(getSupportFragmentManager(), "CountryBottomSheet");
        });
        binding.textViewCountryCode.setText(R.string.country_code_mys);
        binding.editTextPhone.setInputFieldType(RoundedEditTextView.InputFieldType.NUMBER);
        binding.editTextPhone.setHint(getString(R.string.action_main_hint_phone));
    }

    private void applyActionConfig() {
        if (actionType == null) {
            return;
        }
        switch (actionType) {
            case TOP_UP:
                binding.panelSearch.setVisibility(View.VISIBLE);
                binding.panelPhone.setVisibility(View.GONE);
                configurePage(getString(R.string.action_main_top_up_title), getString(R.string.action_main_top_up_desc),
                        getString(R.string.action_main_button_search), () -> searchMember(false));
                configureOptions(getString(R.string.action_main_top_up_option_qr), () -> openQrScanner(this, ScanMode.TOPUP),
                        getString(R.string.action_main_top_up_option_yxi), () -> {
                            Bundle bundle = new Bundle();
                            bundle.putString("data", new Gson().toJson(actionType));
                            startAppActivity(new Intent(ActionMainActivity.this, YxiListActivity.class),
                                    bundle, false, false, true);
                        });
                break;

            case WITHDRAWAL:
                binding.panelSearch.setVisibility(View.VISIBLE);
                binding.panelPhone.setVisibility(View.GONE);
                configurePage(getString(R.string.action_main_withdraw_title), getString(R.string.action_main_withdraw_desc),
                        getString(R.string.action_main_button_search), () -> searchMember(false));
                configureOptions(getString(R.string.action_main_withdraw_option_qr), () -> openQrScanner(this, ScanMode.WITHDRAW),
                        getString(R.string.action_main_withdraw_option_yxi), () -> {
                            Bundle bundle = new Bundle();
                            bundle.putString("data", new Gson().toJson(actionType));
                            startAppActivity(new Intent(ActionMainActivity.this, YxiListActivity.class),
                                    bundle, false, false, true);
                        });
                break;

            case CREATE_USER:
                binding.panelSearch.setVisibility(View.GONE);
                binding.panelPhone.setVisibility(View.VISIBLE);
                configurePage(getString(R.string.action_main_create_user_title), getString(R.string.action_main_create_user_desc),
                        getString(R.string.action_main_button_create), this::createNewMember);
                configureOptions(getString(R.string.action_main_create_user_option_random), () -> {
                            Intent intent = new Intent(ActionMainActivity.this, ActionMainActivity.class);
                            Bundle bundle = new Bundle();
                            bundle.putString("data", new Gson().toJson(ActionType.CREATE_USER_RANDOM));
                            startAppActivity(intent, bundle, false, false, true);
                        },
                        getString(R.string.action_main_create_user_option_yxi), () -> {
                            Bundle bundle = new Bundle();
                            bundle.putString("data", new Gson().toJson(actionType));
                            startAppActivity(new Intent(ActionMainActivity.this, YxiListActivity.class),
                                    bundle, false, false, true);
                        });
                break;

            case CREATE_USER_RANDOM:
                binding.panelSearch.setVisibility(View.GONE);
                binding.panelPhone.setVisibility(View.GONE);
                configurePage(getString(R.string.action_main_create_random_title), getString(R.string.action_main_create_random_desc),
                        getString(R.string.action_main_button_confirm), this::randomGenerateMember);
                configureOptions(getString(R.string.action_main_button_cancel), this::onBaseBackPressed, null, null);
                break;

            case CHANGE_PASSWORD:
                binding.panelSearch.setVisibility(View.VISIBLE);
                binding.panelPhone.setVisibility(View.GONE);
                configurePage(getString(R.string.action_main_change_password_title), getString(R.string.action_main_change_password_desc),
                        getString(R.string.action_main_button_search), () -> searchMember(false));
                hideOptions();
                break;

            case BLOCK_USER:
                binding.panelSearch.setVisibility(View.GONE);
                binding.panelPhone.setVisibility(View.VISIBLE);
                configurePage(getString(R.string.action_main_block_title), getString(R.string.action_main_block_desc),
                        getString(R.string.action_main_button_search), () -> searchMember(true));
                hideOptions();
                break;
        }
    }

    private void configurePage(String title, String desc, String buttonText, Runnable buttonAction) {
        binding.textViewPageTitle.setText(title);
        binding.textViewPageDesc.setText(desc);
        binding.buttonSearch.setText(buttonText);
        binding.buttonSearch.setOnClickListener(v -> buttonAction.run());
    }

    private void configureOptions(String option1Text, Runnable option1Action, String option2Text, Runnable option2Action) {
        binding.imageViewDivider.setVisibility(View.VISIBLE);

        if (option1Text != null) {
            binding.textViewOption1.setVisibility(View.VISIBLE);
            binding.textViewOption1.setText(option1Text);
            binding.textViewOption1.setOnClickListener(v -> option1Action.run());
        } else {
            binding.textViewOption1.setVisibility(View.GONE);
        }

        if (option2Text != null) {
            binding.textViewOption2.setVisibility(View.VISIBLE);
            binding.textViewOption2.setText(option2Text);
            binding.textViewOption2.setOnClickListener(v -> option2Action.run());
        } else {
            binding.imageViewDivider.setVisibility(View.GONE);
            binding.textViewOption2.setVisibility(View.GONE);
        }
    }

    private void hideOptions() {
        binding.imageViewDivider.setVisibility(View.GONE);
        binding.textViewOption1.setVisibility(View.GONE);
        binding.textViewOption2.setVisibility(View.GONE);
    }

    private void openQrScanner(Activity context, ScanMode mode) {
        Intent intent = new Intent(this, QrScannerActivity.class);
        intent.putExtra(QrScannerActivity.SCAN_MODE, mode);
        qrScannerLauncher.launch(intent);
    }

    private void getShopProfile() {
        if (shop == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestProfile request = new RequestProfile(String.valueOf(shop.getShop_id()));
        executeApiCall(this, apiService.getShopProfile(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Shop> response) {
                Shop updatedShopData = response.getData();
                if (updatedShopData != null) {
                    CacheManager.saveObject(ActionMainActivity.this, CacheManager.KEY_SHOP_PROFILE, updatedShopData);
                    shop = updatedShopData;
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
        }, false);
    }

    private void searchMember(boolean isPhone) {
        String search = "";
        if (isPhone) {
            String countryCode = binding.textViewCountryCode.getText().toString();
            String phone = binding.editTextPhone.getText();
            search = String.format(getString(R.string.template_s_s), countryCode.replaceAll("^\\+", ""), phone);
            if (phone.isEmpty()) {
                binding.editTextPhone.showError("");
                return;
            }
        } else {
            search = binding.editTextSearch.getText();
            if (search.isEmpty()) {
                binding.editTextSearch.showError("");
                return;
            }
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestMemberSearch request = new RequestMemberSearch(shop.getShop_id(), search);
        executeApiCall(this, apiService.searchMember(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Member> response) {
                Member member = response.getData();
                Bundle bundle = new Bundle();
                bundle.putString("data", new Gson().toJson(actionType));
                bundle.putString("id", member.getMember_id());
                startAppActivity(new Intent(ActionMainActivity.this, ActionUserDetailsActivity.class),
                        bundle, false, false, true);
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

    private void getCountryList() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestProfileGeneral request = new RequestProfileGeneral(shop.getShop_id());
        executeApiCall(this, apiService.getCountryList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<Country>> response) {
                countryList = response.getData();
                for (Country country : countryList) {
                    country.setSelected(country.getCountry_code().equalsIgnoreCase(selectedCountry != null ? selectedCountry.getCountry_code() : "mys"));
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

    private void createNewMember() {
        binding.editTextPhone.clearError();
        String countryCode = binding.textViewCountryCode.getText().toString();
        String phone = binding.editTextPhone.getText();
        String newMember = String.format(getString(R.string.template_s_s), countryCode.replaceAll("^\\+", ""), phone);
        if (phone.isEmpty()) {
            binding.editTextPhone.showError("");
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestMemberNew request = new RequestMemberNew(shop.getShop_id(), newMember);
        executeApiCall(this, apiService.createNewMember(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Member> response) {
                binding.editTextPhone.setText("");
                Member member = response.getData();
                SuccessConfig data = SuccessConfigFactory.createNewUserSuccess(ActionMainActivity.this, FormatUtils.formatMsianPhone(member.getMember_login()), response.getPassword(), member.getMember_id());
                Bundle bundle = new Bundle();
                bundle.putString("data", new Gson().toJson(data));
                startAppActivity(new Intent(ActionMainActivity.this, SuccessActivity.class), bundle, false, false, true);
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

    private void randomGenerateMember() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestProfile request = new RequestProfile(shop.getShop_id());
        executeApiCall(this, apiService.randomGenerateMember(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Member> response) {
                Member member = response.getData();
                String password = response.getPassword();
                if (member != null && !StringUtil.isNullOrEmpty(password)) {
                    SuccessConfig data = SuccessConfigFactory.createNewUserRandomSuccess(ActionMainActivity.this, FormatUtils.formatMsianPhone(member.getMember_login()), password, member.getMember_id());
                    Bundle bundle = new Bundle();
                    bundle.putString("data", new Gson().toJson(data));
                    startAppActivity(new Intent(ActionMainActivity.this, SuccessActivity.class), bundle, true, false, true);
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

    private void handleGetApi(String apiUrl) {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        executeApiCall(this, apiService.getFromUrl(apiUrl), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                Transaction transaction = response.getCredit();
                Bundle bundle = new Bundle();
                bundle.putString("data", new Gson().toJson(actionType));
                bundle.putString("id", transaction.getMember_id());
                bundle.putString("transaction", new Gson().toJson(transaction));
                startAppActivity(new Intent(ActionMainActivity.this, ActionUserDetailsActivity.class),
                        bundle, false, false, true);
            }

            @Override
            public boolean onApiError(int code, String message) {
                return true;
            }

            @Override
            public boolean onFailure(Throwable t) {
                return true;
            }
        }, true);
    }

}