package com.dj.manager.util;

public interface ApiCallback<T> {
    void onSuccess(T response);

    // Return true if handled, false to fallback to default
    boolean onApiError(int code, String message);

    // Return true if handled, false to fallback to default
    boolean onFailure(Throwable t);
}
