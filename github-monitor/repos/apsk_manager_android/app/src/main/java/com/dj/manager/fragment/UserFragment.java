package com.dj.manager.fragment;

import android.content.Context;
import android.content.Intent;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.LinearLayout;
import android.widget.ListView;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.fragment.app.Fragment;

import com.dj.manager.activity.user.UserDetailsActivity;
import com.dj.manager.activity.user.UserManagementActivity;
import com.dj.manager.adapter.UserListViewAdapter;
import com.dj.manager.databinding.FragmentUserBinding;
import com.dj.manager.model.request.RequestBlockedData;
import com.dj.manager.model.request.RequestDeleteData;
import com.dj.manager.model.request.RequestProfile;
import com.dj.manager.model.request.RequestStatusData;
import com.dj.manager.model.response.BaseResponse;
import com.dj.manager.model.response.Manager;
import com.dj.manager.model.response.Member;
import com.dj.manager.util.ApiCallback;
import com.dj.manager.util.ApiClient;
import com.dj.manager.util.ApiService;
import com.dj.manager.util.CacheManager;
import com.google.gson.Gson;

import java.util.List;

import retrofit2.Call;

public class UserFragment extends Fragment {
    private FragmentUserBinding binding;
    private Context context;
    private int page = 1;
    private Manager manager;
    private LinearLayout dataPanel, noDataPanel, loadingPanel;
    private UserListViewAdapter userListViewAdapter;
    private boolean allowFilterCallback = false;

    public UserFragment newInstance(Context context, int page) {
        UserFragment fragment = new UserFragment();
        fragment.context = context;
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
        binding = FragmentUserBinding.inflate(inflater, container, false);
        context = getContext();
        manager = CacheManager.getObject(context, CacheManager.KEY_MANAGER_PROFILE, Manager.class);

        setupUI();
        return binding.getRoot();
    }

    @Override
    public void onResume() {
        super.onResume();
        getMemberList();
    }

    private void setupUI() {
        dataPanel = binding.panelData;
        noDataPanel = binding.panelNoData.getRoot();
        loadingPanel = binding.panelLoading.getRoot();

        ListView userListView = binding.listViewUser;
        userListViewAdapter = new UserListViewAdapter(context);
        userListView.setAdapter(userListViewAdapter);

        userListViewAdapter.setFilterListener(isEmpty -> {
            if (!allowFilterCallback) return;
            if (isEmpty) {
                dataPanel.setVisibility(View.GONE);
                noDataPanel.setVisibility(View.VISIBLE);
                loadingPanel.setVisibility(View.GONE);
            } else {
                dataPanel.setVisibility(View.VISIBLE);
                noDataPanel.setVisibility(View.GONE);
                loadingPanel.setVisibility(View.GONE);
            }
        });
        userListViewAdapter.setOnItemClickListener((position, object) -> {
            Bundle bundle = new Bundle();
            bundle.putString("data", new Gson().toJson(object));
            ((UserManagementActivity) context).startAppActivity(new Intent(context, UserDetailsActivity.class),
                    bundle, false, false, false, true);
        });
    }

    public void filter(String keyword) {
        if (userListViewAdapter != null) {
            userListViewAdapter.getFilter().filter(keyword);
        }
    }

    private void getMemberList() {
        if (userListViewAdapter.isEmpty()) {
            dataPanel.setVisibility(View.GONE);
            noDataPanel.setVisibility(View.GONE);
            loadingPanel.setVisibility(View.VISIBLE);
        }
        ApiService apiService = ApiClient.getInstance(context).create(ApiService.class);
        Call<BaseResponse<List<Member>>> call;
        if (page == 2) {
            RequestStatusData request = new RequestStatusData(manager.getManager_id(), 1);
            call = apiService.getMemberList(request);
        } else if (page == 3) {
            RequestBlockedData request = new RequestBlockedData(manager.getManager_id(), 0, 0);
            call = apiService.getMemberList(request);
        } else if (page == 4) {
            RequestDeleteData request = new RequestDeleteData(manager.getManager_id(), 1);
            call = apiService.getMemberList(request);
        } else {
            RequestProfile request = new RequestProfile(manager.getManager_id());
            call = apiService.getMemberList(request);
        }
        ((UserManagementActivity) context).executeApiCall(context, call, new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<Member>> response) {
                List<Member> memberList = response.getData();
                if (memberList != null && !memberList.isEmpty()) {
                    userListViewAdapter.setData(memberList);
                    allowFilterCallback = false;
                    userListViewAdapter.getFilter().filter("");
                    allowFilterCallback = true;

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
                dataPanel.setVisibility(View.GONE);
                noDataPanel.setVisibility(View.VISIBLE);
                loadingPanel.setVisibility(View.GONE);
                return false;
            }

            @Override
            public boolean onFailure(Throwable t) {
                dataPanel.setVisibility(View.GONE);
                noDataPanel.setVisibility(View.VISIBLE);
                loadingPanel.setVisibility(View.GONE);
                return false;
            }
        }, false);
    }
}
