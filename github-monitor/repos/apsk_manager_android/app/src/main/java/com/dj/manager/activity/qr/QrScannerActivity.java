package com.dj.manager.activity.qr;

import android.Manifest;
import android.annotation.SuppressLint;
import android.app.Activity;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.graphics.Bitmap;
import android.net.Uri;
import android.os.Bundle;
import android.provider.MediaStore;
import android.util.Log;
import android.widget.Toast;

import androidx.activity.result.ActivityResultLauncher;
import androidx.activity.result.contract.ActivityResultContracts;
import androidx.annotation.NonNull;
import androidx.camera.core.Camera;
import androidx.camera.core.CameraSelector;
import androidx.camera.core.ImageAnalysis;
import androidx.camera.core.ImageProxy;
import androidx.camera.core.Preview;
import androidx.camera.lifecycle.ProcessCameraProvider;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;

import com.dj.manager.R;
import com.dj.manager.activity.BaseActivity;
import com.dj.manager.databinding.ActivityQrScannerBinding;
import com.dj.manager.databinding.ViewToolbarBinding;
import com.google.common.util.concurrent.ListenableFuture;
import com.google.zxing.BinaryBitmap;
import com.google.zxing.MultiFormatReader;
import com.google.zxing.NotFoundException;
import com.google.zxing.PlanarYUVLuminanceSource;
import com.google.zxing.RGBLuminanceSource;
import com.google.zxing.Result;
import com.google.zxing.common.HybridBinarizer;

import java.io.IOException;
import java.nio.ByteBuffer;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

public class QrScannerActivity extends BaseActivity {

    private ActivityQrScannerBinding binding;

    private boolean hasScanned = false;
    private boolean isFlashOn = false;

    private Camera camera;
    private ExecutorService cameraExecutor;

    final ActivityResultLauncher<String> galleryPermissionLauncher =
            registerForActivityResult(new ActivityResultContracts.RequestPermission(), isGranted -> {
                if (isGranted) {
                    openGallery();
                } else {
                    Toast.makeText(this, R.string.qr_scan_gallery_permission, Toast.LENGTH_SHORT).show();
                }
            });

    final ActivityResultLauncher<Intent> pickImageLauncher =
            registerForActivityResult(new ActivityResultContracts.StartActivityForResult(), result -> {
                if (result.getResultCode() == Activity.RESULT_OK && result.getData() != null) {
                    Uri selectedImage = result.getData().getData();
                    handleSelectedImage(selectedImage);
                }
            });

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityQrScannerBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        ViewToolbarBinding toolbarBinding = ViewToolbarBinding.bind(binding.toolbar.getRoot());
        toolbarBinding.getRoot().setBackgroundColor(ContextCompat.getColor(this, R.color.black_141718));
        setupToolbar(binding.toolbar.getRoot(), getString(R.string.qr_scan_title), 0, null);

        binding.imageViewQr.setOnClickListener(v -> startAppActivity(
                new Intent(QrScannerActivity.this, FullScreenImageActivity.class),
                null, false, false, false, true
        ));
        binding.imageViewFlash.setOnClickListener(v -> {
            if (camera != null) {
                isFlashOn = !isFlashOn;
                camera.getCameraControl().enableTorch(isFlashOn);
                binding.imageViewFlash.setImageResource(isFlashOn ? R.drawable.ic_flash_on : R.drawable.ic_flash);
            }
        });
        binding.imageViewGallery.setOnClickListener(v -> {
            if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.TIRAMISU) {
                galleryPermissionLauncher.launch(Manifest.permission.READ_MEDIA_IMAGES);
            } else {
                if (ContextCompat.checkSelfPermission(QrScannerActivity.this, Manifest.permission.READ_EXTERNAL_STORAGE)
                        != PackageManager.PERMISSION_GRANTED) {
                    galleryPermissionLauncher.launch(Manifest.permission.READ_EXTERNAL_STORAGE);
                } else {
                    openGallery();
                }
            }
        });

        cameraExecutor = Executors.newSingleThreadExecutor();
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED) {
            startCamera();
        } else {
            ActivityCompat.requestPermissions(this, new String[]{Manifest.permission.CAMERA}, 100);
        }
        startCamera();
    }

    void openGallery() {
        Intent intent = new Intent(Intent.ACTION_PICK, MediaStore.Images.Media.EXTERNAL_CONTENT_URI);
        intent.setType("image/*");
        pickImageLauncher.launch(intent);
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
                Log.e("### startCamera", e.toString());
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

    private void handleSelectedImage(Uri imageUri) {
        if (imageUri == null) return;
        try {
            Bitmap bitmap = MediaStore.Images.Media.getBitmap(getContentResolver(), imageUri);

            int width = bitmap.getWidth();
            int height = bitmap.getHeight();
            int[] pixels = new int[width * height];
            bitmap.getPixels(pixels, 0, width, 0, 0, width, height);

            RGBLuminanceSource source = new RGBLuminanceSource(width, height, pixels);
            BinaryBitmap binaryBitmap = new BinaryBitmap(new HybridBinarizer(source));

            Result result = new MultiFormatReader().decode(binaryBitmap);

            if (result != null) {
                handleQrResult(result.getText());
            } else {
                Toast.makeText(this, R.string.qr_scan_no_code, Toast.LENGTH_SHORT).show();
            }
        } catch (NotFoundException e) {
            Toast.makeText(this, R.string.qr_scan_no_code, Toast.LENGTH_SHORT).show();
        } catch (IOException e) {
            Toast.makeText(this, R.string.qr_scan_failed_to_load, Toast.LENGTH_SHORT).show();
        }
    }

    private void handleQrResult(String qrContent) {
        Log.d("### QR", "Scanned: " + qrContent);
        Toast.makeText(this, qrContent, Toast.LENGTH_SHORT).show();

        // Delay finish to let user see the result
        // TODO: 21/10/2025
//        Intent resultIntent = new Intent();
//        resultIntent.putExtra("data", qrContent);
//        setResult(RESULT_OK, resultIntent);
//        binding.previewView.postDelayed(this::finish, 1500);
    }

    @Override
    public void onRequestPermissionsResult(int requestCode,
                                           @NonNull String[] permissions, @NonNull int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);
        if (requestCode == 100 && grantResults.length > 0 &&
                grantResults[0] == PackageManager.PERMISSION_GRANTED) {
            startCamera();
        } else {
            Toast.makeText(this, R.string.qr_scan_camera_permission, Toast.LENGTH_SHORT).show();
            finish();
        }
    }

    @Override
    protected void onDestroy() {
        super.onDestroy();
        cameraExecutor.shutdown();
    }
}
