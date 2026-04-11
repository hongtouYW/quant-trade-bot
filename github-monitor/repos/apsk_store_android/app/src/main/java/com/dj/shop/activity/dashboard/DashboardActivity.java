package com.dj.shop.activity.dashboard;

import android.Manifest;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.graphics.Typeface;
import android.graphics.drawable.Drawable;
import android.os.Build;
import android.os.Bundle;
import android.text.Spannable;
import android.text.SpannableString;
import android.text.style.AbsoluteSizeSpan;
import android.text.style.ImageSpan;
import android.view.View;
import android.widget.Toast;

import androidx.activity.OnBackPressedCallback;
import androidx.core.content.ContextCompat;
import androidx.core.content.res.ResourcesCompat;
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout;

import com.dj.shop.R;
import com.dj.shop.activity.BaseActivity;
import com.dj.shop.activity.account.ProfileActivity;
import com.dj.shop.activity.account.SettingsActivity;
import com.dj.shop.activity.transaction.ActionMainActivity;
import com.dj.shop.activity.transaction.TransactionHistoryActivity;
import com.dj.shop.adapter.AccountGridItemAdapter;
import com.dj.shop.adapter.ActionGridItemAdapter;
import com.dj.shop.databinding.ActivityDashboardBinding;
import com.dj.shop.enums.ActionType;
import com.dj.shop.model.ItemGrid;
import com.dj.shop.model.request.RequestProfile;
import com.dj.shop.model.request.RequestProfileGeneral;
import com.dj.shop.model.request.RequestUpdateFirebase;
import com.dj.shop.model.response.BaseResponse;
import com.dj.shop.model.response.Country;
import com.dj.shop.model.response.Shop;
import com.dj.shop.util.ApiCallback;
import com.dj.shop.util.ApiClient;
import com.dj.shop.util.ApiService;
import com.dj.shop.util.CacheManager;
import com.dj.shop.util.FormatUtils;
import com.dj.shop.util.SingletonUtil;
import com.dj.shop.util.StringUtil;
import com.dj.shop.widget.CustomTypefaceSpan;
import com.dj.shop.widget.ExpandableHeightGridView;
import com.google.gson.Gson;

import java.util.ArrayList;
import java.util.List;

public class DashboardActivity extends BaseActivity {
    private long backPressed;
    private static final int TIME_INTERVAL = 2000;

    private Shop shop;
    private double todayIncome = 0.0;
    private boolean isAmountVisible = false;
    private int totalMember = 0, totalPlayer = 0;

    private ActivityDashboardBinding binding;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityDashboardBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        shop = CacheManager.getObject(this, CacheManager.KEY_SHOP_PROFILE, Shop.class);
        setupUI();
        setupListeners();
        setupGrids();
        setupShopInfo();
        getCountryList();
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            if (checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
                requestPermissions(new String[]{Manifest.permission.POST_NOTIFICATIONS}, 1001);
            }
        }
    }

    @Override
    protected void onResume() {
        super.onResume();
        getShopStat();
        updateFirebaseToken();
    }

    private void setupUI() {
        binding.swipeRefresh.setOnRefreshListener(new SwipeRefreshLayout.OnRefreshListener() {
            @Override
            public void onRefresh() {
                getShopStat();
            }
        });

        binding.textViewAmount.setText("****");
        binding.textViewIncome.setText("****");

        String label = getString(R.string.dashboard_income);
        String currency = getString(R.string.placeholder_currency);
        SpannableString ss = new SpannableString(label + " " + currency);
        Typeface typeface = ResourcesCompat.getFont(this, R.font.poppins_regular);
        ss.setSpan(new CustomTypefaceSpan(typeface), 0, label.length(), Spannable.SPAN_EXCLUSIVE_EXCLUSIVE);
        ss.setSpan(new AbsoluteSizeSpan(15, true), 0, label.length(), Spannable.SPAN_EXCLUSIVE_EXCLUSIVE);
        int start = label.length() + 1; // after space
        int end = ss.length();
        ss.setSpan(new CustomTypefaceSpan(typeface), start, end, Spannable.SPAN_EXCLUSIVE_EXCLUSIVE);
        ss.setSpan(new AbsoluteSizeSpan(12, true), start, end, Spannable.SPAN_EXCLUSIVE_EXCLUSIVE);
        binding.textViewToday.setText(ss);

        String yxiText = getString(R.string.dashboard_yxi_count);
        Drawable yxiDrawable = ContextCompat.getDrawable(this, R.drawable.ic_controller);
        if (yxiDrawable != null) {
            int width = dpToPx(13);
            int height = dpToPx(10);
            yxiDrawable.setBounds(0, 0, width, height);
        }
        ImageSpan yxiImageSpan = new ImageSpan(yxiDrawable, ImageSpan.ALIGN_CENTER);
        SpannableString yxiSs = new SpannableString(yxiText + "  ");
        yxiSs.setSpan(yxiImageSpan, yxiSs.length() - 1, yxiSs.length(), Spannable.SPAN_EXCLUSIVE_EXCLUSIVE);
        yxiSs.setSpan(new AbsoluteSizeSpan(15, true), 0, yxiText.length(), Spannable.SPAN_EXCLUSIVE_EXCLUSIVE);
        yxiSs.setSpan(new CustomTypefaceSpan(typeface), 0, yxiText.length(), Spannable.SPAN_EXCLUSIVE_EXCLUSIVE);
        binding.textViewYxiLabel.setText(yxiSs);

        String playerText = getString(R.string.dashboard_player_count);
        Drawable playerDrawable = ContextCompat.getDrawable(this, R.drawable.ic_group);
        if (playerDrawable != null) {
            int width = dpToPx(13);
            int height = dpToPx(10);
            playerDrawable.setBounds(0, 0, width, height);
        }
        ImageSpan playerImageSpan = new ImageSpan(playerDrawable, ImageSpan.ALIGN_CENTER);
        SpannableString playerSs = new SpannableString(playerText + "  ");
        playerSs.setSpan(playerImageSpan, playerSs.length() - 1, playerSs.length(), Spannable.SPAN_EXCLUSIVE_EXCLUSIVE);
        playerSs.setSpan(new AbsoluteSizeSpan(15, true), 0, playerText.length(), Spannable.SPAN_EXCLUSIVE_EXCLUSIVE);
        playerSs.setSpan(new CustomTypefaceSpan(typeface), 0, playerText.length(), Spannable.SPAN_EXCLUSIVE_EXCLUSIVE);
        binding.textViewPlayerLabel.setText(playerSs);

        binding.imageViewAmountToggle.setImageResource(R.drawable.ic_home_eye_off);
    }

    private void setupListeners() {
        getOnBackPressedDispatcher().addCallback(this, new OnBackPressedCallback(true) {
            @Override
            public void handleOnBackPressed() {
                hideKeyboard(DashboardActivity.this);
                showTapTwiceExitToast();
            }
        });
        binding.imageViewSearch.setOnClickListener(view ->
                startAppActivity(new Intent(DashboardActivity.this, DashboardSearchActivity.class),
                        null, false, false, true)
        );
        binding.imageViewProfile.setOnClickListener(view ->
                startAppActivity(new Intent(DashboardActivity.this, ProfileActivity.class),
                        null, false, false, true)
        );
        binding.panelAlert.setOnClickListener(view -> {
            notifyLowBalance();
        });

        binding.imageViewAmountToggle.setOnClickListener(view -> {
            isAmountVisible = !isAmountVisible;
            setupShopInfo();
        });
    }

    private void setupGrids() {
        setupAccountGrid();
        setupActionGrid();
    }

    private void setupAccountGrid() {
        if (shop == null) {
            return;
        }
        List<ItemGrid> accountItems = new ArrayList<>();
        if (shop.isCanCreate()) {
            accountItems.add(new ItemGrid(1, R.drawable.ic_create, getString(R.string.dashboard_account_new)));
        }
        accountItems.add(new ItemGrid(2, R.drawable.ic_password, getString(R.string.dashboard_account_password)));
        if (shop.isCanBlock()) {
            accountItems.add(new ItemGrid(3, R.drawable.ic_block, getString(R.string.dashboard_account_block)));
        }

        AccountGridItemAdapter adapter = new AccountGridItemAdapter(this, accountItems);
        binding.gridViewAccount.setAdapter(adapter);
        binding.gridViewAccount.setExpanded(true);

        binding.gridViewAccount.setOnItemClickListener((parent, view, position, id) -> {
            ItemGrid clicked = accountItems.get(position);
            ActionType actionType = null;
            switch (clicked.id) {
                case 1:
                    actionType = ActionType.CREATE_USER;
                    break;
                case 2:
                    actionType = ActionType.CHANGE_PASSWORD;
                    break;
                case 3:
                    actionType = ActionType.BLOCK_USER;
                    break;
            }
            if (actionType != null) {
                Intent intent = new Intent(this, ActionMainActivity.class);
                Bundle bundle = new Bundle();
                bundle.putString("data", new Gson().toJson(actionType));
                startAppActivity(intent, bundle, false, false, true);
            }
        });
    }

    private void setupActionGrid() {
        List<ItemGrid> actionItems = new ArrayList<>();
        if (shop.isCanDeposit()) {
            actionItems.add(new ItemGrid(1, R.drawable.bg_top_up, R.drawable.ic_top_up, getString(R.string.dashboard_action_top_up)));
        }
        if (shop.isCanWithdraw()) {
            actionItems.add(new ItemGrid(2, R.drawable.bg_withdrawal, R.drawable.ic_withdrawal, getString(R.string.dashboard_action_withdraw)));
        }
        actionItems.add(new ItemGrid(3, R.drawable.bg_history, R.drawable.ic_history, getString(R.string.dashboard_action_history)));
        actionItems.add(new ItemGrid(4, R.drawable.bg_setting, R.drawable.ic_setting, getString(R.string.dashboard_action_setting)));

        ActionGridItemAdapter adapter = new ActionGridItemAdapter(this, actionItems);
        ExpandableHeightGridView gridView = binding.gridViewAction;
        gridView.setAdapter(adapter);
        gridView.setExpanded(true);

        gridView.setOnItemClickListener((parent, view, position, id) -> {
            ItemGrid clicked = actionItems.get(position);
            Intent intent = null;
            Bundle bundle = null;
            switch (clicked.id) {
                case 1:
                    intent = new Intent(DashboardActivity.this, ActionMainActivity.class);
                    bundle = new Bundle();
                    bundle.putString("data", new Gson().toJson(ActionType.TOP_UP));
                    break;
                case 2:
                    intent = new Intent(DashboardActivity.this, ActionMainActivity.class);
                    bundle = new Bundle();
                    bundle.putString("data", new Gson().toJson(ActionType.WITHDRAWAL));
                    break;
                case 3:
                    intent = new Intent(DashboardActivity.this, TransactionHistoryActivity.class);
                    break;
                case 4:
                    intent = new Intent(DashboardActivity.this, SettingsActivity.class);
                    break;
            }
            startAppActivity(intent, bundle, false, false, true);
        });
    }

    private void setupShopInfo() {
        if (shop == null) {
            return;
        }
        binding.textViewAmount.setText(isAmountVisible ? FormatUtils.formatAmount(shop.getBalance()) : "****");
        binding.imageViewAmountToggle.setImageResource(
                isAmountVisible ? R.drawable.ic_home_eye : R.drawable.ic_home_eye_off
        );
        String incomeStr = FormatUtils.formatAmount(Math.abs(todayIncome));
        if (todayIncome >= 0) {
            binding.textViewIncome.setText(isAmountVisible ? String.format("+%s", incomeStr) : "****");
        } else {
            binding.textViewIncome.setText(isAmountVisible ? String.format("-%s", incomeStr) : "****");
        }
        binding.panelIncome.setVisibility(shop.isCanIncome() ? View.VISIBLE : View.GONE);
        binding.textViewYxi.setText(String.valueOf(totalMember));
        binding.textViewPlayer.setText(String.valueOf(totalPlayer));
    }

    private int dpToPx(int dp) {
        return (int) (dp * getResources().getDisplayMetrics().density + 0.5f);
    }

    private void getCountryList() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestProfileGeneral request = new RequestProfileGeneral(shop.getShop_id());
        executeApiCall(this, apiService.getCountryList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<Country>> response) {
                List<Country> countryList = response.getData();
                for (Country country : countryList) {
                    country.setSelected(country.getCountry_code().equalsIgnoreCase("mys"));
                }
                CacheManager.saveObject(DashboardActivity.this, CacheManager.KEY_COUNTRY_LIST, countryList);
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

    private void updateFirebaseToken() {
        String fcmToken = SingletonUtil.getInstance().getFcmToken();
        if (shop == null) return;
        if (fcmToken == null || fcmToken.isEmpty()) return;
        if (fcmToken.equalsIgnoreCase(shop.getDevicekey())) return;
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestUpdateFirebase request = new RequestUpdateFirebase(shop.getShop_id(), fcmToken);
        executeApiCall(this, apiService.updateFirebaseToken(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Shop> response) {
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

    private void getShopStat() {
        if (shop == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestProfile request = new RequestProfile(String.valueOf(shop.getShop_id()));
        executeApiCall(this, apiService.getShopStat(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Shop> response) {
                binding.swipeRefresh.setRefreshing(false);
                Shop updatedShopData = response.getData();
                if (updatedShopData != null) {
                    CacheManager.saveObject(DashboardActivity.this, CacheManager.KEY_SHOP_PROFILE, updatedShopData);
                }
                shop = updatedShopData;
                todayIncome = response.getTotalincome();
                totalMember = response.getTotalmember();
                totalPlayer = response.getTotalplayer();
                setupGrids();
                setupShopInfo();
            }

            @Override
            public boolean onApiError(int code, String message) {
                binding.swipeRefresh.setRefreshing(false);
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                binding.swipeRefresh.setRefreshing(false);
                return false;
            }
        }, false);
    }

    private void notifyLowBalance() {
        if (shop == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestProfile request = new RequestProfile(String.valueOf(shop.getShop_id()));
        executeApiCall(this, apiService.notifyLowBalance(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Shop> response) {
                String message = "";
                if (!StringUtil.isNullOrEmpty(response.getMessage())) {
                    message = response.getMessage();
                }
                Toast.makeText(DashboardActivity.this, message, Toast.LENGTH_LONG).show();
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

    private void showTapTwiceExitToast() {
        if (backPressed + TIME_INTERVAL > System.currentTimeMillis()) {
            finish();
        } else {
            Toast.makeText(this, getString(R.string.exit_tap_twice), Toast.LENGTH_SHORT).show();
        }
        backPressed = System.currentTimeMillis();
    }
}