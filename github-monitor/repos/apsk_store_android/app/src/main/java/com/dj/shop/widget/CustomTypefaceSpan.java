package com.dj.shop.widget;

import android.graphics.Paint;
import android.graphics.Typeface;
import android.text.TextPaint;
import android.text.style.MetricAffectingSpan;

public class CustomTypefaceSpan extends MetricAffectingSpan {

    private final Typeface typeface;

    public CustomTypefaceSpan(Typeface typeface) {
        this.typeface = typeface;
    }

    @Override
    public void updateDrawState(TextPaint ds) {
        apply(ds);
    }

    @Override
    public void updateMeasureState(TextPaint paint) {
        apply(paint);
    }

    private void apply(Paint paint) {
        Typeface old = paint.getTypeface();
        int oldStyle = old != null ? old.getStyle() : 0;
        int fake = oldStyle & ~typeface.getStyle();
        if ((fake & Typeface.BOLD) != 0) paint.setFakeBoldText(true);
        if ((fake & Typeface.ITALIC) != 0) paint.setTextSkewX(-0.25f);
        paint.setTypeface(typeface);
    }
}
