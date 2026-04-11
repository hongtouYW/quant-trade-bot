package com.dj.manager.widget;

import android.content.Context;
import android.graphics.Typeface;
import android.util.AttributeSet;
import android.util.TypedValue;

import androidx.appcompat.widget.AppCompatButton;
import androidx.core.content.res.ResourcesCompat;

import com.dj.manager.R;

public class CustomButton extends AppCompatButton {

    public CustomButton(Context context) {
        super(context);
        init(context);
    }

    public CustomButton(Context context, AttributeSet attrs) {
        super(context, attrs);
        init(context);
    }

    public CustomButton(Context context, AttributeSet attrs, int defStyleAttr) {
        super(context, attrs, defStyleAttr);
        init(context);
    }

    private void init(Context context) {
        setHeight(dpToPx(context));
        setBackgroundResource(R.drawable.bg_button);
        setTextColor(getResources().getColor(R.color.gold_D4AF37));
        setTextSize(TypedValue.COMPLEX_UNIT_SP, 16);

        Typeface typeface = ResourcesCompat.getFont(context, R.font.poppins_semi_bold);
        if (typeface != null) {
            setTypeface(typeface);
        }
    }

    private int dpToPx(Context context) {
        float density = context.getResources().getDisplayMetrics().density;
        return (int) (65 * density + 0.5f);
    }

    public void setTextColorRes(int colorResId) {
        setTextColor(getResources().getColor(colorResId));
    }
}
