package com.dj.shop.model;

import com.dj.shop.enums.ActionType;

public class SuccessConfig {
    public String icon;
    public String titleFormat;
    public Object[] titleArgs;

    public String messageFormat;
    public Object[] messageArgs;

    public int imageResId;

    public String solidButtonText;
    public SuccessAction solidButtonAction;

    public String borderedButtonText;
    public SuccessAction borderedButtonAction;

    public String link1Text;
    public SuccessAction link1Action;
    public String link2Text;
    public SuccessAction link2Action;
    public ActionType actionType;

    public enum SuccessAction {
        TOP_UP,
        HOME,
        PRINT,
        AGAIN,
        BACK,
        NONE
    }

    public SuccessConfig(String titleFormat, Object[] titleArgs,
                         String messageFormat, Object[] messageArgs,
                         int imageResId,
                         String solidButtonText, SuccessAction solidButtonAction,
                         String borderedButtonText, SuccessAction borderedButtonAction,
                         String link1Text, SuccessAction link1Action) {
        this.titleFormat = titleFormat;
        this.titleArgs = titleArgs;
        this.messageFormat = messageFormat;
        this.messageArgs = messageArgs;
        this.imageResId = imageResId;
        this.solidButtonText = solidButtonText;
        this.solidButtonAction = solidButtonAction;
        this.borderedButtonText = borderedButtonText;
        this.borderedButtonAction = borderedButtonAction;
        this.link1Text = link1Text;
        this.link1Action = link1Action;
    }

    public SuccessConfig(String titleFormat, Object[] titleArgs,
                         String messageFormat, Object[] messageArgs,
                         int imageResId,
                         String solidButtonText, SuccessAction solidButtonAction,
                         String borderedButtonText, SuccessAction borderedButtonAction,
                         String link1Text, SuccessAction link1Action,
                         ActionType actionType) {
        this.titleFormat = titleFormat;
        this.titleArgs = titleArgs;
        this.messageFormat = messageFormat;
        this.messageArgs = messageArgs;
        this.imageResId = imageResId;
        this.solidButtonText = solidButtonText;
        this.solidButtonAction = solidButtonAction;
        this.borderedButtonText = borderedButtonText;
        this.borderedButtonAction = borderedButtonAction;
        this.link1Text = link1Text;
        this.link1Action = link1Action;
        this.actionType = actionType;
    }

    public SuccessConfig(String icon, String titleFormat, Object[] titleArgs,
                         String messageFormat, Object[] messageArgs,
                         int imageResId,
                         String solidButtonText, SuccessAction solidButtonAction,
                         String borderedButtonText, SuccessAction borderedButtonAction,
                         String link1Text, SuccessAction link1Action,
                         ActionType actionType) {
        this.icon = icon;
        this.titleFormat = titleFormat;
        this.titleArgs = titleArgs;
        this.messageFormat = messageFormat;
        this.messageArgs = messageArgs;
        this.imageResId = imageResId;
        this.solidButtonText = solidButtonText;
        this.solidButtonAction = solidButtonAction;
        this.borderedButtonText = borderedButtonText;
        this.borderedButtonAction = borderedButtonAction;
        this.link1Text = link1Text;
        this.link1Action = link1Action;
        this.actionType = actionType;
    }

    public SuccessConfig(String titleFormat, Object[] titleArgs,
                         String messageFormat, Object[] messageArgs,
                         int imageResId,
                         String solidButtonText, SuccessAction solidButtonAction,
                         String borderedButtonText, SuccessAction borderedButtonAction,
                         String link1Text, SuccessAction link1Action,
                         String link2Text, SuccessAction link2Action,
                         ActionType actionType) {
        this.titleFormat = titleFormat;
        this.titleArgs = titleArgs;
        this.messageFormat = messageFormat;
        this.messageArgs = messageArgs;
        this.imageResId = imageResId;
        this.solidButtonText = solidButtonText;
        this.solidButtonAction = solidButtonAction;
        this.borderedButtonText = borderedButtonText;
        this.borderedButtonAction = borderedButtonAction;
        this.link1Text = link1Text;
        this.link1Action = link1Action;
        this.link2Text = link2Text;
        this.link2Action = link2Action;
        this.actionType = actionType;
    }

    public SuccessConfig(String icon, String titleFormat, Object[] titleArgs,
                         String messageFormat, Object[] messageArgs,
                         int imageResId,
                         String solidButtonText, SuccessAction solidButtonAction,
                         String borderedButtonText, SuccessAction borderedButtonAction,
                         String link1Text, SuccessAction link1Action,
                         String link2Text, SuccessAction link2Action,
                         ActionType actionType) {
        this.icon = icon;
        this.titleFormat = titleFormat;
        this.titleArgs = titleArgs;
        this.messageFormat = messageFormat;
        this.messageArgs = messageArgs;
        this.imageResId = imageResId;
        this.solidButtonText = solidButtonText;
        this.solidButtonAction = solidButtonAction;
        this.borderedButtonText = borderedButtonText;
        this.borderedButtonAction = borderedButtonAction;
        this.link1Text = link1Text;
        this.link1Action = link1Action;
        this.link2Text = link2Text;
        this.link2Action = link2Action;
        this.actionType = actionType;
    }
}
