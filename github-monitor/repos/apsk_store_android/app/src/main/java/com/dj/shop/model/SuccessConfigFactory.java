package com.dj.shop.model;

import android.content.Context;

import com.dj.shop.R;
import com.dj.shop.enums.ActionType;
import com.dj.shop.util.FormatUtils;

public class SuccessConfigFactory {

    public static SuccessConfig createTopUpSuccess(Context context, String id, double topUpAmount, double newBalance) {
        return new SuccessConfig(
                context.getString(R.string.success_action_top_up_title),
                new Object[]{FormatUtils.formatAmount(topUpAmount)},
                null, new Object[]{id, String.format(context.getString(R.string.template_currency_amount), FormatUtils.formatAmount(newBalance))},
                R.drawable.ic_success_tick,
                context.getString(R.string.success_action_home), SuccessConfig.SuccessAction.HOME,
                context.getString(R.string.success_action_print), SuccessConfig.SuccessAction.PRINT,
                context.getString(R.string.success_action_top_up_another), SuccessConfig.SuccessAction.AGAIN,
                ActionType.TOP_UP
        );
    }

    public static SuccessConfig createWithdrawalSuccess(Context context, String id, double withdrawalAmount, double newBalance) {
        return new SuccessConfig(
                context.getString(R.string.success_action_withdraw_title),
                new Object[]{FormatUtils.formatAmount(withdrawalAmount)},
                null, new Object[]{id, String.format(context.getString(R.string.template_currency_amount), FormatUtils.formatAmount(newBalance))},
                R.drawable.ic_success_tick,
                context.getString(R.string.success_action_home), SuccessConfig.SuccessAction.HOME,
                null, null,
                context.getString(R.string.success_action_withdraw_another), SuccessConfig.SuccessAction.AGAIN,
                ActionType.WITHDRAWAL
        );
    }

    public static SuccessConfig createNewUserSuccess(Context context, String phone, String password, String id) {
        return new SuccessConfig(
                context.getString(R.string.success_action_create_user_title), null,
                context.getString(R.string.success_action_create_user_desc), new Object[]{phone, password, id},
                R.drawable.ic_success_tick,
                context.getString(R.string.success_action_top_up), SuccessConfig.SuccessAction.TOP_UP,
                context.getString(R.string.success_action_print), SuccessConfig.SuccessAction.PRINT,
                context.getString(R.string.success_action_home), SuccessConfig.SuccessAction.HOME,
                context.getString(R.string.success_action_create_user_another), SuccessConfig.SuccessAction.BACK,
                ActionType.CREATE_USER
        );
    }

    public static SuccessConfig createNewUserRandomSuccess(Context context, String phone, String password, String id) {
        return new SuccessConfig(
                context.getString(R.string.success_action_create_user_random_title), null,
                null, new Object[]{phone, password, id},
                R.drawable.ic_success_tick,
                context.getString(R.string.success_action_top_up), SuccessConfig.SuccessAction.TOP_UP,
                context.getString(R.string.success_action_print), SuccessConfig.SuccessAction.PRINT,
                context.getString(R.string.success_action_home), SuccessConfig.SuccessAction.HOME,
                context.getString(R.string.success_action_create_user_another), SuccessConfig.SuccessAction.BACK,
                ActionType.CREATE_USER_RANDOM
        );
    }

    public static SuccessConfig createChangePasswordSuccess(Context context, String phone, String password) {
        return new SuccessConfig(
                context.getString(R.string.success_action_change_password_title), null,
                context.getString(R.string.success_action_change_password_desc), new Object[]{phone, password},
                R.drawable.ic_success_tick,
                context.getString(R.string.success_action_home), SuccessConfig.SuccessAction.HOME,
                null, null,
                context.getString(R.string.success_action_change_password_another), SuccessConfig.SuccessAction.BACK,
                ActionType.CHANGE_PASSWORD
        );
    }

    public static SuccessConfig createFeedbackSuccess(Context context) {
        return new SuccessConfig(
                context.getString(R.string.success_action_feedback_title), null,
                context.getString(R.string.success_action_feedback_desc), null,
                R.drawable.ic_success_feedback,
                context.getString(R.string.success_action_home), SuccessConfig.SuccessAction.HOME,
                null, null, null, null
        );
    }

    public static SuccessConfig createConnectPrinterSuccess(Context context) {
        return new SuccessConfig(
                context.getString(R.string.success_action_connect_printer_title), null,
                context.getString(R.string.success_action_connect_printer_desc), null,
                R.drawable.ic_success_tick,
                context.getString(R.string.success_action_home), SuccessConfig.SuccessAction.BACK,
                null, null, null, null,
                ActionType.CONNECT_PRINTER
        );
    }

    public static SuccessConfig createNewYxiUserSuccess(Context context, String memberId, String memberPass, String yxiIcon, String providerId, String playerId, String yxiName, String playerLogin, String playerPass) {
        return new SuccessConfig(
                yxiIcon,
                context.getString(R.string.success_action_create_player_title), null,
                null, new Object[]{memberId, memberPass, providerId, playerId, yxiName, playerLogin, playerPass},
                0,
                context.getString(R.string.success_action_top_up), SuccessConfig.SuccessAction.TOP_UP,
                context.getString(R.string.success_action_print), SuccessConfig.SuccessAction.PRINT,
                context.getString(R.string.success_action_home), SuccessConfig.SuccessAction.HOME,
                context.getString(R.string.success_action_create_player_another), SuccessConfig.SuccessAction.BACK,
                ActionType.CREATE_USER
        );
    }

    public static SuccessConfig createYxiTopUpSuccess(Context context, String yxiIcon, String yxiName, String yxiUserId, double topUpAmount, double balance, double yxiBalance) {
        return new SuccessConfig(
                yxiIcon,
                context.getString(R.string.success_action_top_up_title), new Object[]{FormatUtils.formatAmount(topUpAmount)},
                null, new Object[]{yxiName, yxiUserId, String.format(context.getString(R.string.template_currency_amount), FormatUtils.formatAmount(balance)), String.format("RM %s", FormatUtils.formatAmount(yxiBalance))},
                0,
                context.getString(R.string.success_action_home), SuccessConfig.SuccessAction.HOME,
                context.getString(R.string.success_action_print), SuccessConfig.SuccessAction.PRINT,
                context.getString(R.string.success_action_top_up_another), SuccessConfig.SuccessAction.BACK,
                ActionType.TOP_UP
        );
    }

    public static SuccessConfig createYxiTWithdrawalSuccess(Context context, String yxiIcon, String yxiName, String yxiUserId, double withdrawalAmount, double balance, double yxiBalance) {
        return new SuccessConfig(
                yxiIcon,
                context.getString(R.string.success_action_withdraw_title), new Object[]{FormatUtils.formatAmount(withdrawalAmount)},
                null, new Object[]{yxiName, yxiUserId, String.format(context.getString(R.string.template_currency_amount), FormatUtils.formatAmount(balance)), String.format("RM %s", FormatUtils.formatAmount(yxiBalance))},
                0,
                context.getString(R.string.success_action_home), SuccessConfig.SuccessAction.HOME,
                null, null,
                context.getString(R.string.success_action_withdraw_another), SuccessConfig.SuccessAction.BACK,
                ActionType.WITHDRAWAL
        );
    }
}
