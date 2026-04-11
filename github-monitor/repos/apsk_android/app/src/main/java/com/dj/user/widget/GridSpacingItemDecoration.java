package com.dj.user.widget;

import android.graphics.Rect;
import android.view.View;

import androidx.annotation.NonNull;
import androidx.recyclerview.widget.RecyclerView;

public class GridSpacingItemDecoration extends RecyclerView.ItemDecoration {

    private final int spanCount;
    private final int horizontalSpacing;
    private final int verticalSpacing;
    private final boolean includeEdge;

    public GridSpacingItemDecoration(int spanCount, int horizontalSpacing, int verticalSpacing, boolean includeEdge) {
        this.spanCount = spanCount;
        this.horizontalSpacing = horizontalSpacing;
        this.verticalSpacing = verticalSpacing;
        this.includeEdge = includeEdge;
    }

    @Override
    public void getItemOffsets(@NonNull Rect outRect, @NonNull View view,
                               @NonNull RecyclerView parent, @NonNull RecyclerView.State state) {
        int position = parent.getChildAdapterPosition(view); // item position
        int column = position % spanCount; // item column

        if (includeEdge) {
            // Horizontal spacing (left/right)
            outRect.left = horizontalSpacing - column * horizontalSpacing / spanCount;
            outRect.right = (column + 1) * horizontalSpacing / spanCount;

            // Vertical spacing (top/bottom)
            if (position < spanCount) {
                outRect.top = verticalSpacing; // top edge
            }
            outRect.bottom = verticalSpacing; // item bottom
        } else {
            outRect.left = column * horizontalSpacing / spanCount;
            outRect.right = horizontalSpacing - (column + 1) * horizontalSpacing / spanCount;

            if (position >= spanCount) {
                outRect.top = verticalSpacing; // item top
            }
        }
    }
}
