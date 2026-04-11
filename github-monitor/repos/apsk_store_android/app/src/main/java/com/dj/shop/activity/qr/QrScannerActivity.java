package com.dj.shop.activity.qr;

import android.Manifest;
import android.annotation.SuppressLint;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.os.Bundle;
import android.util.Log;
import android.widget.Toast;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.camera.core.Camera;
import androidx.camera.core.CameraSelector;
import androidx.camera.core.ImageAnalysis;
import androidx.camera.core.ImageProxy;
import androidx.camera.core.Preview;
import androidx.camera.lifecycle.ProcessCameraProvider;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;

import com.dj.shop.R;
import com.dj.shop.activity.BaseActivity;
import com.dj.shop.activity.yxi.SearchDetailsActivity;
import com.dj.shop.databinding.ActivityQrScannerBinding;
import com.dj.shop.databinding.ViewToolbarBinding;
import com.dj.shop.enums.ActionType;
import com.dj.shop.enums.ScanMode;
import com.dj.shop.model.request.RequestPlayerSearch;
import com.dj.shop.model.response.BaseResponse;
import com.dj.shop.model.response.Player;
import com.dj.shop.model.response.Shop;
import com.dj.shop.util.ApiCallback;
import com.dj.shop.util.ApiClient;
import com.dj.shop.util.ApiService;
import com.dj.shop.util.CacheManager;
import com.google.common.util.concurrent.ListenableFuture;
import com.google.gson.Gson;
import com.google.zxing.BinaryBitmap;
import com.google.zxing.MultiFormatReader;
import com.google.zxing.PlanarYUVLuminanceSource;
import com.google.zxing.Result;
import com.google.zxing.common.HybridBinarizer;

import java.nio.ByteBuffer;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

public class QrScannerActivity extends BaseActivity {

    private ActivityQrScannerBinding binding;
    private Shop shop;

    private boolean hasScanned = false;
    private boolean isFlashOn = false;

    private Camera camera;
    private ScanMode scanMode;
    private ExecutorService cameraExecutor;

    public static final String SCAN_MODE = "SCAN_MODE";

    @Override
    protected void onCreate(@Nullable Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityQrScannerBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        shop = CacheManager.getObject(this, CacheManager.KEY_SHOP_PROFILE, Shop.class);

        binding.imageViewFlash.setOnClickListener(v -> {
            if (camera != null) {
                isFlashOn = !isFlashOn;
                camera.getCameraControl().enableTorch(isFlashOn);
                binding.imageViewFlash.setColorFilter(ContextCompat.getColor(this,
                        isFlashOn ? R.color.gold_D4AF37 : R.color.white_FFFFFF));
            }
        });

        ViewToolbarBinding toolbarBinding = ViewToolbarBinding.bind(binding.toolbar.getRoot());
        toolbarBinding.getRoot().setBackgroundColor(ContextCompat.getColor(this, R.color.blue_000122));
        setupToolbar(binding.toolbar.getRoot(), getString(R.string.qr_scan_title), 0, null);

        scanMode = (ScanMode) getIntent().getSerializableExtra(SCAN_MODE);
        cameraExecutor = Executors.newSingleThreadExecutor();

        if (ContextCompat.checkSelfPermission(this, Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED) {
            startCamera();
        } else {
            ActivityCompat.requestPermissions(this, new String[]{Manifest.permission.CAMERA}, 100);
        }
        startCamera();
    }

    private void startCamera() {
        ListenableFuture<ProcessCameraProvider> cameraProviderFuture =
                ProcessCameraProvider.getInstance(this);

        cameraProviderFuture.addListener(() -> {
            try {
                ProcessCameraProvider cameraProvider = cameraProviderFuture.get();

                Preview preview = new Preview.Builder().build();
                CameraSelector cameraSelector = CameraSelector.DEFAULT_BACK_CAMERA;

                ImageAnalysis imageAnalysis = new ImageAnalysis.Builder()
                        .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
                        .setTargetRotation(getWindowManager().getDefaultDisplay().getRotation())
                        .build();

                imageAnalysis.setAnalyzer(cameraExecutor, this::analyzeImage);

                preview.setSurfaceProvider(binding.previewView.getSurfaceProvider());

                cameraProvider.unbindAll();
                camera = cameraProvider.bindToLifecycle(this, cameraSelector, preview, imageAnalysis);

            } catch (Exception e) {
                Log.e("###", "startCamera:", e);
            }
        }, ContextCompat.getMainExecutor(this));
    }

    @SuppressLint("UnsafeOptInUsageError")
    private void analyzeImage(@NonNull ImageProxy image) {
        if (hasScanned) {
            image.close();
            return;
        }

        try (image) {
            int width = image.getWidth();
            int height = image.getHeight();

            byte[] yBytes = yBufferToByteArray(image); // <-- fixed here

            int squareSize = Math.min(width, height) / 2;
            int left = (width - squareSize) / 2;
            int top = (height - squareSize) / 2;

            PlanarYUVLuminanceSource source = new PlanarYUVLuminanceSource(
                    yBytes, width, height,
                    left, top, squareSize, squareSize,
                    false
            );

            BinaryBitmap bitmap = new BinaryBitmap(new HybridBinarizer(source));

            Result result = new MultiFormatReader().decode(bitmap);
            if (result != null) {
                hasScanned = true;
                runOnUiThread(() -> handleQrResult(result.getText()));
            }
        } catch (Exception e) {
            // Ignore decoding failures (they're normal during scanning)
        }
    }

    private byte[] yBufferToByteArray(ImageProxy image) {
        ImageProxy.PlaneProxy yPlane = image.getPlanes()[0];
        ByteBuffer buffer = yPlane.getBuffer();
        byte[] data = new byte[buffer.remaining()];
        buffer.get(data);
        return data;
    }

    private void handleQrResult(String qrContent) {
        Log.d("### QR", "Scanned: " + qrContent);
        // Delay finish to let user see the result

//        Uri uri = Uri.parse(qrContent);
//        String host = uri.getHost();
//        String id = uri.getQueryParameter("id");
//        String yxi = uri.getQueryParameter("yxi");
        switch (scanMode) {
//            case TOPUP:
//                if (host.equalsIgnoreCase("member")) {
//                    Bundle bundle = new Bundle();
//                    bundle.putString("data", new Gson().toJson(ActionType.TOP_UP));
//                    bundle.putString("id", id);
//                    startAppActivity(new Intent(QrScannerActivity.this, ActionUserDetailsActivity.class), bundle, false, false, true);
//                } else if (host.equalsIgnoreCase("player")) {
//                    searchPlayer(yxi, id, ActionType.TOP_UP);
//                } else {
//                    Toast.makeText(this, "Invalid QR code", Toast.LENGTH_SHORT).show();
//                }
//                break;
            case WITHDRAW:
                Intent resultIntent = new Intent();
                resultIntent.putExtra("data", qrContent);
                setResult(RESULT_OK, resultIntent);
//                if (host.equalsIgnoreCase("member")) {
//                    Bundle bundle = new Bundle();
//                    bundle.putString("data", new Gson().toJson(ActionType.WITHDRAWAL));
//                    bundle.putString("id", id);
//                    startAppActivity(new Intent(QrScannerActivity.this, ActionUserDetailsActivity.class), bundle, false, false, true);
//                } else if (host.equalsIgnoreCase("player")) {
//                    searchPlayer(yxi, id, ActionType.WITHDRAWAL);
//                } else {
//                    Toast.makeText(this, "Invalid QR code", Toast.LENGTH_SHORT).show();
//                }
                break;
//            case CHANGE_PASSWORD:
//                if (host.equalsIgnoreCase("member")) {
//                    Bundle bundle = new Bundle();
//                    bundle.putString("data", new Gson().toJson(ActionType.CHANGE_PASSWORD));
//                    bundle.putString("id", id);
//                    startAppActivity(new Intent(QrScannerActivity.this, ActionUserDetailsActivity.class), bundle, false, false, true);
//                } else {
//                    Toast.makeText(this, "Invalid QR code", Toast.LENGTH_SHORT).show();
//                }
//                break;
        }
        // Delay finish to let user see the result
        binding.previewView.postDelayed(this::finish, 1500);
    }

    private void searchPlayer(String yxi, String id, ActionType actionType) {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestPlayerSearch request = new RequestPlayerSearch(shop.getShop_id(), yxi, id);
        executeApiCall(this, apiService.playerSearch(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Player> response) {
                Player player = response.getData();
                if (player != null) {
                    Bundle bundle = new Bundle();
                    bundle.putString("data", new Gson().toJson(actionType));
                    bundle.putString("player", new Gson().toJson(player));
                    startAppActivity(new Intent(QrScannerActivity.this, SearchDetailsActivity.class), bundle, false, false, true);
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

    @Override
    public void onRequestPermissionsResult(int requestCode,
                                           @NonNull String[] permissions, @NonNull int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);
        if (requestCode == 100 && grantResults.length > 0 &&
                grantResults[0] == PackageManager.PERMISSION_GRANTED) {
            startCamera();
        } else {
            Toast.makeText(this, getString(R.string.qr_scan_camera_permission), Toast.LENGTH_SHORT).show();
            finish();
        }
    }

    @Override
    protected void onDestroy() {
        super.onDestroy();
        cameraExecutor.shutdown();
    }
}
