package com.dj.manager.adapter;

import android.content.Context;
import android.text.SpannableString;
import android.text.Spanned;
import android.text.style.ForegroundColorSpan;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.core.content.ContextCompat;

import com.dj.manager.R;
import com.dj.manager.model.response.LogDesc;
import com.dj.manager.model.response.SystemLog;
import com.dj.manager.util.DateFormatUtils;

public class LogListViewAdapter extends CustomAdapter<SystemLog> {

    private class ViewHolder {
        private LinearLayout itemPanel;
        private TextView descTextView, timeTextView, dateTextView;
    }

    public LogListViewAdapter(Context context) {
        super(context);
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_log, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.descTextView = convertView.findViewById(R.id.textView_desc);
            viewHolder.timeTextView = convertView.findViewById(R.id.textView_time);
            viewHolder.dateTextView = convertView.findViewById(R.id.textView_date);
            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final SystemLog systemLog = getItem(position);
        if (systemLog != null) {
            LogDesc logDesc = systemLog.getLog_desc();
            if (logDesc != null) {
                String name = logDesc.getName() != null ? logDesc.getName() : "";
                String template = logDesc.getTemplate() != null ? logDesc.getTemplate() : "";
                String target = logDesc.getTarget() != null ? logDesc.getTarget() : "";

                String sentence = String.format(template, name, target);
                SpannableString spannable = new SpannableString(sentence);
                if (!name.isEmpty() && sentence.contains(name)) {
                    int startName = sentence.indexOf(name);
                    int endName = startName + name.length();
                    spannable.setSpan(
                            new ForegroundColorSpan(ContextCompat.getColor(context, R.color.gold_D4AF37)),
                            startName, endName, Spanned.SPAN_EXCLUSIVE_EXCLUSIVE
                    );
                }
                if (!target.isEmpty() && sentence.contains(target)) {
                    int startTarget = sentence.lastIndexOf(target);
                    int endTarget = startTarget + target.length();
                    spannable.setSpan(
                            new ForegroundColorSpan(ContextCompat.getColor(context, R.color.gold_D4AF37)),
                            startTarget, endTarget, Spanned.SPAN_EXCLUSIVE_EXCLUSIVE
                    );
                }
                viewHolder.descTextView.setText(spannable);
            } else {
                viewHolder.descTextView.setText("-");
            }
            viewHolder.timeTextView.setText(DateFormatUtils.formatIsoDate(systemLog.getCreated_on(), DateFormatUtils.FORMAT_HH_MM_A));
            viewHolder.dateTextView.setText(DateFormatUtils.formatIsoDate(systemLog.getCreated_on(), DateFormatUtils.FORMAT_YYYY_MM_DD));
            viewHolder.itemPanel.setOnClickListener(view -> onItemClickListener.onItemClick(position, systemLog));
        }
        return convertView;
    }
}

