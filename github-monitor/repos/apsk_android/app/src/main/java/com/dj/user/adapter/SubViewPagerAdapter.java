package com.dj.user.adapter;

import androidx.annotation.NonNull;
import androidx.fragment.app.Fragment;
import androidx.viewpager2.adapter.FragmentStateAdapter;

import java.util.List;

public class SubViewPagerAdapter extends FragmentStateAdapter {

    private final List<Fragment> fragmentList;

    public SubViewPagerAdapter(@NonNull Fragment fragment, List<Fragment> fragmentList) {
        super(fragment.getChildFragmentManager(), fragment.getLifecycle());
        this.fragmentList = fragmentList;
    }

    @NonNull
    @Override
    public Fragment createFragment(int position) {
        return fragmentList.get(position);
    }

    @Override
    public int getItemCount() {
        return fragmentList == null ? 0 : fragmentList.size();
    }
}

