package com.dj.user.widget;

import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageView;
import android.widget.LinearLayout;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;

import com.dj.user.R;
import com.dj.user.adapter.PlayerListViewAdapter;
import com.dj.user.model.response.Player;
import com.google.android.material.bottomsheet.BottomSheetDialogFragment;
import com.google.gson.Gson;

import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;

public class PlayerBottomSheetDialogFragment extends BottomSheetDialogFragment {

    private static final String ARG_FAV = "arg_fav";
    private static final String ARG_LIST = "arg_list";

    private ArrayList<Player> playerList;
    private ImageView favsImageView;
    private PlayerListViewAdapter playerListViewAdapter;
    private OnPlayerSelectedListener playerListener;
    private OnAddPlayerListener addPlayerListener;
    private OnFavYxiListener favYxiListener;
    private boolean isFaved = false;

    public interface OnPlayerSelectedListener {
        void onPlayerSelected(Player player, int position);
    }

    public interface OnAddPlayerListener {
        void onAddPlayer();
    }

    public interface OnFavYxiListener {
        void onFavYxi();
    }

    public static PlayerBottomSheetDialogFragment newInstance(boolean isFaved, ArrayList<Player> list, OnPlayerSelectedListener playerListener, OnAddPlayerListener addPlayerListener, OnFavYxiListener favYxiListener) {
        PlayerBottomSheetDialogFragment fragment = new PlayerBottomSheetDialogFragment();
        Bundle args = new Bundle();
        args.putBoolean(ARG_FAV, isFaved);
        args.putString(ARG_LIST, new Gson().toJson(list));
        fragment.setArguments(args);
        fragment.setPlayerSelectedListener(playerListener);
        fragment.setAddPlayerListener(addPlayerListener);
        fragment.setFavYxiListener(favYxiListener);
        return fragment;
    }

    public void setPlayerSelectedListener(OnPlayerSelectedListener listener) {
        this.playerListener = listener;
    }

    public void setAddPlayerListener(OnAddPlayerListener listener) {
        this.addPlayerListener = listener;
    }

    public void setFavYxiListener(OnFavYxiListener listener) {
        this.favYxiListener = listener;
    }

    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater,
                             @Nullable ViewGroup container,
                             @Nullable Bundle savedInstanceState) {

        View view = inflater.inflate(R.layout.dialog_bottom_sheet_player, container, false);
        ImageView dismissImageView = view.findViewById(R.id.imageView_dismiss);
        favsImageView = view.findViewById(R.id.imageView_fav);
        ExpandableHeightListView listView = view.findViewById(R.id.listView_player);
        LinearLayout addPanel = view.findViewById(R.id.panel_add);

        if (getArguments() != null) {
            isFaved = getArguments().getBoolean(ARG_FAV, false);
            String json = getArguments().getString(ARG_LIST);
            if (json != null) {
                playerList = new ArrayList<>(Arrays.asList(new Gson().fromJson(json, Player[].class)));
            }
        }
        playerListViewAdapter = new PlayerListViewAdapter(requireContext());
        playerListViewAdapter.replaceList(playerList);
        playerListViewAdapter.setOnItemClickListener((position, player) -> {
            if (playerListener != null) {
                playerListener.onPlayerSelected((Player) player, position);
            }
        });
        listView.setAdapter(playerListViewAdapter);
        dismissImageView.setOnClickListener(v -> dismiss());
        favsImageView.setImageResource(isFaved ? R.drawable.ic_fav_selected : R.drawable.ic_fav);
        favsImageView.setOnClickListener(v -> {
            if (favYxiListener != null) {
                favYxiListener.onFavYxi();
            }
        });
        addPanel.setVisibility(playerList.isEmpty() ? View.VISIBLE : View.GONE);
        addPanel.setOnClickListener(v -> {
            if (addPlayerListener != null) {
                addPlayerListener.onAddPlayer();
            }
        });
        return view;
    }

    public void updatePlayers(List<Player> newPlayers) {
        if (playerListViewAdapter != null) {
            playerListViewAdapter.replaceList(newPlayers);
        }
    }

    public void updateFavStatus(boolean isFaved) {
        this.isFaved = isFaved;
        favsImageView.setImageResource(isFaved ? R.drawable.ic_fav_selected : R.drawable.ic_fav);
    }

    @Override
    public int getTheme() {
        return R.style.BottomSheetDialogTheme;
    }
}
