package com.dj.shop.widget;

import android.content.Context;
import android.graphics.Typeface;
import android.util.AttributeSet;
import android.util.TypedValue;

import androidx.appcompat.widget.AppCompatButton;
import androidx.core.content.res.ResourcesCompat;

import com.dj.shop.R;

public class BorderedTextButton extends AppCompatButton {

    public BorderedTextButton(Context context) {
        super(context);
        init(context);
    }

    public BorderedTextButton(Context context, AttributeSet attrs) {
        super(context, attrs);
        init(context);
    }

    public BorderedTextButton(Context context, AttributeSet attrs, int defStyleAttr) {
        super(context, attrs, defStyleAttr);
        init(context);
    }

    private void init(Context context) {
        setHeight(dpToPx(context));
        setBackgroundResource(R.drawable.bg_button_bordered);
        setTextColor(getResources().getColor(R.color.blue_4876C9));
        setTextSize(TypedValue.COMPLEX_UNIT_SP, 16);

        Typeface typeface = ResourcesCompat.getFont(context, R.font.poppins_bold);
        if (typeface != null) {
            setTypeface(typeface);
        }
    }

    private int dpToPx(Context context) {
        float density = context.getResources().getDisplayMetrics().density;
        return (int) (56 * density + 0.5f);
    }

    public void setTextWhite() {
        setBackgroundResource(R.drawable.bg_button_bordered_white);
        setTextColor(getResources().getColor(android.R.color.white));
    }
}
