package com.dj.user.dj.fragment;

import android.content.Context;
import android.content.Intent;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ExpandableListView;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;

import com.dj.user.activity.BaseActivity;
import com.dj.user.databinding.DjFragmentHelpBinding;
import com.dj.user.dj.activity.mine.setting.DJHelpDetailsActivity;
import com.dj.user.dj.adapter.DJHelpExpandableListAdapter;
import com.dj.user.fragment.BaseFragment;

import java.util.Arrays;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

public class DJHelpFragment extends BaseFragment {

    private DjFragmentHelpBinding binding;
    private Context context;
    private int page = 0;

    public DJHelpFragment newInstance(Context context, int page) {
        DJHelpFragment fragment = new DJHelpFragment();
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
        binding = DjFragmentHelpBinding.inflate(inflater, container, false);
        context = getContext();

        List<String> groupList = Arrays.asList("虚拟币钱包", "游戏问题", "优惠问题", "充值问题", "提现问题");
        Map<String, List<String>> childMap = new HashMap<>();
        childMap.put("虚拟币钱包", Arrays.asList("1. USDT虚拟币钱包的了解和使用", "2. 使用虚拟币的好处", "3. 如何下载并使用虚拟币"));
        childMap.put("游戏问题", Arrays.asList("1. USDT虚拟币钱包的了解和使用", "2. 使用虚拟币的好处", "3. 如何下载并使用虚拟币"));
        DJHelpExpandableListAdapter adapter = new DJHelpExpandableListAdapter(context, groupList, childMap);
        binding.expandableListView.setAdapter(adapter);
        binding.expandableListView.setOnChildClickListener(new ExpandableListView.OnChildClickListener() {
            @Override
            public boolean onChildClick(ExpandableListView parent, View v, int groupPosition, int childPosition, long id) {
//                String selectedItem = childMap.get(groupList.get(groupPosition)).get(childPosition);
                ((BaseActivity) context).startAppActivity(new Intent(context, DJHelpDetailsActivity.class),
                        null, false, false, true
                );
                return true;
            }
        });
        return binding.getRoot();
    }

    @Override
    public void onDestroyView() {
        super.onDestroyView();
        binding = null;
    }
}