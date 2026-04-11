package com.dj.manager.activity.dashboard;

import android.Manifest;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.graphics.Typeface;
import android.graphics.drawable.Drawable;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.text.Spannable;
import android.text.SpannableString;
import android.text.style.AbsoluteSizeSpan;
import android.text.style.ImageSpan;
import android.widget.Toast;

import androidx.activity.OnBackPressedCallback;
import androidx.activity.result.ActivityResultLauncher;
import androidx.activity.result.contract.ActivityResultContracts;
import androidx.core.content.ContextCompat;
import androidx.core.content.res.ResourcesCompat;
import androidx.core.view.GravityCompat;

import com.dj.manager.R;
import com.dj.manager.activity.BaseActivity;
import com.dj.manager.activity.log.LogListActivity;
import com.dj.manager.activity.log.YxiLogListActivity;
import com.dj.manager.activity.notification.NotificationActivity;
import com.dj.manager.activity.point.PointListActivity;
import com.dj.manager.activity.profile.ProfileActivity;
import com.dj.manager.activity.qr.QrScannerActivity;
import com.dj.manager.activity.shop.ShopDetailsActivity;
import com.dj.manager.activity.shop.ShopManagementActivity;
import com.dj.manager.activity.user.UserManagementActivity;
import com.dj.manager.adapter.ShopListViewAdapter;
import com.dj.manager.databinding.ActivityDashboardBinding;
import com.dj.manager.databinding.ViewSideMenuBinding;
import com.dj.manager.model.request.RequestData;
import com.dj.manager.model.request.RequestProfile;
import com.dj.manager.model.request.RequestShopProfile;
import com.dj.manager.model.request.RequestUpdateFirebase;
import com.dj.manager.model.response.BaseResponse;
import com.dj.manager.model.response.Country;
import com.dj.manager.model.response.Manager;
import com.dj.manager.model.response.Notification;
import com.dj.manager.model.response.Shop;
import com.dj.manager.model.response.ShopPin;
import com.dj.manager.util.ApiCallback;
import com.dj.manager.util.ApiClient;
import com.dj.manager.util.ApiService;
import com.dj.manager.util.CacheManager;
import com.dj.manager.util.FormatUtils;
import com.dj.manager.util.SingletonUtil;
import com.dj.manager.util.VersionUtil;
import com.dj.manager.widget.CustomTypefaceSpan;
import com.google.gson.Gson;

import java.util.List;

public class DashboardActivity extends BaseActivity {
    private long backPressed;
    private static final int TIME_INTERVAL = 2000;

    private boolean isBalanceVisible = false;
    private boolean isCapitalVisible = false;
    private Manager manager;
    private int totalShop = 0;
    private double totalShopBalance = 0.00;
    private double totalShopCapital = 0.00;
    private int unreadCount = 0;
    private ShopListViewAdapter shopListViewAdapter;
    private List<Shop> shopList;
    private ActivityDashboardBinding binding;
    private final ActivityResultLauncher<Intent> qrScannerLauncher =
            registerForActivityResult(new ActivityResultContracts.StartActivityForResult(), result -> {
                if (result.getResultCode() == RESULT_OK && result.getData() != null) {
                    String qrResult = result.getData().getStringExtra("data");
                    if (qrResult != null && qrResult.startsWith("apsk://")) {
                        Intent intent = new Intent(Intent.ACTION_VIEW, Uri.parse(qrResult));
                        startActivity(intent);
                    }
                }
            });

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityDashboardBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        manager = CacheManager.getObject(this, CacheManager.KEY_MANAGER_PROFILE, Manager.class);

        getOnBackPressedDispatcher().addCallback(this, new OnBackPressedCallback(true) {
            @Override
            public void handleOnBackPressed() {
                hideKeyboard(DashboardActivity.this);
                showTapTwiceExitToast();
            }
        });

        setupSideMenu();
        setupUI();
        setupSearchPanel();
        setupShopListView();
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
        updateFirebaseToken();
        getManagerStat();
        getShopList();
    }

    private void setupSideMenu() {
        ViewSideMenuBinding sideMenuBinding = ViewSideMenuBinding.bind(binding.panelSideMenu.getRoot());
        sideMenuBinding.panelShopManagement.setOnClickListener(view ->
                startAppActivity(new Intent(DashboardActivity.this, ShopManagementActivity.class),
                        null, false, false, false, true
                ));
        sideMenuBinding.panelUserManagement.setOnClickListener(view ->
                startAppActivity(new Intent(DashboardActivity.this, UserManagementActivity.class),
                        null, false, false, false, true
                ));
        sideMenuBinding.panelPointHistory.setOnClickListener(view ->
                startAppActivity(new Intent(DashboardActivity.this, PointListActivity.class),
                        null, false, false, false, true
                ));
        sideMenuBinding.panelSystemLog.setOnClickListener(view ->
                startAppActivity(new Intent(DashboardActivity.this, LogListActivity.class),
                        null, false, false, false, true
                ));
        sideMenuBinding.panelYxiLog.setOnClickListener(view ->
                startAppActivity(new Intent(DashboardActivity.this, YxiLogListActivity.class),
                        null, false, false, false, true
                ));
        sideMenuBinding.panelScan.setOnClickListener(view -> {
            Intent intent = new Intent(this, QrScannerActivity.class);
            qrScannerLauncher.launch(intent);
        });
        sideMenuBinding.panelSettings.setOnClickListener(view ->
                startAppActivity(
                        new Intent(this, ProfileActivity.class),
                        null, false, false, false, true
                ));
        sideMenuBinding.textViewVersion.setText(String.format("V %s_%s", VersionUtil.getVersionName(this), VersionUtil.getVersionCode(this)));
    }

    private void setupUI() {
        binding.imageViewMenu.setOnClickListener(v -> binding.drawerLayout.openDrawer(GravityCompat.START));
        binding.textViewBalance.setOnClickListener(view -> {
            isBalanceVisible = !isBalanceVisible;
            setupViewData();
        });
        binding.textViewCapital.setOnClickListener(view -> {
            isCapitalVisible = !isCapitalVisible;
            setupViewData();
        });
        binding.imageViewNotification.setOnClickListener(view ->
                startAppActivity(new Intent(this, NotificationActivity.class),
                        null, false, false, false, true
                ));
        binding.imageViewProfile.setOnClickListener(view ->
                startAppActivity(new Intent(this, ProfileActivity.class),
                        null, false, false, false, true
                ));
        binding.panelQrScan.setOnClickListener(view -> {
            Intent intent = new Intent(this, QrScannerActivity.class);
            qrScannerLauncher.launch(intent);
        });
        binding.panelPointHistory.setOnClickListener(view ->
                startAppActivity(new Intent(DashboardActivity.this, PointListActivity.class),
                        null, false, false, false, true
                ));
        binding.panelShopManagement.setOnClickListener(view ->
                startAppActivity(new Intent(DashboardActivity.this, ShopManagementActivity.class),
                        null, false, false, false, true
                ));
        binding.panelUserManagement.setOnClickListener(view ->
                startAppActivity(new Intent(DashboardActivity.this, UserManagementActivity.class),
                        null, false, false, false, true
                ));
        binding.textViewAll.setOnClickListener(view ->
                startAppActivity(new Intent(this, ShopManagementActivity.class),
                        null, false, false, false, true
                ));
    }

    private void setupSearchPanel() {
        binding.panelSearch.setOnClickListener(view ->
                startAppActivity(new Intent(this, DashboardSearchActivity.class),
                        null, false, false, false, true
                ));
    }

    private void setupShopListView() {
        shopListViewAdapter = new ShopListViewAdapter(this, true);
        binding.listViewShop.setAdapter(shopListViewAdapter);
        binding.listViewShop.setExpanded(true);
        shopListViewAdapter.setOnItemClickListener((position, object) -> {
            Bundle bundle = new Bundle();
            bundle.putString("data", new Gson().toJson(object));
            startAppActivity(new Intent(DashboardActivity.this, ShopDetailsActivity.class),
                    bundle, false, false, false, true);
        });
    }

    private void setupViewData() {
        binding.imageViewNotification.setImageResource(unreadCount > 0 ? R.drawable.ic_notification_top_unread : R.drawable.ic_notification_top);

        String balanceText = isBalanceVisible ? FormatUtils.formatAmount(totalShopBalance) : "****";
        Drawable balanceDrawable = ContextCompat.getDrawable(this, isBalanceVisible ? R.drawable.ic_eye_on_home : R.drawable.ic_eye_off_home);
        if (balanceDrawable != null) {
            int width = FormatUtils.dpToPx(this, 24);
            int height = FormatUtils.dpToPx(this, 24);
            balanceDrawable.setBounds(0, 0, width, height);
        }
        ImageSpan balanceImageSpan = new ImageSpan(balanceDrawable, ImageSpan.ALIGN_CENTER);
        SpannableString balanceSs = new SpannableString(balanceText + "  ");
        Typeface typeface = ResourcesCompat.getFont(this, R.font.poppins_semi_bold);
        balanceSs.setSpan(balanceImageSpan, balanceSs.length() - 1, balanceSs.length(), Spannable.SPAN_EXCLUSIVE_EXCLUSIVE);
        balanceSs.setSpan(new AbsoluteSizeSpan(36, true), 0, balanceText.length(), Spannable.SPAN_EXCLUSIVE_EXCLUSIVE);
        balanceSs.setSpan(new CustomTypefaceSpan(typeface), 0, balanceText.length(), Spannable.SPAN_EXCLUSIVE_EXCLUSIVE);
        binding.textViewBalance.setText(balanceSs);

        binding.textViewShopCount.setText(String.valueOf(totalShop));

        String capitalText = isCapitalVisible ? FormatUtils.formatAmount(totalShopCapital) : "****";
        Drawable capitalDrawable = ContextCompat.getDrawable(this, isCapitalVisible ? R.drawable.ic_eye_on_home : R.drawable.ic_eye_off_home);
        if (capitalDrawable != null) {
            int width = FormatUtils.dpToPx(this, 24);
            int height = FormatUtils.dpToPx(this, 24);
            capitalDrawable.setBounds(0, 0, width, height);
        }
        ImageSpan capitalImageSpan = new ImageSpan(capitalDrawable, ImageSpan.ALIGN_CENTER);
        SpannableString capitalSs = new SpannableString(capitalText + "  ");
        capitalSs.setSpan(capitalImageSpan, capitalSs.length() - 1, capitalSs.length(), Spannable.SPAN_EXCLUSIVE_EXCLUSIVE);
        capitalSs.setSpan(new AbsoluteSizeSpan(36, true), 0, capitalText.length(), Spannable.SPAN_EXCLUSIVE_EXCLUSIVE);
        capitalSs.setSpan(new CustomTypefaceSpan(typeface), 0, capitalText.length(), Spannable.SPAN_EXCLUSIVE_EXCLUSIVE);
        binding.textViewCapital.setText(capitalSs);
    }

    private void getCountryList() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestData request = new RequestData(manager.getManager_id());
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
        if (manager == null) return;
        if (fcmToken == null || fcmToken.isEmpty()) return;
        if (fcmToken.equalsIgnoreCase(manager.getDevicekey())) return;
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestUpdateFirebase request = new RequestUpdateFirebase(manager.getManager_id(), fcmToken);
        executeApiCall(this, apiService.updateFirebaseToken(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Manager> response) {
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

    private void getManagerStat() {
        if (manager == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestProfile request = new RequestProfile(manager.getManager_id());
        executeApiCall(this, apiService.getManagerStat(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Manager> response) {
                Manager updatedManagerData = response.getData();
                if (updatedManagerData != null) {
                    CacheManager.saveObject(DashboardActivity.this, CacheManager.KEY_MANAGER_PROFILE, updatedManagerData);
                }
                manager = updatedManagerData;
                totalShop = response.getTotalshop();
                totalShopBalance = response.getTotalshopbalance();
                totalShopCapital = manager.getPrincipal();
                List<Notification> notificationList = response.getTbl_notification();
                unreadCount = (int) (notificationList != null
                        ? notificationList.stream().filter(n -> !n.getIs_read()).count()
                        : 0);
                setupViewData();
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

    private void getShopList() {
        if (manager == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestProfile request = new RequestProfile(manager.getManager_id());
        executeApiCall(this, apiService.getShopList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<Shop>> response) {
                List<Shop> allShops = response.getData();
                if (allShops != null && allShops.size() > 10) {
                    shopList = allShops.subList(0, 10);
                } else {
                    shopList = allShops;
                }
                shopListViewAdapter.setData(shopList);
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

    public void pinUnpinShop(Shop shop) {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestShopProfile request = new RequestShopProfile(manager.getManager_id(), shop.getShop_id());
        if (!shop.isPinned()) {
            executeApiCall(this, apiService.pinShop(request), new ApiCallback<>() {
                @Override
                public void onSuccess(BaseResponse<ShopPin> response) {
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
        } else {
            executeApiCall(this, apiService.unpinShop(request), new ApiCallback<>() {
                @Override
                public void onSuccess(BaseResponse<Void> response) {
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

    private void showTapTwiceExitToast() {
        if (backPressed + TIME_INTERVAL > System.currentTimeMillis()) {
            finish();
        } else {
            Toast.makeText(this, getString(R.string.exit_tap_twice), Toast.LENGTH_SHORT).show();
        }
        backPressed = System.currentTimeMillis();
    }
}