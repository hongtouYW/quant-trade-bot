package com.dj.user.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.appcompat.widget.SwitchCompat;

import com.dj.user.R;
import com.dj.user.activity.mine.bank.BankListActivity;
import com.dj.user.model.response.BankAccount;
import com.dj.user.util.FormatUtils;

public class BankAccountListViewAdapter extends CustomAdapter<BankAccount> {

    private class ViewHolder {
        private LinearLayout itemPanel;
        private TextView bankTextView, accountNoTextView;
        private SwitchCompat quickPaySwitch;
    }

    public BankAccountListViewAdapter(Context context) {
        super(context);
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_bank, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.bankTextView = convertView.findViewById(R.id.textView_bank);
            viewHolder.accountNoTextView = convertView.findViewById(R.id.textView_account_no);
            viewHolder.quickPaySwitch = convertView.findViewById(R.id.switchCompat_quick_pay);
            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final BankAccount bankAccount = getItem(position);
        if (bankAccount != null) {
            viewHolder.quickPaySwitch.setOnCheckedChangeListener(null);

            viewHolder.bankTextView.setText(bankAccount.getBankName());
            viewHolder.accountNoTextView.setText(FormatUtils.maskWithDots(bankAccount.getBank_account()));
            viewHolder.quickPaySwitch.setChecked(bankAccount.isQuickPay());
            viewHolder.quickPaySwitch.setOnCheckedChangeListener((buttonView, isChecked) ->
                    ((BankListActivity) context).updateQuickPay(bankAccount, viewHolder.quickPaySwitch.isChecked() ? 1 : 0)
            );
            viewHolder.itemPanel.setOnClickListener(view -> onItemClickListener.onItemClick(position, bankAccount));
        }
        return convertView;
    }
}

