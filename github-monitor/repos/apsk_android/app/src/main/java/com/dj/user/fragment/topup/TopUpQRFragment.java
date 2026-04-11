package com.dj.user.fragment.topup;

import android.content.Context;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;

import com.dj.user.databinding.FragmentTopUpQrBinding;
import com.dj.user.fragment.BaseFragment;

public class TopUpQRFragment extends BaseFragment {

    private FragmentTopUpQrBinding binding;
    private Context context;

    public TopUpQRFragment newInstance(Context context) {
        TopUpQRFragment fragment = new TopUpQRFragment();
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
        binding = FragmentTopUpQrBinding.inflate(inflater, container, false);
        context = getContext();
        return binding.getRoot();
    }

    @Override
    public void onDestroyView() {
        super.onDestroyView();
        binding = null;
    }
}
