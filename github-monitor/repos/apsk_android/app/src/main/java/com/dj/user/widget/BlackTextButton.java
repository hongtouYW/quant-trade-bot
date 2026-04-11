package com.dj.user.widget;

import android.content.Context;
import android.graphics.Typeface;
import android.util.AttributeSet;
import android.util.TypedValue;

import androidx.appcompat.widget.AppCompatButton;
import androidx.core.content.res.ResourcesCompat;

import com.dj.user.R;

public class BlackTextButton extends AppCompatButton {

    public BlackTextButton(Context context) {
        super(context);
        init(context);
    }

    public BlackTextButton(Context context, AttributeSet attrs) {
        super(context, attrs);
        init(context);
    }

    public BlackTextButton(Context context, AttributeSet attrs, int defStyleAttr) {
        super(context, attrs, defStyleAttr);
        init(context);
    }

    private void init(Context context) {
        setHeight(dpToPx(context));
        setBackgroundResource(R.drawable.bg_gradient_pill_button);
        setTextColor(getResources().getColor(R.color.black_000000));
        setTextSize(TypedValue.COMPLEX_UNIT_SP, 14);
        setAllCaps(false);

        Typeface typeface = ResourcesCompat.getFont(context, R.font.pingfang_sc_semi_bold);
        if (typeface != null) {
            setTypeface(typeface);
        }
    }

    private int dpToPx(Context context) {
        float density = context.getResources().getDisplayMetrics().density;
        return (int) (40 * density + 0.5f);
    }

    public void setTextColorRes(int colorResId) {
        setTextColor(getResources().getColor(colorResId));
    }
}
