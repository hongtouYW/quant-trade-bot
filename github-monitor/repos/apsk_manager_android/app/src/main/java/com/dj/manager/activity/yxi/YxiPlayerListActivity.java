package com.dj.manager.activity.yxi;

import android.content.Intent;
import android.os.Bundle;
import android.text.Editable;
import android.text.TextWatcher;
import android.view.View;
import android.widget.LinearLayout;

import com.dj.manager.R;
import com.dj.manager.activity.BaseActivity;
import com.dj.manager.adapter.PlayerListViewAdapter;
import com.dj.manager.databinding.ActivityYxiPlayerListBinding;
import com.dj.manager.model.request.RequestMemberYxiPlayer;
import com.dj.manager.model.response.BaseResponse;
import com.dj.manager.model.response.Manager;
import com.dj.manager.model.response.Member;
import com.dj.manager.model.response.Player;
import com.dj.manager.model.response.YxiProvider;
import com.dj.manager.util.ApiCallback;
import com.dj.manager.util.ApiClient;
import com.dj.manager.util.ApiService;
import com.dj.manager.util.CacheManager;
import com.dj.manager.util.StringUtil;
import com.google.gson.Gson;
import com.squareup.picasso.Callback;
import com.squareup.picasso.Picasso;

import java.util.List;

public class YxiPlayerListActivity extends BaseActivity {
    private ActivityYxiPlayerListBinding binding;
    private PlayerListViewAdapter playerListViewAdapter;
    private LinearLayout searchPanel;
    private Manager manager;
    private Member member;
    private YxiProvider yxiProvider;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityYxiPlayerListBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        manager = CacheManager.getObject(this, CacheManager.KEY_MANAGER_PROFILE, Manager.class);

        parseIntentData();
        setupUI();
        setupToolbar(binding.toolbar.getRoot(), yxiProvider.getProvider_name(), R.drawable.ic_search, view -> {
            if (searchPanel.getVisibility() == View.VISIBLE) {
                searchPanel.setVisibility(View.GONE);
                hideKeyboard(this);
            } else {
                searchPanel.setVisibility(View.VISIBLE);
            }
        });
        setupSearchPanel();
    }

    @Override
    protected void onResume() {
        super.onResume();
        getYxiPlayerList();
    }

    private void parseIntentData() {
        String json = getIntent().getStringExtra("member");
        member = new Gson().fromJson(json, Member.class);

        json = getIntent().getStringExtra("data");
        yxiProvider = new Gson().fromJson(json, YxiProvider.class);
    }

    private void setupSearchPanel() {
        binding.editTextSearch.addTextChangedListener(new TextWatcher() {
            @Override
            public void beforeTextChanged(CharSequence charSequence, int i, int i1, int i2) {

            }

            @Override
            public void onTextChanged(CharSequence charSequence, int i, int i1, int i2) {

            }

            @Override
            public void afterTextChanged(Editable editable) {
                if (editable.length() > 0) {
                    binding.imageViewClear.setVisibility(View.VISIBLE);
                } else {
                    binding.imageViewClear.setVisibility(View.GONE);
                }

                String keyword = editable.toString().trim().toLowerCase();
                playerListViewAdapter.getFilter().filter(keyword);
            }
        });
        binding.imageViewClear.setOnClickListener(view -> {
            binding.editTextSearch.setText("");
            binding.imageViewClear.setVisibility(View.GONE);
        });
    }

    private void setupUI() {
        searchPanel = binding.panelSearch;

        String icon = yxiProvider != null ? yxiProvider.getIcon() : "";
        if (!StringUtil.isNullOrEmpty(icon)) {
            if (!icon.startsWith("http")) {
                icon = String.format(getString(R.string.template_s_s), getString(R.string.image_base_url), icon);
            }
            Picasso.get().load(icon).centerCrop().fit().into(binding.imageViewYxi, new Callback() {
                @Override
                public void onSuccess() {

                }

                @Override
                public void onError(Exception e) {
                    binding.imageViewYxi.setImageResource(R.drawable.img_provider_default);
                }
            });
        } else {
            binding.imageViewYxi.setImageResource(R.drawable.img_provider_default);
        }

        playerListViewAdapter = new PlayerListViewAdapter(this);
        binding.listViewPlayer.setAdapter(playerListViewAdapter);
        binding.listViewPlayer.setExpanded(true);
        playerListViewAdapter.setFilterListener(isEmpty -> {
            if (isEmpty) {
            } else {
            }
        });
        playerListViewAdapter.setOnItemClickListener((position, object) -> {
            Player player = (Player) object;
            player.setProvider(yxiProvider);
            Bundle bundle = new Bundle();
            bundle.putString("data", new Gson().toJson(player));
            startAppActivity(new Intent(YxiPlayerListActivity.this, YxiPlayerDetailsActivity.class),
                    bundle, false, false, false, true);
        });

        binding.panelAddPlayer.setOnClickListener(view -> {
            addPlayer();
        });
    }

    private void getYxiPlayerList() {
        if (manager == null || member == null || yxiProvider == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestMemberYxiPlayer request = new RequestMemberYxiPlayer(manager.getManager_id(), member.getMember_id(), yxiProvider.getProvider_id());
        executeApiCall(this, apiService.getPlayerList(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<List<Player>> response) {
                List<Player> playerList = response.getData();
                playerListViewAdapter.setData(playerList);
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

    private void addPlayer() {
        if (manager == null || member == null || yxiProvider == null) {
            return;
        }
        ApiService apiService = ApiClient.getInstance(this).create(ApiService.class);
        RequestMemberYxiPlayer request = new RequestMemberYxiPlayer(manager.getManager_id(), member.getMember_id(), yxiProvider.getProvider_id());
        executeApiCall(this, apiService.addPlayer(request), new ApiCallback<>() {
            @Override
            public void onSuccess(BaseResponse<Void> response) {
                getYxiPlayerList();
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