package com.dj.manager.activity;

import static com.dj.manager.R.string.language_chinese;
import static com.dj.manager.R.string.language_malay;

import android.content.Intent;
import android.os.Bundle;

import com.dj.manager.R;
import com.dj.manager.adapter.LanguageListViewAdapter;
import com.dj.manager.databinding.ActivityOnboardingBinding;
import com.dj.manager.model.ItemLanguage;
import com.dj.manager.widget.SelectionBottomSheetDialogFragment;

import java.util.ArrayList;

public class OnboardingActivity extends BaseActivity {

    private ActivityOnboardingBinding binding;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityOnboardingBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        setupUI();
    }

    private void setupUI() {
        binding.buttonLogin.setOnClickListener(view ->
                startAppActivity(new Intent(this, LoginActivity.class),
                        null, false, false, false, true
                ));

        String currentLanguage = getCurrentLanguage(this);
        binding.textViewLanguage.setText(getString(
                currentLanguage.equalsIgnoreCase("en") ? R.string.language_english :
                        currentLanguage.equalsIgnoreCase("ms") ? language_malay :
                                language_chinese)
        );
        ArrayList<ItemLanguage> languageList = new ArrayList<>();
        languageList.add(new ItemLanguage(1, getString(language_chinese), "zh"));
        languageList.add(new ItemLanguage(2, getString(R.string.language_english), "en"));
        languageList.add(new ItemLanguage(3, getString(R.string.language_malay), "ms"));
        for (ItemLanguage itemLanguage : languageList) {
            itemLanguage.setSelected(itemLanguage.getCode().equalsIgnoreCase(currentLanguage));
        }
        LanguageListViewAdapter languageListViewAdapter = new LanguageListViewAdapter(this);
        binding.panelLanguage.setOnClickListener(view ->
                SelectionBottomSheetDialogFragment.newInstance(
                        getString(R.string.profile_language_settings),
                        false,
                        languageList,
                        languageListViewAdapter,
                        (language, pos) -> {
                            setLocale(language.getCode());
                            for (ItemLanguage itemLanguage : languageList) {
                                itemLanguage.setSelected(itemLanguage.getCode().equalsIgnoreCase(language.getCode()));
                            }
                        },
                        ItemLanguage.class).show(getSupportFragmentManager(), "LanguageSheet")
        );
    }
}