package com.dj.user.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;

import com.dj.user.R;
import com.dj.user.model.response.BankAccount;
import com.dj.user.util.FormatUtils;

public class BankAccountSelectionListViewAdapter extends CustomAdapter<BankAccount> {

    private class ViewHolder {
        private LinearLayout itemPanel;
        private TextView bankTextView, accountNoTextView;
        private ImageView radioImageView;
    }

    public BankAccountSelectionListViewAdapter(Context context) {
        super(context);
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_bank_selection, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.bankTextView = convertView.findViewById(R.id.textView_bank);
            viewHolder.accountNoTextView = convertView.findViewById(R.id.textView_account_no);
            viewHolder.radioImageView = convertView.findViewById(R.id.imageView_radio);
            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final BankAccount bankAccount = getItem(position);
        if (bankAccount != null) {
            viewHolder.bankTextView.setText(bankAccount.getBankName());
            viewHolder.accountNoTextView.setText(FormatUtils.maskWithDots(bankAccount.getBank_account()));
            viewHolder.radioImageView.setImageResource(bankAccount.isSelected() ? R.drawable.ic_radio_selected : R.drawable.ic_radio);
            viewHolder.itemPanel.setOnClickListener(view -> {
                setSelected(position);
                onItemClickListener.onItemClick(position, bankAccount);
            });
        }
        return convertView;
    }

    public void setSelected(int position) {
        if (getList() == null) {
            return;
        }
        if (position >= 0 && position < getList().size()) {
            for (BankAccount bankAccount : getList()) {
                bankAccount.setSelected(false);
            }
            getList().get(position).setSelected(true);
            notifyDataSetChanged();
        }
    }

    public BankAccount getSelected() {
        if (getList() == null) {
            return null;
        }
        for (BankAccount bankAccount : getList()) {
            if (bankAccount.isSelected()) {
                return bankAccount;
            }
        }
        return null;
    }
}

