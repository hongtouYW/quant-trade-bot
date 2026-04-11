package com.dj.user.dj.fragment;

import android.content.Context;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.LinearLayout;
import android.widget.ListView;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;

import com.dj.user.databinding.DjFragmentRankSongListBinding;
import com.dj.user.dj.adapter.DJRankSongListViewAdapter;
import com.dj.user.fragment.BaseFragment;
import com.dj.user.model.Song;

import java.util.ArrayList;
import java.util.List;

public class DJRankSongListFragment extends BaseFragment {

    private DjFragmentRankSongListBinding binding;
    private Context context;
    private int page = 0;
    private LinearLayout dataPanel, noDataPanel, loadingPanel;

    public DJRankSongListFragment newInstance(Context context, int page) {
        DJRankSongListFragment fragment = new DJRankSongListFragment();
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
        binding = DjFragmentRankSongListBinding.inflate(inflater, container, false);
        context = getContext();

        setupUI();
        setupSongListView();
        return binding.getRoot();
    }

    private void setupUI() {
        dataPanel = binding.panelData;
        noDataPanel = binding.panelNoData.getRoot();
        loadingPanel = binding.panelLoading.getRoot();
    }

    private void setupSongListView() {
        List<Song> songList = new ArrayList<>();
        songList.add(new Song());
        songList.add(new Song());
        songList.add(new Song());
        songList.add(new Song());
        songList.add(new Song());
        songList.add(new Song());
        songList.add(new Song());
        songList.add(new Song());
        songList.add(new Song());
        songList.add(new Song());
        songList.add(new Song());
        songList.add(new Song());
        songList.add(new Song());
        songList.add(new Song());

        ListView songListView = binding.listViewSong;
        DJRankSongListViewAdapter songListViewAdapter = new DJRankSongListViewAdapter(context);
        songListView.setAdapter(songListViewAdapter);
        songListViewAdapter.replaceList(songList);
        songListViewAdapter.setOnItemClickListener((position, object) -> {
            // TODO: 10/10/2025
        });

        dataPanel.setVisibility(View.VISIBLE);
        noDataPanel.setVisibility(View.GONE);
        loadingPanel.setVisibility(View.GONE);
    }
}

