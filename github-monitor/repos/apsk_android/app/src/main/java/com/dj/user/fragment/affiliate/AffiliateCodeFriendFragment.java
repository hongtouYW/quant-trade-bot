package com.dj.user.fragment.affiliate;

import android.content.Context;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.LinearLayout;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;

import com.dj.user.activity.mine.affiliate.AffiliateCodeDetailsActivity;
import com.dj.user.adapter.AffiliateCodeFriendListViewAdapter;
import com.dj.user.databinding.FragmentAffiliateCodeFriendBinding;
import com.dj.user.fragment.BaseFragment;
import com.dj.user.model.request.RequestProfile;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Friend;
import com.dj.user.model.response.Member;
import com.dj.user.util.ApiCallback;
import com.dj.user.util.ApiClient;
import com.dj.user.util.ApiService;
import com.dj.user.util.CacheManager;

import java.util.List;

public class AffiliateCodeFriendFragment extends BaseFragment {

    private FragmentAffiliateCodeFriendBinding binding;
    private Context context;
    private Member member;
    private LinearLayout dataPanel, noDataPanel, loadingPanel;
    private AffiliateCodeFriendListViewAdapter affiliateCodeFriendListViewAdapter;

    public AffiliateCodeFriendFragment newInstance(Context context) {
        AffiliateCodeFriendFragment fragment = new AffiliateCodeFriendFragment();
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
        binding = FragmentAffiliateCodeFriendBinding.inflate(inflater, container, false);
        context = getContext();
        member = CacheManager.getObject(context, CacheManager.KEY_USER_PROFILE, Member.class);

        setupAffiliateCodeFriendList();
        return binding.getRoot();
    }

    @Override
    public void onResume() {
        super.onResume();
        getFriendList();
    }

    private void setupAffiliateCodeFriendList() {
        dataPanel = binding.panelData;
        noDataPanel = binding.panelNoData.getRoot();
        loadingPanel = binding.panelLoading.getRoot();

        affiliateCodeFriendListViewAdapter = new AffiliateCodeFriendListViewAdapter(context);
        binding.listViewFriend.setAdapter(affiliateCodeFriendListViewAdapter);
        affiliateCodeFriendListViewAdapter.setOnItemClickListener((position, object) -> {
        });
    }

    private void getFriendList() {
        if (affiliateCodeFriendListViewAdapter.isEmpty()) {
            dataPanel.setVisibility(View.GONE);
            noDataPanel.setVisibility(View.GONE);
            loadingPanel.setVisibility(View.VISIBLE);
        }
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        RequestProfile request = new RequestProfile(member.getMember_id());
        ((AffiliateCodeDetailsActivity) context).executeApiCall(context, apiService.getFriendList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<Friend>> response) {
                List<Friend> friendList = response.getData();
                loadingPanel.setVisibility(View.GONE);
                if (friendList != null && !friendList.isEmpty()) {
                    affiliateCodeFriendListViewAdapter.replaceList(friendList);
                    dataPanel.setVisibility(View.VISIBLE);
                    noDataPanel.setVisibility(View.GONE);
                } else {
                    dataPanel.setVisibility(View.GONE);
                    noDataPanel.setVisibility(View.VISIBLE);
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