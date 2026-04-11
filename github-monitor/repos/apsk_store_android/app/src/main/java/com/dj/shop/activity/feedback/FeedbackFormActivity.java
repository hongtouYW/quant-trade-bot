package com.dj.shop.activity.feedback;

import android.Manifest;
import android.app.Activity;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.net.Uri;
import android.os.Bundle;
import android.provider.MediaStore;
import android.util.Log;
import android.view.View;
import android.widget.Toast;

import androidx.activity.result.ActivityResultLauncher;
import androidx.activity.result.contract.ActivityResultContracts;
import androidx.annotation.Nullable;
import androidx.core.content.ContextCompat;
import androidx.core.content.FileProvider;

import com.dj.shop.R;
import com.dj.shop.activity.BaseActivity;
import com.dj.shop.activity.success.SuccessActivity;
import com.dj.shop.databinding.ActivityFeedbackFormBinding;
import com.dj.shop.model.SuccessConfigFactory;
import com.dj.shop.model.request.RequestProfile;
import com.dj.shop.model.response.BaseResponse;
import com.dj.shop.model.response.FeedbackType;
import com.dj.shop.model.response.Shop;
import com.dj.shop.util.ApiCallback;
import com.dj.shop.util.ApiClient;
import com.dj.shop.util.ApiService;
import com.dj.shop.util.CacheManager;
import com.dj.shop.util.FileUtil;
import com.dj.shop.widget.AndroidBug5497Workaround;
import com.dj.shop.widget.FeedbackTypeBottomSheetDialogFragment;
import com.dj.shop.widget.MediaBottomSheetDialogFragment;
import com.dj.shop.widget.RoundedEditTextView;
import com.google.gson.Gson;

import java.io.File;
import java.io.IOException;
import java.text.SimpleDateFormat;
import java.util.ArrayList;
import java.util.Date;
import java.util.List;
import java.util.Locale;

import okhttp3.MediaType;
import okhttp3.MultipartBody;
import okhttp3.RequestBody;
import retrofit2.Call;

public class FeedbackFormActivity extends BaseActivity {
    private ActivityFeedbackFormBinding binding;
    private Shop shop;
    private Uri imageUri, selectedImageUri;
    private FeedbackType selectedFeedbackType;
    private ArrayList<FeedbackType> feedbackTypeList;

    @Override
    protected void onCreate(@Nullable Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityFeedbackFormBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        AndroidBug5497Workaround.assistActivity(this);
        shop = CacheManager.getObject(this, CacheManager.KEY_SHOP_PROFILE, Shop.class);

        setupToolbar(binding.toolbar.getRoot(), getString(R.string.profile_feedback_title), 0, null);
        setupUI();
    }

    @Override
    protected void onResume() {
        super.onResume();
        if (feedbackTypeList != null) return;
        getFeedbackTypeList();
    }

    private void setupUI() {
//        ArrayList<Subject> subjectList = new ArrayList<>();
//        if (subjectList.isEmpty()) {
//            subjectList.add(new Subject(getString(R.string.profile_feedback_subject_manager), getString(R.string.profile_feedback_subject_manager_desc)));
//            subjectList.add(new Subject(getString(R.string.profile_feedback_subject_app), getString(R.string.profile_feedback_subject_app_desc)));
//            subjectList.add(new Subject(getString(R.string.profile_feedback_subject_other), getString(R.string.profile_feedback_subject_other_desc)));
//        }
        binding.panelSubject.setOnClickListener(view ->
                FeedbackTypeBottomSheetDialogFragment.newInstance(getString(R.string.profile_feedback_subject_title), feedbackTypeList, (feedbackType, pos) -> {
                    clearError(this, binding.panelSubject);
                    selectedFeedbackType = feedbackType;
                    binding.textViewSubject.setText(feedbackType.getTitle());
                    binding.textViewSubject.setTextColor(ContextCompat.getColor(this, R.color.white_FFFFFF));
                }).show(getSupportFragmentManager(), "SubjectBottomSheet")
        );

        binding.editTextFeedback.setInputFieldType(RoundedEditTextView.InputFieldType.MULTILINE_TEXT_LONG);
        binding.editTextFeedback.setHint(getString(R.string.profile_feedback_hint_content));
        binding.editTextFeedback.setMaxLength(1000);
        binding.editTextFeedback.setOnTextCountChangeListener(count -> binding.textViewCount.setText(String.format(Locale.ENGLISH, getString(R.string.profile_feedback_content_count_template), count)));

        binding.buttonUpload.setTextColorRes(R.color.gray_C2C3CB);
        binding.buttonUpload.setOnClickListener(view -> showImageOptionDialog());

        binding.imageViewDelete.setOnClickListener(view -> {
            binding.panelAttachment.setVisibility(View.GONE);
            binding.textViewFileName.setText("");
            selectedImageUri = null;
        });
        binding.buttonSubmit.setOnClickListener(view -> sendFeedbackWithImage(selectedImageUri));
    }

    private void getFeedbackTypeList() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestProfile request = new RequestProfile(shop.getShop_id());
        executeApiCall(this, apiService.getFeedbackTypeList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<FeedbackType>> response) {
                feedbackTypeList = new ArrayList<>(response.getData());
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

    public void sendFeedbackWithImage(@Nullable Uri imageUri) {
        String feedback = binding.editTextFeedback.getText();
        if (selectedFeedbackType == null && feedback.isEmpty()) {
            showError(this, binding.panelSubject);
            binding.editTextFeedback.showError("");
            return;
        }
        if (selectedFeedbackType == null) {
            showError(this, binding.panelSubject);
            return;
        }
        if (feedback.isEmpty()) {
            binding.editTextFeedback.showError("");
            return;
        }
        if (imageUri == null) {
            showCustomConfirmationDialog(
                    this,
                    getString(R.string.profile_feedback_title),
                    getString(R.string.profile_feedback_upload_desc),
                    getString(R.string.profile_feedback_upload_okay),
                    null
            );
            return;
        }

        RequestBody shopId = RequestBody.create(shop.getShop_id(), MediaType.parse("text/plain"));
        RequestBody feedbackTypeId = RequestBody.create(selectedFeedbackType.getFeedbacktype_id(), MediaType.parse("text/plain"));
        RequestBody desc = RequestBody.create(feedback, MediaType.parse("text/plain"));

        MultipartBody.Part photoPart = null;
        if (imageUri != null) {
            try {
                File compressedFile = FileUtil.compressImageToUnder2MB(this, imageUri);
                if (compressedFile != null) {
                    RequestBody requestFile = RequestBody.create(compressedFile, MediaType.parse("image/jpeg"));
                    photoPart = MultipartBody.Part.createFormData("photo", compressedFile.getName(), requestFile);
                } else {
                    Toast.makeText(this, R.string.profile_feedback_image_compress_failed, Toast.LENGTH_SHORT).show();
                    return;
                }
            } catch (IOException e) {
                Log.e("###", "sendFeedbackWithImage: " + e);
                Toast.makeText(this, R.string.profile_feedback_image_process_failed, Toast.LENGTH_SHORT).show();
                return;
            }
        }

        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        Call<BaseResponse<Void>> call = apiService.sendFeedback(shopId, feedbackTypeId, desc, photoPart);
        executeApiCall(this, call, new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> result) {
                Bundle bundle = new Bundle();
                bundle.putString("data", new Gson().toJson(SuccessConfigFactory.createFeedbackSuccess(FeedbackFormActivity.this)));
                startAppActivity(new Intent(FeedbackFormActivity.this, SuccessActivity.class),
                        bundle, true, false, true);
            }

            @Override
            public boolean onApiError(int statusCode, String errorMessage) {
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                return false;
            }
        }, true);
    }

    private String getFileProviderAuthority() {
        return getApplicationContext().getPackageName() + ".provider";
    }

    private void showImageOptionDialog() {
        MediaBottomSheetDialogFragment.newInstance(new MediaBottomSheetDialogFragment.OnMediaOptionClickListener() {
            @Override
            public void onTakePhoto() {
                if (ContextCompat.checkSelfPermission(FeedbackFormActivity.this, Manifest.permission.CAMERA)
                        != PackageManager.PERMISSION_GRANTED) {
                    cameraPermissionLauncher.launch(Manifest.permission.CAMERA);
                } else {
                    takePhoto();
                }
            }

            @Override
            public void onChooseGallery() {
                if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.TIRAMISU) {
                    galleryPermissionLauncher.launch(Manifest.permission.READ_MEDIA_IMAGES);
                } else {
                    if (ContextCompat.checkSelfPermission(FeedbackFormActivity.this, Manifest.permission.READ_EXTERNAL_STORAGE)
                            != PackageManager.PERMISSION_GRANTED) {
                        galleryPermissionLauncher.launch(Manifest.permission.READ_EXTERNAL_STORAGE);
                    } else {
                        openGallery();
                    }
                }
            }
        }).show(getSupportFragmentManager(), "MediaBottomSheet");
    }

    private void handleSelectedImage(Uri uri) {
        selectedImageUri = uri;
        String fileName = FileUtil.getFileName(this, uri);
        long fileSize = FileUtil.getFileSize(this, uri);
        String displaySize;
        if (fileSize >= 1024 * 1024) {
            displaySize = String.format(Locale.ENGLISH, getString(R.string.profile_feedback_image_size_mb), fileSize / (1024.0 * 1024.0));
        } else {
            displaySize = String.format(Locale.ENGLISH, getString(R.string.profile_feedback_image_size_kb), fileSize / 1024.0);
        }
        binding.panelAttachment.setVisibility(View.VISIBLE);
        binding.textViewFileName.setText(String.format(Locale.ENGLISH, getString(R.string.template_s_bracket_s), fileName, displaySize));
        binding.textViewFileName.setSelected(true);
    }

    void openGallery() {
        Intent intent = new Intent(Intent.ACTION_PICK, MediaStore.Images.Media.EXTERNAL_CONTENT_URI);
        intent.setType("image/*");
        pickImageLauncher.launch(intent);
    }

    void takePhoto() {
        Intent intent = new Intent(MediaStore.ACTION_IMAGE_CAPTURE);
        try {
            File photoFile = createImageFile();
            imageUri = FileProvider.getUriForFile(this, getFileProviderAuthority(), photoFile);
            intent.putExtra(MediaStore.EXTRA_OUTPUT, imageUri);
            takePhotoLauncher.launch(intent);
        } catch (IOException e) {
            Log.e("###", "takePhoto: " + e);
            Toast.makeText(this, R.string.profile_feedback_image_create_failed, Toast.LENGTH_SHORT).show();
        }
    }

    private File createImageFile() throws IOException {
        String timeStamp = new SimpleDateFormat("yyyyMMdd_HHmmss", Locale.ENGLISH).format(new Date());
        String imageFileName = "JPEG_" + timeStamp + "_";
        File storageDir = getExternalFilesDir(null);
        return File.createTempFile(imageFileName, ".jpg", storageDir);
    }

    final ActivityResultLauncher<Intent> pickImageLauncher =
            registerForActivityResult(new ActivityResultContracts.StartActivityForResult(), result -> {
                if (result.getResultCode() == Activity.RESULT_OK && result.getData() != null) {
                    Uri selectedImage = result.getData().getData();
                    handleSelectedImage(selectedImage);
                }
            });

    final ActivityResultLauncher<Intent> takePhotoLauncher =
            registerForActivityResult(new ActivityResultContracts.StartActivityForResult(), result -> {
                if (result.getResultCode() == Activity.RESULT_OK) {
                    handleSelectedImage(imageUri);
                }
            });
    final ActivityResultLauncher<String> cameraPermissionLauncher =
            registerForActivityResult(new ActivityResultContracts.RequestPermission(), isGranted -> {
                if (isGranted) {
                    takePhoto();
                } else {
                    Toast.makeText(this, R.string.profile_feedback_camera_permission, Toast.LENGTH_SHORT).show();
                }
            });

    final ActivityResultLauncher<String> galleryPermissionLauncher =
            registerForActivityResult(new ActivityResultContracts.RequestPermission(), isGranted -> {
                if (isGranted) {
                    openGallery();
                } else {
                    Toast.makeText(this, R.string.profile_feedback_gallery_permission, Toast.LENGTH_SHORT).show();
                }
            });
}