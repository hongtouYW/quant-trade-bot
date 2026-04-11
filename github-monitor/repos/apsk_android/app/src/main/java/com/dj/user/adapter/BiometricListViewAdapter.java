package com.dj.user.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.TextView;
import android.widget.Toast;

import androidx.annotation.NonNull;
import androidx.appcompat.widget.SwitchCompat;

import com.dj.user.R;
import com.dj.user.activity.mine.security.BiometricActivity;
import com.dj.user.enums.BiometricStatus;
import com.dj.user.model.ItemBiometric;

public class BiometricListViewAdapter extends CustomAdapter<ItemBiometric> {

    private class ViewHolder {
        private LinearLayout itemPanel;
        private ImageView iconImageView;
        private TextView titleTextView;
        private SwitchCompat switchCompat;
    }

    public BiometricListViewAdapter(Context context) {
        super(context);
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_language, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.iconImageView = convertView.findViewById(R.id.imageView_icon);
            viewHolder.titleTextView = convertView.findViewById(R.id.textView_title);
            viewHolder.switchCompat = convertView.findViewById(R.id.switchCompat);

            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final ItemBiometric biometric = getItem(position);
        if (biometric != null) {
            viewHolder.iconImageView.setImageResource(biometric.getIconResId());
            viewHolder.titleTextView.setText(biometric.getTitle());
            viewHolder.switchCompat.setChecked(biometric.isEnabled());
            viewHolder.switchCompat.setOnCheckedChangeListener((buttonView, isChecked) -> {
                if (!biometric.isAvailable()) {
                    BiometricStatus status = ((BiometricActivity) context).getBiometricStatus();
                    if (status != BiometricStatus.SUCCESS) {
                        switch (status) {
                            case NO_HARDWARE:
                                Toast.makeText(context, "设备不支持生物识别", Toast.LENGTH_SHORT).show();
                                break;
                            case HW_UNAVAILABLE:
                                Toast.makeText(context, "生物识别硬件不可用", Toast.LENGTH_SHORT).show();
                                break;
                            case NONE_ENROLLED:
                                Toast.makeText(context, "请先在系统设置中录入指纹/面部", Toast.LENGTH_SHORT).show();
                                break;
                        }
                        buttonView.setChecked(false); // revert toggle
                        return;
                    }
                }
                if (!biometric.isEnabled()) {
                    ((BiometricActivity) context).startBiometricPrompt(biometric);
                } else {
                    ((BiometricActivity) context).setBiometricEnabled(biometric.getId(), false);
                    notifyDataSetChanged();
                }
            });
            viewHolder.itemPanel.setAlpha(biometric.isAvailable() ? 1.0F : 0.5F);
            viewHolder.itemPanel.setOnClickListener(view -> onItemClickListener.onItemClick(position, biometric));
        }
        return convertView;
    }
}

