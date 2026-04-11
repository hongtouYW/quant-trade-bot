package com.dj.user.fragment;

import android.content.Context;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;

import com.dj.user.activity.BaseActivity;
import com.dj.user.adapter.VIPListViewAdapter;
import com.dj.user.databinding.FragmentVipBinding;
import com.dj.user.enums.VIPType;
import com.dj.user.model.request.RequestVIP;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Member;
import com.dj.user.model.response.VIP;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;
import com.dj.user.widget.ExpandableHeightListView;

import java.util.List;

public class VIPFragment extends BaseFragment {

    private FragmentVipBinding binding;
    private Context context;
    private Member member;
    private ExpandableHeightListView vipListView;
    private VIPListViewAdapter vipListViewAdapter;
    private VIPType type;
    private int page = 0;

    public VIPFragment newInstance(Context context, VIPType type, int page) {
        VIPFragment fragment = new VIPFragment();
        fragment.context = context;
        fragment.type = type;
        fragment.page = page;
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
        binding = FragmentVipBinding.inflate(inflater, container, false);
        context = getContext();
        member = CacheManager.getObject(context, CacheManager.KEY_USER_PROFILE, Member.class);

        setupUI();
        return binding.getRoot();
    }

    @Override
    public void onResume() {
        super.onResume();
        member = CacheManager.getObject(context, CacheManager.KEY_USER_PROFILE, Member.class);
        if (vipListViewAdapter != null && member != null) {
            vipListViewAdapter.updateVipLevel(member.getVip());
        }
        getVIPList();
    }

    private void setupUI() {
        int level = 0;
        if (member != null) {
            level = member.getVip();
        }
        vipListView = binding.listViewVip;
        vipListView.setExpanded(true);
        vipListViewAdapter = new VIPListViewAdapter(context, level, type, page);
        vipListView.setAdapter(vipListViewAdapter);
        vipListViewAdapter.setOnItemClickListener((position, object) -> {
        });
    }

    private void getVIPList() {
        if (member == null || type == VIPType.VIP) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestVIP request = new RequestVIP(member.getMember_id(), type.getValue());
        ((BaseActivity) context).executeApiCall(context, apiService.getVIPList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<VIP>> response) {
                List<VIP> vipList = response.getData();
                if (vipList != null && !vipList.isEmpty()) {
                    vipList.add(0, new VIP(true));
                    vipListViewAdapter.replaceList(vipList);
                    vipListView.setVisibility(View.VISIBLE);

                } else {
                    vipListView.setVisibility(View.GONE);
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
    public void onDestroyView() {
        super.onDestroyView();
        binding = null;
    }
}