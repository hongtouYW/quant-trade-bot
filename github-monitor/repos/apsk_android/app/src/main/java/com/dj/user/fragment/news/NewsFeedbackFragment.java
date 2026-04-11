package com.dj.user.fragment.news;

import android.Manifest;
import android.app.Activity;
import android.content.Context;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.net.Uri;
import android.os.Bundle;
import android.provider.MediaStore;
import android.util.Log;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.LinearLayout;
import android.widget.TextView;
import android.widget.Toast;

import androidx.activity.result.ActivityResultLauncher;
import androidx.activity.result.contract.ActivityResultContracts;
import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.core.content.ContextCompat;
import androidx.core.content.FileProvider;

import com.dj.user.R;
import com.dj.user.activity.mine.FeedbackDetailsActivity;
import com.dj.user.activity.mine.NewsCentreActivity;
import com.dj.user.adapter.FeedbackListViewAdapter;
import com.dj.user.databinding.FragmentNewsFeedbackBinding;
import com.dj.user.fragment.BaseFragment;
import com.dj.user.model.request.RequestProfile;
import com.dj.user.model.request.RequestProfileGeneral;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Feedback;
import com.dj.user.model.response.FeedbackType;
import com.dj.user.model.response.Member;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.dj.user.util.FileUtil;
import com.dj.user.widget.AndroidBug5497Workaround;
import com.dj.user.widget.FeedbackTypeBottomSheetDialogFragment;
import com.dj.user.widget.MediaBottomSheetDialogFragment;
import com.dj.user.widget.RoundedEditTextView;
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

public class NewsFeedbackFragment extends BaseFragment {

    private FragmentNewsFeedbackBinding binding;
    private Context context;
    private Member member;
    private List<TextView> buttonList = new ArrayList<>();
    private LinearLayout dataPanel, noDataPanel, loadingPanel;
    private FeedbackListViewAdapter feedbackListViewAdapter;
    private Uri imageUri, selectedImageUri;
    private ArrayList<FeedbackType> feedbackTypeList;
    private FeedbackType selectedFeedbackType;
    private int selectedIndex = 0;

    public NewsFeedbackFragment newInstance(Context context) {
        NewsFeedbackFragment fragment = new NewsFeedbackFragment();
        fragment.context = context;
        return fragment;
    }

    @Override
    public void onAttach(@NonNull Context ctx) {
        super.onAttach(ctx);
        if (context == null) {
            context = ctx;
        }
    }

    @Override
    public View onCreateView(@NonNull LayoutInflater inflater, @Nullable ViewGroup container, @Nullable Bundle savedInstanceState) {
        binding = FragmentNewsFeedbackBinding.inflate(inflater, container, false);
        context = getContext();
        AndroidBug5497Workaround.assistActivity((NewsCentreActivity) context);
        member = CacheManager.getObject(context, CacheManager.KEY_USER_PROFILE, Member.class);

        setupUI();
        setupButtons();
        return binding.getRoot();
    }

    @Override
    public void onResume() {
        super.onResume();
        if (feedbackTypeList != null) return;
        getFeedbackTypeList();
    }

    private void setupUI() {
        dataPanel = binding.panelData;
        noDataPanel = binding.panelNoData.getRoot();
        loadingPanel = binding.panelLoading.getRoot();

        binding.imageViewDelete.setOnClickListener(view -> {
            binding.panelAttachment.setVisibility(View.GONE);
            binding.textViewFileName.setText("");
            selectedImageUri = null;
        });
    }

    private void setupButtons() {
        buttonList.add(binding.textViewNew);
        buttonList.add(binding.textViewMine);

        for (int i = 0; i < buttonList.size(); i++) {
            TextView textView = buttonList.get(i);
            int index = i;
            textView.setOnClickListener(v -> {
                selectedIndex = index;
                updateFilterStyles();
                if (selectedIndex == 0) {
                    setupFeedbackForm();
                } else {
                    setupFeedbackList();
                }
            });
        }
        selectedIndex = 0;
        updateFilterStyles();
        setupFeedbackForm();
    }

    private void updateFilterStyles() {
        for (int i = 0; i < buttonList.size(); i++) {
            TextView textView = buttonList.get(i);
            if (i == selectedIndex) {
                textView.setBackgroundResource(R.drawable.bg_filter_selected);
                textView.setTextColor(ContextCompat.getColor(context, R.color.black_000000));
            } else {
                textView.setBackgroundResource(R.drawable.bg_filter);
                textView.setTextColor(ContextCompat.getColor(context, R.color.orange_F8AF07));
            }
        }
    }

    private void setupFeedbackForm() {
        selectedIndex = 0;
        updateFilterStyles();

        binding.panelSubject.setOnClickListener(view ->
                FeedbackTypeBottomSheetDialogFragment.newInstance(getString(R.string.app_feedback_subject_prompt), feedbackTypeList, (feedbackType, pos) -> {
                    selectedFeedbackType = feedbackType;
                    binding.textViewSubject.setText(feedbackType.getTitle());
                    binding.textViewSubject.setTextColor(ContextCompat.getColor(context, R.color.white_FFFFFF));
                }).show(((NewsCentreActivity) context).getSupportFragmentManager(), "SubjectSheet")
        );
        binding.editTextFeedback.setInputFieldType(RoundedEditTextView.InputFieldType.MULTILINE_TEXT_LONG);
        binding.editTextFeedback.setMaxLength(1000);
        binding.editTextFeedback.setEditTextHeight(230);
        binding.editTextFeedback.setTextSize(14f);
        binding.editTextFeedback.setBackgroundTransparent(true);
        binding.editTextFeedback.setHint(getString(R.string.app_feedback_message_desc));
        binding.editTextFeedback.setOnTextCountChangeListener(count -> binding.textViewCount.setText(String.format(Locale.ENGLISH, "%d/1000", count)));

        binding.panelAddMedia.setOnClickListener(view -> showImageOptionDialog());
        binding.buttonSubmit.setOnClickListener(view -> sendFeedbackWithImage(selectedImageUri));

//        binding.imageViewDelete.setOnClickListener(view -> {
//            binding.panelAttachment.setVisibility(View.GONE);
//            binding.textViewFileName.setText("");
//            selectedImageUri = null;
//        });

        binding.scrollViewFeedback.setVisibility(View.VISIBLE);
        binding.panelList.setVisibility(View.GONE);
    }

    private void setupFeedbackList() {
        selectedIndex = 1;
        updateFilterStyles();

        feedbackListViewAdapter = new FeedbackListViewAdapter(context);
        binding.listViewFeedback.setAdapter(feedbackListViewAdapter);
        feedbackListViewAdapter.setOnItemClickListener((position, object) -> {
            Bundle bundle = new Bundle();
            bundle.putString("data", new Gson().toJson(object));
            ((NewsCentreActivity) context).startAppActivity(new Intent(context, FeedbackDetailsActivity.class),
                    bundle, false, false, true);
        });

        binding.scrollViewFeedback.setVisibility(View.GONE);
        binding.panelList.setVisibility(View.VISIBLE);
        getFeedbackList();
    }

    private void resetForm() {
        selectedFeedbackType = null;
        binding.textViewSubject.setText(getString(R.string.app_feedback_subject_label));
        binding.textViewSubject.setTextColor(ContextCompat.getColor(context, R.color.yellow_FFFC86_50a));
        binding.editTextFeedback.setText("");
        binding.panelAttachment.setVisibility(View.GONE);
        binding.textViewFileName.setText("");
        selectedImageUri = null;
    }

    private String getFileProviderAuthority() {
        return context.getApplicationContext().getPackageName() + ".provider";
    }

    private void showImageOptionDialog() {
        MediaBottomSheetDialogFragment.newInstance(new MediaBottomSheetDialogFragment.OnMediaOptionClickListener() {
            @Override
            public void onTakePhoto() {
                if (ContextCompat.checkSelfPermission(context, Manifest.permission.CAMERA)
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
                    if (ContextCompat.checkSelfPermission(context, Manifest.permission.READ_EXTERNAL_STORAGE)
                            != PackageManager.PERMISSION_GRANTED) {
                        galleryPermissionLauncher.launch(Manifest.permission.READ_EXTERNAL_STORAGE);
                    } else {
                        openGallery();
                    }
                }
            }
        }).show(getChildFragmentManager(), "MediaBottomSheet");
    }

    private void handleSelectedImage(Uri uri) {
        selectedImageUri = uri;
        String fileName = FileUtil.getFileName(context, uri);
        long fileSize = FileUtil.getFileSize(context, uri);
        String displaySize;
        if (fileSize >= 1024 * 1024) {
            displaySize = String.format(Locale.ENGLISH, getString(R.string.app_feedback_image_size_mb), fileSize / (1024.0 * 1024.0));
        } else {
            displaySize = String.format(Locale.ENGLISH, getString(R.string.app_feedback_image_size_kb), fileSize / 1024.0);
        }
        binding.panelAttachment.setVisibility(View.VISIBLE);
        binding.textViewFileName.setText(String.format(Locale.ENGLISH, getString(R.string.template_s_bracket_s), fileName, displaySize));
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
            imageUri = FileProvider.getUriForFile(context, getFileProviderAuthority(), photoFile);
            intent.putExtra(MediaStore.EXTRA_OUTPUT, imageUri);
            takePhotoLauncher.launch(intent);
        } catch (IOException e) {
            Log.e("###", "takePhoto: " + e);
            Toast.makeText(context, R.string.app_feedback_image_create_failed, Toast.LENGTH_SHORT).show();
        }
    }

    private File createImageFile() throws IOException {
        String timeStamp = new SimpleDateFormat("yyyyMMdd_HHmmss", Locale.ENGLISH).format(new Date());
        String imageFileName = "JPEG_" + timeStamp + "_";
        File storageDir = context.getExternalFilesDir(null);
        return File.createTempFile(imageFileName, ".jpg", storageDir);
    }

    public void sendFeedbackWithImage(@Nullable Uri imageUri) {
        String feedback = binding.editTextFeedback.getText();
        if (selectedFeedbackType == null && feedback.isEmpty()) {
            ((NewsCentreActivity) context).showError(context, binding.panelSubject);
            binding.editTextFeedback.showError("");
            return;
        }
        if (selectedFeedbackType == null) {
            ((NewsCentreActivity) context).showError(context, binding.panelSubject);
            return;
        }
        if (feedback.isEmpty()) {
            binding.editTextFeedback.showError("");
            return;
        }
        if (imageUri == null) {
            ((NewsCentreActivity) context).showCustomConfirmationDialog(
                    context,
                    context.getString(R.string.app_feedback_details_title),
                    context.getString(R.string.app_feedback_upload_desc),
                    null, null,
                    context.getString(R.string.app_feedback_upload_okay),
                    null
            );
            return;
        }

        RequestBody memberId = RequestBody.create(member.getMember_id(), MediaType.parse("text/plain"));
        RequestBody feedbackTypeId = RequestBody.create(selectedFeedbackType.getFeedbacktype_id(), MediaType.parse("text/plain"));
        RequestBody desc = RequestBody.create(feedback, MediaType.parse("text/plain"));

        MultipartBody.Part photoPart = null;
        try {
            File compressedFile = FileUtil.compressImageToUnder2MB(context, imageUri);
            if (compressedFile != null) {
                RequestBody requestFile = RequestBody.create(compressedFile, MediaType.parse("image/jpeg"));
                photoPart = MultipartBody.Part.createFormData("photo", compressedFile.getName(), requestFile);
            } else {
                Toast.makeText(context, R.string.app_feedback_image_compress_failed, Toast.LENGTH_SHORT).show();
                return;
            }
        } catch (IOException e) {
            Log.e("###", "sendFeedbackWithImage: " + e);
            Toast.makeText(context, R.string.app_feedback_image_process_failed, Toast.LENGTH_SHORT).show();
            return;
        }

        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        Call<BaseResponse<Feedback>> call = apiService.sendFeedback(memberId, feedbackTypeId, desc, photoPart);
        ((NewsCentreActivity) context).executeApiCall(context, call, new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Feedback> result) {
                setupFeedbackList();
                resetForm();
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

    private void getFeedbackTypeList() {
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        ((NewsCentreActivity) context).executeApiCall(context, apiService.getFeedbackTypeList(request), new ApiCallback<>() {
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

    private void getFeedbackList() {
        dataPanel.setVisibility(View.GONE);
        noDataPanel.setVisibility(View.GONE);
        loadingPanel.setVisibility(View.VISIBLE);

        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestProfileGeneral request = new RequestProfileGeneral(member.getMember_id());
        ((NewsCentreActivity) context).executeApiCall(context, apiService.getFeedbackList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<Feedback>> response) {
                List<Feedback> feedbackList = response.getData();
                if (feedbackList != null && !feedbackList.isEmpty()) {
                    feedbackListViewAdapter.replaceList(feedbackList);
                    dataPanel.setVisibility(View.VISIBLE);
                    noDataPanel.setVisibility(View.GONE);
                    loadingPanel.setVisibility(View.GONE);

                } else {
                    dataPanel.setVisibility(View.GONE);
                    noDataPanel.setVisibility(View.VISIBLE);
                    loadingPanel.setVisibility(View.GONE);
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
                    Toast.makeText(context, R.string.app_feedback_camera_permission, Toast.LENGTH_SHORT).show();
                }
            });

    final ActivityResultLauncher<String> galleryPermissionLauncher =
            registerForActivityResult(new ActivityResultContracts.RequestPermission(), isGranted -> {
                if (isGranted) {
                    openGallery();
                } else {
                    Toast.makeText(context, R.string.app_feedback_gallery_permission, Toast.LENGTH_SHORT).show();
                }
            });

    @Override
    public void onDestroyView() {
        super.onDestroyView();
        binding = null;
    }
}