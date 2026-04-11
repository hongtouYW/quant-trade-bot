package com.dj.user.dj.activity.mine.setting;

import android.os.Bundle;

import com.dj.user.activity.BaseActivity;
import com.dj.user.adapter.LanguageListViewAdapter;
import com.dj.user.databinding.DjActivityLanguageBinding;
import com.dj.user.model.ItemLanguage;
import com.dj.user.util.CacheManager;

import java.util.ArrayList;
import java.util.List;

public class DJLanguageActivity extends BaseActivity {

    private DjActivityLanguageBinding binding;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = DjActivityLanguageBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        setupToolbar(binding.toolbar.getRoot(), "语言", 0, null);
        setupActionList();
    }

    private void setupActionList() {
        List<ItemLanguage> languageList = new ArrayList<>();
        languageList.add(new ItemLanguage(1, "中文", "zh"));
        languageList.add(new ItemLanguage(2, "马来", "ms"));
        languageList.add(new ItemLanguage(3, "英语", "en"));
        languageList.add(new ItemLanguage(4, "泰国", "th"));
        languageList.add(new ItemLanguage(5, "越南", "vn"));
        languageList.add(new ItemLanguage(6, "缅甸", "bm"));

        String language = CacheManager.getString(this, CacheManager.KEY_LANGUAGE);
        if (language == null || language.isEmpty()) {
            language = "zh";
        }
        LanguageListViewAdapter languageListViewAdapter = new LanguageListViewAdapter(this);
        languageListViewAdapter.addList(languageList);
        binding.listViewLanguage.setAdapter(languageListViewAdapter);
        languageListViewAdapter.setOnItemClickListener((position, object) -> {

        });

        for (int i = 0; i < languageList.size(); i++) {
            ItemLanguage itemLanguage = languageList.get(i);
            if (itemLanguage.getCode().equalsIgnoreCase(language)) {
                languageListViewAdapter.setSelectedPosition(i);
                break;
            }
        }
    }
}