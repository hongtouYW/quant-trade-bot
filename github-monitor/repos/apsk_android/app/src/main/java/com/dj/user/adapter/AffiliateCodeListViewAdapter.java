package com.dj.user.adapter;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.view.inputmethod.InputMethodManager;
import android.widget.Button;
import android.widget.EditText;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.NonNull;

import com.dj.user.R;
import com.dj.user.activity.mine.affiliate.AffiliateCodeActivity;
import com.dj.user.model.response.InvitationCode;
import com.dj.user.util.StringUtil;

public class AffiliateCodeListViewAdapter extends CustomAdapter<InvitationCode> {
    private class ViewHolder {
        private LinearLayout itemPanel, labelPanel, editLabelPanel;
        private ImageView tickImageView, editImageView, defaultImageView, codeCopyImageView, linkCopyImageView;
        private TextView labelTextView, defaultTextView, codeTextView, linkTextView;
        private EditText labelEditText;
        private Button inviteNowButton;
    }

    public AffiliateCodeListViewAdapter(Context context) {
        super(context);
    }

    @NonNull
    @Override
    public View getView(final int position, View convertView, @NonNull ViewGroup parent) {
        final ViewHolder viewHolder;
        if (convertView == null) {
            viewHolder = new ViewHolder();
            convertView = LayoutInflater.from(context).inflate(R.layout.item_list_affiliate_code, parent, false);
            viewHolder.itemPanel = convertView.findViewById(R.id.panel_item);
            viewHolder.labelPanel = convertView.findViewById(R.id.panel_label);
            viewHolder.labelTextView = convertView.findViewById(R.id.textView_label);
            viewHolder.tickImageView = convertView.findViewById(R.id.imageView_tick);
            viewHolder.editLabelPanel = convertView.findViewById(R.id.panel_label_edit);
            viewHolder.labelEditText = convertView.findViewById(R.id.editText_label);
            viewHolder.editImageView = convertView.findViewById(R.id.imageView_edit);
            viewHolder.defaultTextView = convertView.findViewById(R.id.textView_default);
            viewHolder.defaultImageView = convertView.findViewById(R.id.imageView_default);
            viewHolder.codeTextView = convertView.findViewById(R.id.textView_invitation_code);
            viewHolder.codeCopyImageView = convertView.findViewById(R.id.imageView_copy_code);
            viewHolder.linkTextView = convertView.findViewById(R.id.textView_invitation_link);
            viewHolder.linkCopyImageView = convertView.findViewById(R.id.imageView_copy_link);
            viewHolder.inviteNowButton = convertView.findViewById(R.id.button_invite_now);

            convertView.setTag(viewHolder);
        } else {
            viewHolder = (ViewHolder) convertView.getTag();
        }

        final InvitationCode invitationCode = getItem(position);
        if (invitationCode != null) {
            if (!invitationCode.isEditing()) {
                viewHolder.editLabelPanel.setVisibility(View.GONE);
                viewHolder.labelPanel.setVisibility(View.VISIBLE);
                viewHolder.labelTextView.setText(!StringUtil.isNullOrEmpty(invitationCode.getInvitecode_name()) ? invitationCode.getInvitecode_name() : context.getString(R.string.affiliate_code_default_label));
                viewHolder.editImageView.setOnClickListener(view -> {
                    invitationCode.setEditing(true);
                    notifyDataSetChanged();
                });
            } else {
                viewHolder.editLabelPanel.setVisibility(View.VISIBLE);
                viewHolder.labelPanel.setVisibility(View.GONE);
                viewHolder.labelEditText.setText(!StringUtil.isNullOrEmpty(invitationCode.getInvitecode_name()) ? invitationCode.getInvitecode_name() : context.getString(R.string.affiliate_code_default_label));
                viewHolder.labelEditText.post(() -> {
                    viewHolder.labelEditText.requestFocus();
                    viewHolder.labelEditText.setSelection(viewHolder.labelEditText.length());
                    InputMethodManager imm = (InputMethodManager) context.getSystemService(Context.INPUT_METHOD_SERVICE);
                    imm.showSoftInput(viewHolder.labelEditText, InputMethodManager.SHOW_FORCED);
                });
                viewHolder.tickImageView.setOnClickListener(view -> {
                    String invitationCodeName = viewHolder.labelEditText.getText().toString();
                    if (!invitationCodeName.isEmpty()) {
                        ((AffiliateCodeActivity) context).setInvitationCodeName(invitationCode, invitationCodeName);
                    }
                });
            }
            viewHolder.defaultTextView.setText(invitationCode.isDefault() ? context.getString(R.string.affiliate_code_default) : context.getString(R.string.affiliate_code_set_as_default));
            viewHolder.defaultImageView.setImageResource(invitationCode.isDefault() ? R.drawable.ic_check_selected : R.drawable.ic_radio);
            viewHolder.defaultImageView.setOnClickListener(view -> {
                if (!invitationCode.isDefault()) {
                    ((AffiliateCodeActivity) context).setDefaultInvitationCode(invitationCode);
                }
            });
            viewHolder.codeTextView.setText(invitationCode.getReferralCode());
            viewHolder.codeCopyImageView.setOnClickListener(view -> StringUtil.copyToClipboard(context, "", invitationCode.getReferralCode()));
            viewHolder.linkTextView.setText(invitationCode.getQr());
            viewHolder.linkTextView.setSelected(true);
            viewHolder.linkCopyImageView.setOnClickListener(view -> StringUtil.copyToClipboard(context, "", invitationCode.getQr()));
            viewHolder.inviteNowButton.setOnClickListener(view -> ((AffiliateCodeActivity) context).shareInvitation(invitationCode));
            viewHolder.itemPanel.setOnClickListener(view -> onItemClickListener.onItemClick(position, invitationCode));
        }
        return convertView;
    }

    public void finishEditing(InvitationCode code, String newLabel) {
        code.setInvitecode_name(newLabel);
        code.setEditing(false);
        notifyDataSetChanged();
    }
}

