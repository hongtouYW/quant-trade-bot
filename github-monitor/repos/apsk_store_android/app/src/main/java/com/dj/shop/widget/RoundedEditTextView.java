package com.dj.shop.widget;

import android.content.Context;
import android.graphics.PorterDuff;
import android.text.Editable;
import android.text.InputType;
import android.text.TextWatcher;
import android.util.AttributeSet;
import android.util.Log;
import android.view.LayoutInflater;
import android.widget.FrameLayout;
import android.widget.LinearLayout;

import androidx.core.content.ContextCompat;

import com.dj.shop.R;
import com.dj.shop.databinding.ViewRoundedEditTextBinding;

import java.text.DecimalFormat;

public class RoundedEditTextView extends FrameLayout {
    public interface OnTextCountChangeListener {
        void onCountChanged(int count);
    }

    private OnTextCountChangeListener countChangeListener;

    private final ViewRoundedEditTextBinding binding;
    private LinearLayout editTextContainer;
    private boolean isPasswordVisible = false;
    private int maxLength = -1;

    public enum InputFieldType {
        TEXT,
        PASSWORD,
        NUMBER,
        MULTILINE_TEXT,
        MULTILINE_TEXT_LONG,
        CURRENCY
    }

    public RoundedEditTextView(Context context) {
        this(context, null);
    }

    public RoundedEditTextView(Context context, AttributeSet attrs) {
        super(context, attrs);

        binding = ViewRoundedEditTextBinding.inflate(LayoutInflater.from(context), this, true);
        editTextContainer = binding.editTextContainer;
        binding.endButton.setVisibility(GONE);
        binding.errorText.setVisibility(GONE);

        // Auto-clear error on typing
        binding.editText.addTextChangedListener(new TextWatcher() {
            @Override
            public void beforeTextChanged(CharSequence s, int start, int count, int after) {
            }

            @Override
            public void onTextChanged(CharSequence s, int start, int before, int count) {
                clearError();
            }

            @Override
            public void afterTextChanged(Editable s) {
            }
        });
    }

    public void setInputFieldType(InputFieldType type) {
        switch (type) {
            case TEXT:
                binding.editText.setInputType(InputType.TYPE_CLASS_TEXT);
                binding.editText.setSingleLine(true);
                setEditTextHeight(56);
                binding.endButton.setVisibility(GONE);
                break;

            case PASSWORD:
                binding.editText.setInputType(InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_VARIATION_PASSWORD);
                binding.editText.setSingleLine(true);
                setEditTextHeight(56);
                binding.endButton.setVisibility(VISIBLE);
                binding.endButton.setOnClickListener(v -> togglePasswordVisibility());
                break;

            case NUMBER:
                binding.editText.setInputType(InputType.TYPE_CLASS_NUMBER);
                binding.editText.setSingleLine(true);
                setEditTextHeight(56);
                binding.endButton.setVisibility(GONE);
                break;

            case MULTILINE_TEXT:
            case MULTILINE_TEXT_LONG:
                binding.editText.setInputType(InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_FLAG_MULTI_LINE);
                binding.editText.setSingleLine(false);
                binding.editText.setLines(6);
                binding.editText.setMaxLines(10);
                binding.editText.setGravity(android.view.Gravity.TOP | android.view.Gravity.START);
                binding.editText.setVerticalScrollBarEnabled(true);
                binding.editText.setOverScrollMode(OVER_SCROLL_ALWAYS);
                setEditTextHeight(type == InputFieldType.MULTILINE_TEXT ? 151 : 292);
                binding.editText.setPadding(0, dpToPx(12), 0, dpToPx(12));
                binding.endButton.setVisibility(GONE);

                binding.editText.addTextChangedListener(new TextWatcher() {
                    @Override
                    public void beforeTextChanged(CharSequence s, int start, int count, int after) {
                    }

                    @Override
                    public void onTextChanged(CharSequence s, int start, int before, int count) {
                        if (countChangeListener != null) {
                            countChangeListener.onCountChanged(s.length());
                        }
                    }

                    @Override
                    public void afterTextChanged(Editable s) {
                    }
                });
                break;

            case CURRENCY:
                binding.editText.setInputType(InputType.TYPE_CLASS_NUMBER);
                binding.editText.setSingleLine(true);
                setEditTextHeight(56);
                binding.endButton.setVisibility(GONE);
                setupCurrencyInputWatcher();
                break;
        }
    }

    private int dpToPx(int dp) {
        return (int) (dp * getResources().getDisplayMetrics().density);
    }

    private void setEditTextHeight(int heightInDp) {
        binding.editText.getLayoutParams().height = dpToPx(heightInDp);
        binding.editText.requestLayout();
    }

    private void togglePasswordVisibility() {
        int selection = binding.editText.getSelectionEnd();
        if (isPasswordVisible) {
            // Hide password
            binding.editText.setTransformationMethod(android.text.method.PasswordTransformationMethod.getInstance());
            binding.endButton.setColorFilter(ContextCompat.getColor(getContext(), R.color.gray_C2C3CB), PorterDuff.Mode.SRC_IN);
        } else {
            // Show password
            binding.editText.setTransformationMethod(android.text.method.HideReturnsTransformationMethod.getInstance());
            binding.endButton.setColorFilter(ContextCompat.getColor(getContext(), R.color.gold_D4AF37), PorterDuff.Mode.SRC_IN);
        }
        // Restore cursor position
        binding.editText.setSelection(Math.min(selection, binding.editText.getText().length()));
        isPasswordVisible = !isPasswordVisible;
    }

    public void setOnTextCountChangeListener(OnTextCountChangeListener listener) {
        this.countChangeListener = listener;
    }

    private void setupCurrencyInputWatcher() {
        binding.editText.addTextChangedListener(new TextWatcher() {
            private boolean editing = false;
            private String current = "";

            @Override
            public void beforeTextChanged(CharSequence s, int start, int count, int after) {
            }

            @Override
            public void onTextChanged(CharSequence s, int start, int before, int count) {
            }

            @Override
            public void afterTextChanged(Editable s) {
                if (editing) return;
                editing = true;

                try {
                    String raw = s.toString();
                    if (raw.isEmpty()) {
                        binding.editText.setText("");
                        editing = false;
                        return;
                    }
                    String digits = raw.replaceAll("[^\\d]", "");
                    if (digits.isEmpty()) {
                        binding.editText.setText("");
                        editing = false;
                        return;
                    }
                    double parsed = Double.parseDouble(digits) / 100.0;
                    DecimalFormat formatter = new DecimalFormat("RM ###,##0.00");
                    String formatted = formatter.format(parsed);

                    binding.editText.setText(formatted);
                    binding.editText.setSelection(formatted.length());

                } catch (Exception e) {
                    Log.e("###", "setupCurrencyInputWatcher: ", e);
                }
                editing = false;
            }
        });
    }

    public void showError(String errorMsg) {
        editTextContainer.setBackground(ContextCompat.getDrawable(getContext(), R.drawable.bg_edit_text_error));
        binding.errorText.setText(errorMsg);
        binding.errorText.setVisibility(errorMsg.isEmpty() ? GONE : VISIBLE);
    }

    public void clearError() {
        editTextContainer.setBackground(ContextCompat.getDrawable(getContext(), R.drawable.bg_edit_text));
        binding.errorText.setVisibility(GONE);
    }

    public String getText() {
        return binding.editText.getText().toString().trim();
    }

    public double getCurrencyAmount() {
        String text = binding.editText.getText().toString();
        text = text.replace("RM", "").replaceAll(",", "").trim();
        try {
            return Double.parseDouble(text);
        } catch (NumberFormatException e) {
            Log.e("###", "getCurrencyAmount: ", e);
            return 0.0;
        }
    }

    public void setText(String text) {
        binding.editText.setText(text);
    }

    public void setHint(String hint) {
        binding.editText.setHint(hint);
    }

    public void setEndButton(int iconRes, OnClickListener action) {
        binding.endButton.setImageResource(iconRes);
        binding.endButton.setVisibility(VISIBLE);
        binding.endButton.setOnClickListener(action);
    }

    public void setEnabled(boolean enabled) {
        binding.editText.setEnabled(enabled);
    }

    public void setMaxLength(int maxLength) {
        this.maxLength = maxLength;

        android.text.InputFilter[] existingFilters = binding.editText.getFilters();
        android.text.InputFilter[] newFilters;

        if (maxLength > 0) {
            newFilters = new android.text.InputFilter[existingFilters.length + 1];
            System.arraycopy(existingFilters, 0, newFilters, 0, existingFilters.length);
            newFilters[existingFilters.length] = new android.text.InputFilter.LengthFilter(maxLength);
        } else {
            newFilters = existingFilters;
        }
        binding.editText.setFilters(newFilters);
        binding.editText.addTextChangedListener(new TextWatcher() {
            @Override
            public void beforeTextChanged(CharSequence s, int start, int count, int after) {
            }

            @Override
            public void onTextChanged(CharSequence s, int start, int before, int count) {
                if (RoundedEditTextView.this.maxLength > 0 && s.length() >= RoundedEditTextView.this.maxLength) {
                }
            }

            @Override
            public void afterTextChanged(Editable s) {
            }
        });
    }
}
