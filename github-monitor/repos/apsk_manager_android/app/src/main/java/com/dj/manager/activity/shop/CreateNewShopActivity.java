package com.dj.manager.activity.shop;

import android.content.Intent;
import android.graphics.Rect;
import android.os.Bundle;
import android.text.Editable;
import android.text.TextWatcher;
import android.view.View;
import android.widget.EditText;
import android.widget.Toast;

import androidx.appcompat.widget.SwitchCompat;
import androidx.core.content.ContextCompat;

import com.dj.manager.R;
import com.dj.manager.activity.BaseActivity;
import com.dj.manager.adapter.AreaListViewAdapter;
import com.dj.manager.adapter.CountryListViewAdapter;
import com.dj.manager.adapter.StateListViewAdapter;
import com.dj.manager.databinding.ActivityCreateNewShopBinding;
import com.dj.manager.databinding.ViewEditTextOptionBinding;
import com.dj.manager.databinding.ViewEditTextPasswordBinding;
import com.dj.manager.model.request.RequestData;
import com.dj.manager.model.request.RequestDataArea;
import com.dj.manager.model.request.RequestDataState;
import com.dj.manager.model.request.RequestShopNew;
import com.dj.manager.model.response.Area;
import com.dj.manager.model.response.BaseResponse;
import com.dj.manager.model.response.Country;
import com.dj.manager.model.response.Manager;
import com.dj.manager.model.response.Shop;
import com.dj.manager.model.response.State;
import com.dj.manager.util.ApiCallback;
import com.dj.manager.util.ApiClient;
import com.dj.manager.util.ApiService;
import com.dj.manager.util.CacheManager;
import com.dj.manager.widget.RoundedEditTextView;
import com.dj.manager.widget.SelectionBottomSheetDialogFragment;

import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;

public class CreateNewShopActivity extends BaseActivity {
    private ActivityCreateNewShopBinding binding;
    private ViewEditTextPasswordBinding passwordBinding;
    private ViewEditTextOptionBinding countryBinding;
    private ViewEditTextOptionBinding stateBinding;
    private ViewEditTextOptionBinding areaBinding;
    private Manager manager;
    private ArrayList<Country> countryList;
    private ArrayList<State> stateList;
    private ArrayList<Area> areaList;
    private Country selectedCountry;
    private State selectedState;
    private Area selectedArea;
    private EditText passwordField;
    private int topUpEnabled = 0;
    private int withdrawEnabled = 0;
    private int createEnabled = 0;
    private int blockEnabled = 0;
    private int incomeEnabled = 0;
    private int settlementEnabled = 0;
    private int viewCredentialsEnabled = 0;
    private int withdrawalFeeEnabled = 0;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityCreateNewShopBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        manager = CacheManager.getObject(this, CacheManager.KEY_MANAGER_PROFILE, Manager.class);
        setupToolbar(binding.toolbar.getRoot(), getString(R.string.shop_new_title), 0, null);
        setupUI();
        setupOptionField();
        Country[] cached = CacheManager.getObject(this, CacheManager.KEY_COUNTRY_LIST, Country[].class);
        if (cached != null) {
            countryList = new ArrayList<>(Arrays.asList(cached));
            processCountryList();
        } else {
            getCountryList();
        }
    }

    private void setupUI() {
        binding.textViewCountry.setText(manager.getCountryName());
        binding.textViewState.setText(manager.getStateName());
        binding.textViewArea.setText(manager.getAreaName());

        ViewEditTextOptionBinding countryBinding = ViewEditTextOptionBinding.bind(binding.viewEditTextCountry.getRoot());
        countryBinding.textViewHintValue.setText(R.string.shop_new_choose_country);
        ViewEditTextOptionBinding stateBinding = ViewEditTextOptionBinding.bind(binding.viewEditTextState.getRoot());
        stateBinding.textViewHintValue.setText(R.string.shop_new_choose_state);
        ViewEditTextOptionBinding areaBinding = ViewEditTextOptionBinding.bind(binding.viewEditTextArea.getRoot());
        areaBinding.textViewHintValue.setText(R.string.shop_new_choose_area);

        binding.editTextName.setInputFieldType(RoundedEditTextView.InputFieldType.TEXT);
        binding.editTextName.setHint(getString(R.string.shop_new_hint_name));
        enableAutoScrollWhileTyping(binding.editTextName.getEditText());
        binding.editTextUsername.setInputFieldType(RoundedEditTextView.InputFieldType.TEXT);
        binding.editTextUsername.setHint(getString(R.string.shop_new_hint_username));
        enableAutoScrollWhileTyping(binding.editTextUsername.getEditText());

        passwordBinding = ViewEditTextPasswordBinding.bind(binding.viewEditTextPassword.getRoot());
        passwordField = passwordBinding.editTextPassword;
        passwordField.setHint(R.string.shop_new_hint_password);
        enableAutoScrollWhileTyping(passwordField);
        passwordField.addTextChangedListener(new TextWatcher() {
            @Override
            public void afterTextChanged(Editable s) {

            }

            @Override
            public void beforeTextChanged(CharSequence s, int start, int count, int after) {

            }

            @Override
            public void onTextChanged(CharSequence s, int start, int before, int count) {
                clearError(CreateNewShopActivity.this, passwordBinding.panelPassword);
            }
        });
        passwordBinding.imageViewPasswordToggle.setOnClickListener(view -> togglePasswordVisibility(passwordField, passwordBinding.imageViewPasswordToggle));

        binding.editTextAmount.setInputFieldType(RoundedEditTextView.InputFieldType.CURRENCY);
        binding.editTextAmount.setHint(getString(R.string.shop_new_hint_amount));
        enableAutoScrollWhileTyping(binding.editTextAmount.getEditText());

        bindSwitch(binding.switchCompatTopUp);
        bindSwitch(binding.switchCompatWithdraw);
        bindSwitch(binding.switchCompatCreate);
        bindSwitch(binding.switchCompatBlock);
        bindSwitch(binding.switchCompatIncome);
        bindSwitch(binding.switchCompatSettlement);
        bindSwitch(binding.switchCompatViewCredientials);
        bindSwitch(binding.switchCompatWithdrawalFee);
        binding.buttonSubmit.setOnClickListener(view -> createNewShop());
    }

    private void bindSwitch(SwitchCompat switchCompat) {
        switchCompat.setOnCheckedChangeListener((buttonView, isChecked) -> {
            int value = isChecked ? 1 : 0;
            if (buttonView.getId() == R.id.switchCompat_top_up) {
                topUpEnabled = value;
            } else if (buttonView.getId() == R.id.switchCompat_withdraw) {
                withdrawEnabled = value;
            } else if (buttonView.getId() == R.id.switchCompat_create) {
                createEnabled = value;
            } else if (buttonView.getId() == R.id.switchCompat_block) {
                blockEnabled = value;
            } else if (buttonView.getId() == R.id.switchCompat_income) {
                incomeEnabled = value;
            } else if (buttonView.getId() == R.id.switchCompat_settlement) {
                settlementEnabled = value;
            } else if (buttonView.getId() == R.id.switchCompat_view_credientials) {
                viewCredentialsEnabled = value;
            } else if (buttonView.getId() == R.id.switchCompat_withdrawal_fee) {
                withdrawalFeeEnabled = isChecked ? 0 : 1;
            }
        });
    }

    private void setupOptionField() {
        countryBinding = ViewEditTextOptionBinding.bind(binding.viewEditTextCountry.getRoot());
        countryBinding.getRoot().setOnClickListener(view -> {
            if (countryList == null || countryList.isEmpty()) {
                return;
            }
            CountryListViewAdapter countryListViewAdapter = new CountryListViewAdapter(this);
            SelectionBottomSheetDialogFragment.newInstance(
                    getString(R.string.shop_new_choose_country),
                    true,
                    countryList,
                    countryListViewAdapter,
                    (country, pos) -> {
                        clearError(this, countryBinding.panelOption);
                        stateList = null;
                        selectedState = null;
                        areaList = null;
                        selectedArea = null;
                        updateSelectedCountry(country);
                        updateOptionField();
                    },
                    Country.class).show(getSupportFragmentManager(), "CountrySheet");
        });
        stateBinding = ViewEditTextOptionBinding.bind(binding.viewEditTextState.getRoot());
        stateBinding.getRoot().setOnClickListener(view -> {
            if (selectedCountry == null) {
                Toast.makeText(CreateNewShopActivity.this, R.string.shop_new_choose_country_first, Toast.LENGTH_SHORT).show();
                return;
            }
            if (stateList == null || stateList.isEmpty()) {
                return;
            }
            StateListViewAdapter stateListViewAdapter = new StateListViewAdapter(this);
            SelectionBottomSheetDialogFragment.newInstance(
                    getString(R.string.shop_new_choose_state),
                    true,
                    stateList,
                    stateListViewAdapter,
                    (state, pos) -> {
                        clearError(this, stateBinding.panelOption);
                        areaList = null;
                        selectedArea = null;
                        updateSelectedState(state);
                        updateOptionField();
                    },
                    State.class).show(getSupportFragmentManager(), "StateSheet");
        });
        areaBinding = ViewEditTextOptionBinding.bind(binding.viewEditTextArea.getRoot());
        areaBinding.getRoot().setOnClickListener(view -> {
            if (selectedCountry == null) {
                Toast.makeText(CreateNewShopActivity.this, getString(R.string.shop_new_choose_country_first), Toast.LENGTH_SHORT).show();
                return;
            }
            if (selectedState == null) {
                Toast.makeText(CreateNewShopActivity.this, R.string.shop_new_choose_state_first, Toast.LENGTH_SHORT).show();
                return;
            }
            if (areaList == null || areaList.isEmpty()) {
                return;
            }
            AreaListViewAdapter areaListViewAdapter = new AreaListViewAdapter(this);
            SelectionBottomSheetDialogFragment.newInstance(
                    getString(R.string.shop_new_choose_area),
                    true,
                    areaList,
                    areaListViewAdapter,
                    (area, pos) -> {
                        clearError(this, areaBinding.panelOption);
                        updateSelectedArea(area);
                        updateOptionField();
                    },
                    Area.class).show(getSupportFragmentManager(), "AreaSheet");
        });
        updateOptionField();
    }

    private void updateOptionField() {
        if (selectedCountry != null) {
            countryBinding.textViewHintValue.setText(selectedCountry.getCountry_name());
            countryBinding.textViewHintValue.setTextColor(ContextCompat.getColor(this, R.color.white_FFFFFF));
        } else {
            countryBinding.textViewHintValue.setText(getString(R.string.shop_new_choose_country));
            countryBinding.textViewHintValue.setTextColor(ContextCompat.getColor(this, R.color.gray_C2C3CB));
        }
        if (selectedState != null) {
            stateBinding.textViewHintValue.setText(selectedState.getState_name());
            stateBinding.textViewHintValue.setTextColor(ContextCompat.getColor(this, R.color.white_FFFFFF));
        } else {
            stateBinding.textViewHintValue.setText(getString(R.string.shop_new_choose_state));
            stateBinding.textViewHintValue.setTextColor(ContextCompat.getColor(this, R.color.gray_C2C3CB));
        }
        if (selectedArea != null) {
            areaBinding.textViewHintValue.setText(selectedArea.getArea_name());
            areaBinding.textViewHintValue.setTextColor(ContextCompat.getColor(this, R.color.white_FFFFFF));
        } else {
            areaBinding.textViewHintValue.setText(getString(R.string.shop_new_choose_area));
            areaBinding.textViewHintValue.setTextColor(ContextCompat.getColor(this, R.color.gray_C2C3CB));
        }
    }

    private void clearErrors() {
        binding.editTextName.clearError();
        clearError(this, passwordBinding.panelPassword);
        binding.editTextUsername.clearError();
        clearError(this, countryBinding.panelOption);
        clearError(this, stateBinding.panelOption);
        clearError(this, areaBinding.panelOption);
    }

    private void enableAutoScrollWhileTyping(EditText editText) {
        editText.addTextChangedListener(new TextWatcher() {
            @Override
            public void beforeTextChanged(CharSequence s, int start, int count, int after) {
            }

            @Override
            public void onTextChanged(CharSequence s, int start, int before, int count) {
                scrollToFocusedView(editText);
            }

            @Override
            public void afterTextChanged(Editable s) {
            }
        });

        editText.setOnFocusChangeListener((v, hasFocus) -> {
            if (hasFocus) {
                scrollToFocusedView(v);
            }
        });
    }

    private void scrollToFocusedView(View focusedView) {
        binding.scrollView.postDelayed(() -> {
            Rect rect = new Rect();
            focusedView.getDrawingRect(rect);
            // Convert coordinates to ScrollView space
            binding.scrollView.offsetDescendantRectToMyCoords(focusedView, rect);
            int extraOffset = getResources().getDimensionPixelSize(R.dimen.scroll_focus_offset);
            binding.scrollView.smoothScrollTo(0, rect.top - extraOffset);
        }, 250); // wait for keyboard resize
    }

    private void processCountryList() {
        if (countryList == null) return;
        Country selected = null;
        for (Country country : countryList) {
            boolean isSelected = country.getCountry_code()
                    .equalsIgnoreCase(
                            manager != null
                                    ? manager.getAreas().getCountry_code()
                                    : "mys"
                    );
            country.setSelected(isSelected);
            if (isSelected) {
                selected = country;
            }
        }
        if (selected != null) {
            updateSelectedCountry(selected);
        }
        updateOptionField();
    }

    private void processStateList() {
        if (stateList == null) return;
        State selected = null;
        for (State state : stateList) {
            boolean isSelected = state.getState_code()
                    .equalsIgnoreCase(
                            manager != null
                                    ? manager.getAreas().getState_code()
                                    : ""
                    );
            state.setSelected(isSelected);
            if (isSelected) {
                selected = state;
            }
        }
        if (selected != null) {
            updateSelectedState(selected);
        }
        updateOptionField();
    }

    private void processAreaList() {
        if (areaList == null) return;
        Area selected = null;
        for (Area area : areaList) {
            boolean isSelected = area.getArea_code()
                    .equalsIgnoreCase(
                            manager != null
                                    ? manager.getAreas().getArea_code()
                                    : ""
                    );
            area.setSelected(isSelected);
            if (isSelected) {
                selected = area;
            }
        }
        if (selected != null) {
            updateSelectedArea(selected);
        }
        updateOptionField();
    }

    // TODO: 10/02/2026 Hidden dropdown list data - still keep the logic
    private void getCountryList() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestData request = new RequestData(manager.getManager_id());
        executeApiCall(this, apiService.getCountryList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<Country>> response) {
                countryList = new ArrayList<>(response.getData());
                processCountryList();
            }

            @Override
            public boolean onApiError(int code, String message) {
                return true;
            }

            @Override
            public boolean onFailure(Throwable t) {
                return true;
            }
        }, false);
    }

    private void getStateList() {
        if (selectedCountry == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestDataState request = new RequestDataState(manager.getManager_id(), selectedCountry.getCountry_code());
        executeApiCall(this, apiService.getStateList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                stateList = new ArrayList<>(response.getTbl_states());
                processStateList();
            }

            @Override
            public boolean onApiError(int code, String message) {
                return true;
            }

            @Override
            public boolean onFailure(Throwable t) {
                return true;
            }
        }, false);
    }

    private void getAreaList() {
        if (selectedState == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestDataArea request = new RequestDataArea(manager.getManager_id(), selectedState.getState_code());
        executeApiCall(this, apiService.getAreaList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                areaList = new ArrayList<>(response.getTbl_areas());
                processAreaList();
            }

            @Override
            public boolean onApiError(int code, String message) {
                return true;
            }

            @Override
            public boolean onFailure(Throwable t) {
                return true;
            }
        }, false);
    }

    private void createNewShop() {
        clearErrors();
        String shopName = binding.editTextName.getText();
        String shopLogin = binding.editTextUsername.getText();
        String password = passwordField.getText().toString();
        double amountType = binding.editTextAmount.getCurrencyAmount();
        boolean hasError = false;
        if (shopName.isEmpty()) {
            binding.editTextName.showError("");
            hasError = true;
        }
        if (shopLogin.isEmpty()) {
            binding.editTextUsername.showError("");
            hasError = true;
        }
        if (password.isEmpty()) {
            showError(this, passwordBinding.panelPassword);
            hasError = true;
        }
//        if (selectedCountry == null) {
//            showError(this, countryBinding.panelOption);
//            hasError = true;
//        }
//        if (selectedState == null) {
//            showError(this, stateBinding.panelOption);
//            hasError = true;
//        }
//        if (selectedArea == null) {
//            showError(this, areaBinding.panelOption);
//            hasError = true;
//        }
        if (hasError) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestShopNew request = new RequestShopNew(manager.getManager_id(), shopName, shopLogin, password, amountType, manager.getArea_code(), // TODO: 10/02/2026 from selected area to manager area code
                topUpEnabled, withdrawEnabled, createEnabled, blockEnabled, incomeEnabled, settlementEnabled, viewCredentialsEnabled, withdrawalFeeEnabled);
        executeApiCall(this, apiService.createNewShop(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Shop> response) {
                startAppActivity(new Intent(CreateNewShopActivity.this, CreateNewShopSuccessActivity.class),
                        null, true, false, false, true
                );
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

    private void updateSelectedCountry(Country selectedCountry) {
        if (selectedCountry == null) return;
        this.selectedCountry = selectedCountry;
        String selectedCode = selectedCountry.getCountry_code();
        for (Country country : countryList) {
            country.setSelected(
                    selectedCode != null &&
                            selectedCode.equalsIgnoreCase(country.getCountry_code())
            );
        }
        getStateList();
    }

    private void updateSelectedState(State selectedState) {
        if (selectedState == null) return;
        this.selectedState = selectedState;
        String selectedCode = selectedState.getState_code();
        for (State state : stateList) {
            state.setSelected(
                    selectedCode != null &&
                            selectedCode.equalsIgnoreCase(state.getState_code())
            );
        }
        getAreaList();
    }

    private void updateSelectedArea(Area selectedArea) {
        if (selectedArea == null) return;
        this.selectedArea = selectedArea;
        String selectedCode = selectedArea.getArea_code();
        for (Area area : areaList) {
            area.setSelected(
                    selectedCode != null &&
                            selectedCode.equalsIgnoreCase(area.getArea_code())
            );
        }
    }
}