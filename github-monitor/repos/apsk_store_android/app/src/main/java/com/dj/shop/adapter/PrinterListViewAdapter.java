package com.dj.shop.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.core.content.ContextCompat;

import com.dj.shop.R;
import com.dj.shop.model.Printer;

public class PrinterListViewAdapter extends CustomAdapter<Printer> {
    private OnPrinterActionListener actionListener;

    private class ViewHolder {
        private LinearLayout itemPanel;
        private ImageView printerImageView;
        private TextView titleTextView, statusTextView, connectTextView;
    }

    public PrinterListViewAdapter(Context context) {
        super(context);
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_printer, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.printerImageView = convertView.findViewById(R.id.imageView_printer);
            viewHolder.titleTextView = convertView.findViewById(R.id.textView_title);
            viewHolder.statusTextView = convertView.findViewById(R.id.textView_status);
            viewHolder.connectTextView = convertView.findViewById(R.id.textView_connect);
            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final Printer printer = getItem(position);
        if (printer != null) {
            viewHolder.printerImageView.setImageResource(printer.getStatus() == 0 ? R.drawable.ic_printer : R.drawable.ic_printer_connected);

            viewHolder.titleTextView.setText(printer.getTitle());
            viewHolder.titleTextView.setTextColor(ContextCompat.getColor(context, printer.getStatus() == 0 ? R.color.white_FFFFFF : R.color.gold_FFCB22));

            viewHolder.statusTextView.setText(printer.getStatus() == 0 ? context.getString(R.string.profile_setting_printer_disconnected) : context.getString(R.string.profile_setting_printer_connected));
            viewHolder.statusTextView.setTextColor(ContextCompat.getColor(context, printer.getStatus() == 0 ? R.color.gray_737373 : R.color.white_FFFFFF));

            viewHolder.connectTextView.setText(printer.getStatus() == 0 ? context.getString(R.string.profile_setting_printer_connect): context.getString(R.string.profile_setting_printer_disconnect));
            viewHolder.connectTextView.setOnClickListener(view -> {
                if (actionListener != null) {
                    if (printer.getStatus() == 0) {
                        toggleConnection(printer);
                        actionListener.onConnect(printer);
                    } else {
                        printer.setStatus(0);
                        notifyDataSetChanged();
                        actionListener.onDisconnect(printer);
                    }
                }
            });
            viewHolder.itemPanel.setOnClickListener(view -> {
                if (actionListener != null) {
                    actionListener.onItemClick(position, printer);
                }
            });
        }
        return convertView;
    }

    public void setOnPrinterActionListener(OnPrinterActionListener listener) {
        this.actionListener = listener;
    }

    public void toggleConnection(Printer selectedPrinter) {
        boolean alreadyConnected = selectedPrinter.getStatus() == 1;
        for (Printer printer : getList()) {
            printer.setStatus(0);
        }
        if (!alreadyConnected) {
            selectedPrinter.setStatus(1);
        }
        notifyDataSetChanged();
    }
}

