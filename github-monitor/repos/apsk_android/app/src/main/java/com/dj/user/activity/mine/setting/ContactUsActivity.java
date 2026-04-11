package com.dj.user.activity.mine.setting;

import android.graphics.Bitmap;
import android.graphics.Canvas;
import android.net.Uri;
import android.os.Bundle;
import android.util.Log;
import android.view.LayoutInflater;
import android.view.View;
import android.widget.ImageView;
import android.widget.TextView;
import android.widget.Toast;

import com.dj.user.R;
import com.dj.user.activity.BaseActivity;
import com.dj.user.databinding.ActivityContactUsBinding;
import com.dj.user.model.request.RequestProfile;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.FindUs;
import com.dj.user.model.response.Member;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.dj.user.util.ImageUtils;
import com.dj.user.util.StringUtil;

import java.util.List;

public class ContactUsActivity extends BaseActivity {

    private ActivityContactUsBinding binding;
    private Member member;
    private FindUs findUs;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityContactUsBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        member = CacheManager.getObject(this, CacheManager.KEY_USER_PROFILE, Member.class);

        setupToolbar(binding.toolbar.getRoot(), getString(R.string.find_us_title), 0, null);
        getContactUs();
    }

    private void setupViewData() {
        if (findUs == null) {
            return;
        }
        StringBuilder domains = new StringBuilder();
        if (findUs.getDomains() != null) {
            List<String> findUsDomains = findUs.getDomains();
            for (int i = 0; i < findUsDomains.size(); i++) {
                String domain = findUsDomains.get(i);
                domains.append(domain);
                if (i != findUsDomains.size() - 1) {
                    domains.append("\n");
                }
            }
            if (domains.toString().isEmpty()) {
                domains = new StringBuilder("-");
            }
        }
        binding.textViewDomains.setText(domains.toString());
        if (findUs.getHomes() != null) {
            List<String> homes = findUs.getHomes();
            binding.panelHomeWebsite.removeAllViews();
            for (int i = 0; i < homes.size(); i++) {
                String home = homes.get(i);
                View homeWebsiteView = LayoutInflater.from(this).inflate(R.layout.item_find_us_web, binding.panelHomeWebsite, false);
                TextView webLabelTextView = homeWebsiteView.findViewById(R.id.textView_label);
                TextView webTextView = homeWebsiteView.findViewById(R.id.textView_web);
                ImageView copyImageView = homeWebsiteView.findViewById(R.id.imageView_copy_web);
                webLabelTextView.setText(String.format(getString(R.string.find_us_web_template), i + 1));
                webTextView.setText(home);
                webTextView.setSelected(true);
                copyImageView.setOnClickListener(v -> StringUtil.copyToClipboard(this, "", home));
                binding.panelHomeWebsite.addView(homeWebsiteView);
            }
        }
        binding.buttonSaveAddress.setOnClickListener(view -> captureAndSavePage());

        binding.textViewEmail.setText(!StringUtil.isNullOrEmpty(findUs.getEmail()) ? findUs.getEmail() : "-");
        binding.imageViewCopyEmail.setOnClickListener(view -> StringUtil.copyToClipboard(this, "", binding.textViewEmail.getText().toString()));
        binding.buttonSaveEmail.setOnClickListener(view -> captureAndSavePage());
    }

    private void captureAndSavePage() {
        try {
            View view = binding.scrollView;
            Bitmap bitmap = Bitmap.createBitmap(view.getWidth(), view.getHeight(), Bitmap.Config.ARGB_8888);
            Canvas canvas = new Canvas(bitmap);
            view.draw(canvas);

            String fileName = "contact_us_" + System.currentTimeMillis();
            Uri uri = ImageUtils.saveBitmap(this, bitmap, fileName);
            if (uri != null) {
                Toast.makeText(this, "Saved to gallery: " + uri.getPath(), Toast.LENGTH_LONG).show();
            } else {
                Toast.makeText(this, "Failed to save image", Toast.LENGTH_SHORT).show();
            }
        } catch (Exception e) {
            Log.e("###", "captureAndSavePage: ", e);
            Toast.makeText(this, "Error capturing page", Toast.LENGTH_SHORT).show();
        }
    }

    private void getContactUs() {
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        executeApiCall(this, apiService.getContactUs(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<FindUs> response) {
                findUs = response.getData();
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
        }, true);
    }

}