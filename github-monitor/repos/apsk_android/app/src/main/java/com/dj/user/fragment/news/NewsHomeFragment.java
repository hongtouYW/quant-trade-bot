package com.dj.user.fragment.news;

import android.content.Context;
import android.content.Intent;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.LinearLayout;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;

import com.dj.user.activity.mine.NewsCentreActivity;
import com.dj.user.activity.mine.NotificationDetailsActivity;
import com.dj.user.adapter.SliderListViewAdapter;
import com.dj.user.databinding.FragmentNewsHomeBinding;
import com.dj.user.fragment.BaseFragment;
import com.dj.user.model.request.RequestProfile;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Member;
import com.dj.user.model.response.Slider;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.google.gson.Gson;

import java.util.List;

public class NewsHomeFragment extends BaseFragment {

    private FragmentNewsHomeBinding binding;
    private Context context;
    private Member member;
    private LinearLayout dataPanel, noDataPanel, loadingPanel;
    private SliderListViewAdapter sliderListViewAdapter;

    public NewsHomeFragment newInstance(Context context) {
        NewsHomeFragment fragment = new NewsHomeFragment();
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
        binding = FragmentNewsHomeBinding.inflate(inflater, container, false);
        context = getContext();
        member = CacheManager.getObject(context, CacheManager.KEY_USER_PROFILE, Member.class);

        setupUI();
        getSliderList();
        return binding.getRoot();
    }

    @Override
    public void onResume() {
        super.onResume();
        getSliderList();
    }

    private void setupUI() {
        dataPanel = binding.panelData;
        noDataPanel = binding.panelNoData.getRoot();
        loadingPanel = binding.panelLoading.getRoot();

        sliderListViewAdapter = new SliderListViewAdapter(context);
        binding.listViewNotificationHome.setAdapter(sliderListViewAdapter);
        sliderListViewAdapter.setOnItemClickListener((position, object) -> {
            Bundle bundle = new Bundle();
            bundle.putString("data", new Gson().toJson(object));
            bundle.putBoolean("isNotification", false);
            ((NewsCentreActivity) context).startAppActivity(new Intent(context, NotificationDetailsActivity.class),
                    bundle, false, false, true);
        });
    }

    private void getSliderList() {
        if (sliderListViewAdapter.isEmpty()) {
            dataPanel.setVisibility(View.GONE);
            noDataPanel.setVisibility(View.GONE);
            loadingPanel.setVisibility(View.VISIBLE);
        }
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        ((NewsCentreActivity) context).executeApiCall(context, apiService.getSliderList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<Slider>> response) {
                List<Slider> sliderList = response.getData();
                if (sliderList != null && !sliderList.isEmpty()) {
                    int count = 0;
                    for (Slider slider : sliderList) {
                        if (!slider.isRead()) {
                            count += 1;
                        }
                    }
                    ((NewsCentreActivity) context).updateTabTag(2, count);
                    sliderListViewAdapter.replaceList(sliderList);
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

    @Override
    public void onDestroyView() {
        super.onDestroyView();
        binding = null;
    }
}