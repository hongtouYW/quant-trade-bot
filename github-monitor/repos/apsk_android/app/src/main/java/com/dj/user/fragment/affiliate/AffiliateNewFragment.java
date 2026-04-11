package com.dj.user.fragment.affiliate;

import android.content.Context;
import android.os.Bundle;
import android.text.Editable;
import android.text.TextWatcher;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.EditText;
import android.widget.Toast;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;

import com.dj.user.R;
import com.dj.user.activity.mine.affiliate.AffiliateActivity;
import com.dj.user.databinding.FragmentAffiliateNewBinding;
import com.dj.user.databinding.ViewEditTextPasswordBinding;
import com.dj.user.fragment.BaseFragment;
import com.dj.user.model.request.RequestDownlineNew;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Member;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.dj.user.util.StringUtil;
import com.dj.user.widget.CustomConfirmationDialog;
import com.dj.user.widget.CustomToast;

public class AffiliateNewFragment extends BaseFragment {

    private FragmentAffiliateNewBinding binding;
    private ViewEditTextPasswordBinding viewEditTextPasswordBinding;
    private Context context;
    private Member member;
    private EditText passwordField;

    public AffiliateNewFragment newInstance(Context context) {
        AffiliateNewFragment fragment = new AffiliateNewFragment();
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
        binding = FragmentAffiliateNewBinding.inflate(inflater, container, false);
        context = getContext();
        member = CacheManager.getObject(context, CacheManager.KEY_USER_PROFILE, Member.class);

        binding.editTextId.setHint(getString(R.string.affiliate_new_id));
        binding.editTextId.setBackgroundTransparent(true);
        EditText editText = binding.editTextId.getEditText();
        editText.setOnFocusChangeListener((v, hasFocus) -> {
            if (hasFocus) {
                // If empty, add prefix "60"
                if (editText.getText().toString().isEmpty()) {
                    editText.setText("60");
                    editText.setSelection(2);
                }
            } else {
                // Lost focus → if only "60", remove it and show hint
                if (editText.getText().toString().equals("60")) {
                    editText.setText("");
                }
            }
        });

        editText.addTextChangedListener(new TextWatcher() {
            boolean editing = false;

            @Override
            public void beforeTextChanged(CharSequence s, int start, int count, int after) {
            }

            @Override
            public void onTextChanged(CharSequence s, int start, int before, int count) {
                if (editing) return;
                editing = true;

                String text = s.toString();

                // Prevent deleting prefix when field is focused
                if (editText.hasFocus()) {
                    if (!text.startsWith("60")) {
                        editText.setText("60");
                        editText.setSelection(2);
                    }
                }

                editing = false;
            }

            @Override
            public void afterTextChanged(Editable s) {
            }
        });

        viewEditTextPasswordBinding = ViewEditTextPasswordBinding.bind(binding.viewEditTextPassword.getRoot());
        passwordField = viewEditTextPasswordBinding.editTextPassword;
        passwordField.setHint(R.string.affiliate_new_password);

        viewEditTextPasswordBinding.panelPassword.setBackgroundResource(R.drawable.bg_edit_text_transparent);
        viewEditTextPasswordBinding.imageViewPasswordToggle.setOnClickListener(v ->
                ((AffiliateActivity) context).togglePasswordVisibility(passwordField, viewEditTextPasswordBinding.imageViewPasswordToggle)
        );
        binding.buttonNext.setOnClickListener(view -> createNewDownline());
        return binding.getRoot();
    }

    private void createNewDownline() {
        binding.editTextId.clearError();
        ((AffiliateActivity) context).clearErrorTransparent(context, viewEditTextPasswordBinding.panelPassword);

        String login = binding.editTextId.getText();
        String password = passwordField.getText().toString().trim();
        boolean hasError = false;
        if (login.isEmpty() && password.isEmpty()) {
            binding.editTextId.showError("");
            ((AffiliateActivity) context).showErrorTransparent(context, viewEditTextPasswordBinding.panelPassword);
            hasError = true;
        }
        if (login.isEmpty() || !StringUtil.isValidPhone(login)) {
            binding.editTextId.showError("");
            Toast.makeText(context, R.string.affiliate_new_phone_invalid, Toast.LENGTH_SHORT).show();
            hasError = true;
        }
        if (!StringUtil.isValidAlphanumeric(password)) {
            ((AffiliateActivity) context).showErrorTransparent(context, viewEditTextPasswordBinding.panelPassword);
            Toast.makeText(context, R.string.affiliate_new_password_invalid, Toast.LENGTH_SHORT).show();
            hasError = true;
        }
        if (hasError) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestDownlineNew request = new RequestDownlineNew(member.getMember_id(), login, password);
        ((AffiliateActivity) context).executeApiCall(context, apiService.createNewDownline(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Member> response) {
                binding.editTextId.setText("");
                binding.viewEditTextPassword.editTextPassword.setText("");
                CustomToast.showTopToast(context, getString(R.string.affiliate_new_success));
                ((AffiliateActivity) context).switchTab(AffiliateActivity.PAGE_DOWNLINE);
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