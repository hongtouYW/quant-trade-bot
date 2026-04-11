package com.dj.user.fragment.affiliate;

import android.content.Context;
import android.content.Intent;
import android.graphics.Bitmap;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.text.Html;
import android.util.TypedValue;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.Toast;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;

import com.dj.user.R;
import com.dj.user.activity.mine.affiliate.AffiliateActivity;
import com.dj.user.activity.mine.affiliate.AffiliateEarnActivity;
import com.dj.user.databinding.FragmentAffiliateShareBinding;
import com.dj.user.fragment.BaseFragment;
import com.dj.user.model.request.RequestProfile;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Member;
import com.dj.user.model.response.ReferralTutorial;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.dj.user.util.ImageUtils;
import com.dj.user.util.SocialMediaUtil;
import com.dj.user.util.StringUtil;
import com.google.android.material.shape.CornerFamily;
import com.squareup.picasso.Callback;
import com.squareup.picasso.Picasso;

public class AffiliateShareFragment extends BaseFragment {

    private FragmentAffiliateShareBinding binding;
    private Context context;
    private Member member;
    private String referralCode;

    public AffiliateShareFragment newInstance(Context context) {
        AffiliateShareFragment fragment = new AffiliateShareFragment();
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
        binding = FragmentAffiliateShareBinding.inflate(inflater, container, false);
        context = getContext();
        member = CacheManager.getObject(context, CacheManager.KEY_USER_PROFILE, Member.class);

        setupUI();
        getDirectUpline();
        getReferralTutorial();
        return binding.getRoot();
    }

    @Override
    public void onResume() {
        super.onResume();
        getReferralCode();
    }

    private void setupUI() {
//        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
//            binding.textViewHtml.setText(Html.fromHtml(getString(R.string.commission_rules), Html.FROM_HTML_MODE_LEGACY));
//        } else {
//            binding.textViewHtml.setText(Html.fromHtml(getString(R.string.commission_rules)));
//        }
        String vipName = "img_affiliate_level_" + member.getVip();
        int resId = context.getResources().getIdentifier(vipName, "drawable", context.getPackageName());
        binding.imageViewVip.setImageResource(resId);
        binding.textViewHistory.setOnClickListener(view -> ((AffiliateActivity) context).switchTab(AffiliateActivity.PAGE_COMMISSION));
    }

    private void updateViewData(String directUplineID) {
        if (!isAdded()) {
            return;
        }
        if (StringUtil.isNullOrEmpty(directUplineID)) {
            binding.textViewUpline.setText(context.getString(R.string.placeholder_dash));
            return;
        }
        binding.textViewUpline.setText(directUplineID);
    }

    private void updateViewData(String link, String code) {
        if (!isAdded()) {
            return;
        }
        Bitmap bitmap = ImageUtils.generateQRCode(context, link);
        if (bitmap != null) {
            binding.imageViewQr.setImageBitmap(bitmap);
            float dp = 10f;
            float px = TypedValue.applyDimension(TypedValue.COMPLEX_UNIT_DIP, dp, context.getResources().getDisplayMetrics());
            binding.imageViewQr.setShapeAppearanceModel(
                    binding.imageViewQr.getShapeAppearanceModel()
                            .toBuilder()
                            .setTopLeftCorner(CornerFamily.ROUNDED, px)
                            .setTopRightCorner(CornerFamily.ROUNDED, px)
                            .setBottomLeftCorner(CornerFamily.ROUNDED, 0f)
                            .setBottomRightCorner(CornerFamily.ROUNDED, 0f)
                            .build()
            );
        }
        binding.panelSaveQr.setOnClickListener(view -> {
            Uri uri = ImageUtils.saveBitmap(context, bitmap, "expro99_invitation_qr");
            Toast.makeText(context, getString(R.string.affiliate_share_saved) + uri, Toast.LENGTH_SHORT).show();
        });

        binding.textViewLink.setText(link);
        binding.textViewLink.setSelected(true);
        binding.panelLink.setOnClickListener(view -> StringUtil.copyToClipboard(context, "", link));
        binding.textViewChange.setOnClickListener(v -> ((AffiliateActivity) context).startAppActivity(new Intent(context, AffiliateEarnActivity.class),
                null, false, false, true
        ));
        binding.textViewCode.setText(code);
        binding.panelCode.setOnClickListener(view -> StringUtil.copyToClipboard(context, "", code));

        binding.panelWechat.setOnClickListener(view -> SocialMediaUtil.shareToWeChat(context, bitmap, link));
        binding.panelWa.setOnClickListener(view -> SocialMediaUtil.shareToWhatsApp(context, bitmap, link));
        binding.panelFb.setOnClickListener(view -> SocialMediaUtil.shareToFacebook(context, bitmap, link));
        binding.panelTelegram.setOnClickListener(view -> SocialMediaUtil.shareToTelegram(context, bitmap, link));
    }

    private void updateViewData(ReferralTutorial referralTutorial) {
        if (!isAdded()) {
            return;
        }
        if (referralTutorial == null) {
            return;
        }
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
            binding.textViewCalculation.setText(Html.fromHtml(referralTutorial.getTitle(), Html.FROM_HTML_MODE_LEGACY));
            binding.textViewCalculation.setLineSpacing(0, 1.2f);
            binding.textViewSlogan.setText(Html.fromHtml(referralTutorial.getSlogan(), Html.FROM_HTML_MODE_LEGACY));
            binding.textViewHtml.setText(Html.fromHtml(referralTutorial.getDesc(), Html.FROM_HTML_MODE_LEGACY));
            binding.textViewHtml.setLineSpacing(0, 1.5f);
        } else {
            binding.textViewCalculation.setText(Html.fromHtml(referralTutorial.getTitle()));
            binding.textViewCalculation.setLineSpacing(0, 1.2f);
            binding.textViewSlogan.setText(Html.fromHtml(referralTutorial.getSlogan()));
            binding.textViewHtml.setText(Html.fromHtml(referralTutorial.getDesc()));
            binding.textViewHtml.setLineSpacing(0, 1.5f);
        }

        String pic = referralTutorial.getPicture();
        if (!StringUtil.isNullOrEmpty(pic)) {
            if (!pic.startsWith("http")) {
                pic = String.format(getString(R.string.template_s_s), getString(R.string.image_base_url), pic);
            }
            Picasso.get().load(pic).into(binding.imageViewTutorial, new Callback() {
                @Override
                public void onSuccess() {
                    if (!isAdded()) {
                        return;
                    }
                    binding.imageViewTutorial.setVisibility(View.VISIBLE);
                }

                @Override
                public void onError(Exception e) {
                    if (!isAdded()) {
                        return;
                    }
                    binding.imageViewTutorial.setVisibility(View.GONE);
                }
            });
        } else {
            binding.imageViewTutorial.setVisibility(View.GONE);
        }
    }

    private void getDirectUpline() {
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        ((AffiliateActivity) context).executeApiCall(context, apiService.getDirectUpline(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<String> response) {
                updateViewData(response.getData());
            }

            @Override
            public boolean onApiError(int code, String message) {
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                return false;
            }
        }, StringUtil.isNullOrEmpty(referralCode));
    }

    private void getReferralCode() {
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        ((AffiliateActivity) context).executeApiCall(context, apiService.getReferralCode(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                referralCode = response.getReferralCode();
                updateViewData(response.getQr(), response.getReferralCode());
            }

            @Override
            public boolean onApiError(int code, String message) {
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                return false;
            }
        }, StringUtil.isNullOrEmpty(referralCode));
    }

    private void getReferralTutorial() {
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        ((AffiliateActivity) context).executeApiCall(context, apiService.getReferralTutorial(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<ReferralTutorial> response) {
                updateViewData(response.getData());
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
    public void onDestroyView() {
        super.onDestroyView();
        binding = null;
    }
}