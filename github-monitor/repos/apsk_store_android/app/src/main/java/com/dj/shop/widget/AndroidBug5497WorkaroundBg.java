package com.dj.shop.widget;

import android.app.Activity;
import android.graphics.Rect;
import android.view.View;
import android.widget.FrameLayout;

import com.dj.shop.R;

public class AndroidBug5497WorkaroundBg {

    // For more information, see https://issuetracker.google.com/issues/36911528
    // To use this class, simply invoke assistActivity() on an Activity that already has its content view set.

    private int usableHeightPrevious;

    //    private final Activity activity;
    private final View mChildOfContent;
    private final FrameLayout.LayoutParams frameLayoutParams;

    public static void assistActivity(Activity activity) {
        new AndroidBug5497WorkaroundBg(activity);
    }

    private AndroidBug5497WorkaroundBg(Activity activity) {
//        this.activity = activity;
        FrameLayout content = activity.findViewById(R.id.content_container);
        mChildOfContent = content.getChildAt(0);
        mChildOfContent.getViewTreeObserver().addOnGlobalLayoutListener(this::possiblyResizeChildOfContent);
        frameLayoutParams = (FrameLayout.LayoutParams) mChildOfContent.getLayoutParams();
    }

    private void possiblyResizeChildOfContent() {
        int usableHeightNow = computeUsableHeight();
        if (usableHeightNow != usableHeightPrevious) {
            int usableHeightSansKeyboard = mChildOfContent.getRootView().getHeight();
            int heightDifference = usableHeightSansKeyboard - usableHeightNow;
            if (heightDifference > (usableHeightSansKeyboard / 4)) {
                // keyboard probably just became visible
                frameLayoutParams.height = usableHeightSansKeyboard - heightDifference;
            } else {
                // keyboard probably just became hidden
                frameLayoutParams.height = usableHeightNow;
            }
            mChildOfContent.requestLayout();
            usableHeightPrevious = usableHeightNow;
        }
    }

    private int computeUsableHeight() {
        Rect rect = new Rect();
        mChildOfContent.getWindowVisibleDisplayFrame(rect);
        return (rect.bottom - rect.top); // + (int) activity.getResources().getDimension(R.dimen.height_status_bar);
    }
}
