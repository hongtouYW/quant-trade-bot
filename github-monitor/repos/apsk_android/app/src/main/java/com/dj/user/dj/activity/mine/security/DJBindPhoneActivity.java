package com.dj.user.dj.activity.mine.security;

import android.content.Intent;
import android.os.Bundle;
import android.view.View;
import android.widget.Toast;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.databinding.DjActivityBindPhoneBinding;
import com.dj.user.enums.ActionType;
import com.dj.user.model.request.RequestBindPhone;
import com.dj.user.model.request.RequestProfileGeneral;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Country;
import com.dj.user.model.response.Member;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.dj.user.util.FormatUtils;
import com.dj.user.util.StringUtil;
import com.dj.user.widget.AndroidBug5497Workaround;
import com.dj.user.widget.CountryBottomSheetDialogFragment;
import com.google.gson.Gson;

import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;

public class DJBindPhoneActivity extends BaseActivity {

    private DjActivityBindPhoneBinding binding;
    private Member member;
    private List<Country> countryList;
    private Country selectedCountry;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = DjActivityBindPhoneBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        AndroidBug5497Workaround.assistActivity(this);
        member = CacheManager.getObject(this, CacheManager.KEY_USER_PROFILE, Member.class);

        setupToolbar(binding.toolbar.getRoot(), getString(R.string.bind_phone_title), 0, null);
        setupUI();
    }

    @Override
    protected void onResume() {
        super.onResume();
        Country[] cached = CacheManager.getObject(this, CacheManager.KEY_COUNTRY_LIST, Country[].class);
        if (cached != null) {
            countryList = new ArrayList<>(Arrays.asList(cached));
        } else {
            getCountryList();
        }
    }

    private void setupUI() {
        if (member == null) {
            onBaseBackPressed();
        }
        if (member.isPhoneBinded()) {
            binding.panelBind.setVisibility(View.GONE);
            binding.panelBinded.setVisibility(View.VISIBLE);
            binding.textViewPhone.setText(FormatUtils.maskPhoneNumber(FormatUtils.formatMsianPhone(member.getPhone())));
        } else {
            binding.panelBind.setVisibility(View.VISIBLE);
            binding.panelBinded.setVisibility(View.GONE);
            binding.panelCountryCode.setOnClickListener(view -> {
                if (countryList == null) {
                    return;
                }
                CountryBottomSheetDialogFragment.newInstance(new ArrayList<>(countryList), (country, pos) -> {
                    selectedCountry = country;
                    binding.textViewCountryCode.setText(String.format(getString(R.string.template_plus_s), country.getPhone_code()));
                }).show(getSupportFragmentManager(), "CountryBottomSheet");
            });
            binding.textViewCountryCode.setText(R.string.country_code_mys);
            binding.editTextPhone.setBackgroundTransparent(true);
            binding.buttonVerify.setOnClickListener(view -> bindEmail());
        }
    }

    private void getCountryList() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestProfileGeneral request = new RequestProfileGeneral(member.getMember_id());
        executeApiCall(this, apiService.getCountryList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<Country>> response) {
                countryList = response.getData();
                for (Country country : countryList) {
                    country.setSelected(country.getCountry_code().equalsIgnoreCase(selectedCountry != null ? selectedCountry.getCountry_code() : "mys"));
                }
                CacheManager.saveObject(DJBindPhoneActivity.this, CacheManager.KEY_COUNTRY_LIST, countryList);
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

    private void bindEmail() {
        if (member == null) {
            return;
        }
        String countryCode = binding.textViewCountryCode.getText().toString();
        String phone = binding.editTextPhone.getText();
        String number = String.format(getString(R.string.template_s_s), countryCode.replaceAll("^\\+", ""), phone);
        if (phone.isEmpty()) {
            binding.editTextPhone.showError("");
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestBindPhone request = new RequestBindPhone(member.getMember_id(), number);
        executeApiCall(this, apiService.requestBindPhone(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                String otp = response.getOtpcode();
                if (!StringUtil.isNullOrEmpty(otp)) {
                    Toast.makeText(DJBindPhoneActivity.this, "Testing: " + otp, Toast.LENGTH_LONG).show();
                }
                Bundle bundle = new Bundle();
                bundle.putString("data", new Gson().toJson(ActionType.BIND_PHONE));
                bundle.putString("request", new Gson().toJson(request));
                startAppActivity(new Intent(DJBindPhoneActivity.this, DJBindOTPActivity.class),
                        bundle, false, false, true
                );
            }

            @Override
            public boolean onApiError(int code, String message) {
                return true;
            }

            @Override
            public boolean onFailure(Throwable t) {
                return false;
            }
        }, true);
    }
}