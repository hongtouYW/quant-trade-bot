package com.dj.shop.model.response;

import androidx.annotation.Nullable;

import com.dj.shop.enums.TransactionStatus;
import com.dj.shop.enums.TransactionType;
import com.dj.shop.util.StringUtil;

public class Transaction {
    private String transactiontype;
    private long credit_id;
    private String member_id;
    private long shop_id;
    private long payment_id;
    private String type;
    private String amount;
    private String before_balance;
    private String after_balance;
    private String reason;
    private String submit_on;
    private int status;
    private int delete;
    private String created_on;
    private String updated_on;

    // Yxi
    private long gamepoint_id;
    private long gamemember_id;
    private String ip;
    private String start_on;
    private String end_on;
    private Player player;

    // Shop
    private long shopcredit_id;
    private long manager_id;

    // From history
    private String title;
    private String displayId;
    private String searchId;
    private String paymentMethod;
    private String providerId;

    @Nullable
    public String getTransactiontype() {
        return transactiontype;
    }

    public void setTransactiontype(@Nullable String transactiontype) {
        this.transactiontype = transactiontype;
    }

    @Nullable
    public String getCredit_id() {
        return String.valueOf(credit_id);
    }

    public void setCredit_id(int credit_id) {
        this.credit_id = credit_id;
    }

    @Nullable
    public String getMember_id() {
        return member_id;
    }

    public void setMember_id(String member_id) {
        this.member_id = member_id;
    }

    @Nullable
    public String getShop_id() {
        return String.valueOf(shop_id);
    }

    public void setShop_id(long shop_id) {
        this.shop_id = shop_id;
    }

    @Nullable
    public String getPayment_id() {
        return String.valueOf(payment_id);
    }

    public void setPayment_id(long payment_id) {
        this.payment_id = payment_id;
    }

    @Nullable
    public String getType() {
        return type;
    }

    public void setType(String type) {
        this.type = type;
    }

    public double getAmount() {
        try {
            return Double.parseDouble(amount.replaceAll(",", ""));
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setAmount(@Nullable String amount) {
        this.amount = amount;
    }

    public double getBefore_balance() {
        try {
            return Double.parseDouble(before_balance.replaceAll(",", ""));
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setBefore_balance(@Nullable String before_balance) {
        this.before_balance = before_balance;
    }

    public double getAfter_balance() {
        try {
            return Double.parseDouble(after_balance.replaceAll(",", ""));
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setAfter_balance(@Nullable String after_balance) {
        this.after_balance = after_balance;
    }

    @Nullable
    public String getReason() {
        return reason;
    }

    public void setReason(@Nullable String reason) {
        this.reason = reason;
    }

    @Nullable
    public String getSubmit_on() {
        return submit_on;
    }

    public void setSubmit_on(@Nullable String submit_on) {
        this.submit_on = submit_on;
    }

    public int getStatus() {
        return status;
    }

    public void setStatus(int status) {
        this.status = status;
    }

    public int getDelete() {
        return delete;
    }

    public void setDelete(int delete) {
        this.delete = delete;
    }

    @Nullable
    public String getCreated_on() {
        return created_on;
    }

    public void setCreated_on(@Nullable String created_on) {
        this.created_on = created_on;
    }

    @Nullable
    public String getUpdated_on() {
        return updated_on;
    }

    public void setUpdated_on(@Nullable String updated_on) {
        this.updated_on = updated_on;
    }

    @Nullable
    public String getGamepoint_id() {
        return String.valueOf(gamepoint_id);
    }

    public void setGamepoint_id(long gamepoint_id) {
        this.gamepoint_id = gamepoint_id;
    }

    @Nullable
    public String getGamemember_id() {
        return String.valueOf(gamemember_id);
    }

    public void setGamemember_id(long gamemember_id) {
        this.gamemember_id = gamemember_id;
    }

    @Nullable
    public String getIp() {
        return ip;
    }

    public void setIp(@Nullable String ip) {
        this.ip = ip;
    }

    @Nullable
    public String getStart_on() {
        return start_on;
    }

    public void setStart_on(@Nullable String start_on) {
        this.start_on = start_on;
    }

    @Nullable
    public String getEnd_on() {
        return end_on;
    }

    public void setEnd_on(@Nullable String end_on) {
        this.end_on = end_on;
    }

    @Nullable
    public Player getPlayer() {
        return player;
    }

    public void setPlayer(@Nullable Player player) {
        this.player = player;
    }

    public String getShopcredit_id() {
        return String.valueOf(shopcredit_id);
    }

    public void setShopcredit_id(long shopcredit_id) {
        this.shopcredit_id = shopcredit_id;
    }

    public String getManager_id() {
        return String.valueOf(manager_id);
    }

    public void setManager_id(long manager_id) {
        this.manager_id = manager_id;
    }

    public String getTitle() {
        return title;
    }

    public void setTitle(String title) {
        this.title = title;
    }

    public String getDisplayId() {
        return displayId;
    }

    public void setDisplayId(String displayId) {
        this.displayId = displayId;
    }

    public String getSearchId() {
        return searchId;
    }

    public void setSearchId(String searchId) {
        this.searchId = searchId;
    }

    public String getPaymentMethod() {
        return paymentMethod;
    }

    public void setPaymentMethod(String paymentMethod) {
        this.paymentMethod = paymentMethod;
    }

    public String getProviderId() {
        return providerId;
    }

    public void setProviderId(String providerId) {
        this.providerId = providerId;
    }

    @Nullable
    public String getYxiProviderId() {
        if (player == null) {
            return "";
        }
        return !StringUtil.isNullOrEmpty(player.getProvider_id()) ? player.getProvider_id() : "";
    }

    @Nullable
    public String getYxiName() {
        if (player == null) {
            return "";
        }
        return player.getProviderName();
    }

    public String getPlayerId() {
        if (player == null) {
            return "";
        }
        return !StringUtil.isNullOrEmpty(player.getGamemember_id()) ? player.getGamemember_id() : "";
    }

    @Nullable
    public TransactionType getTransactionType() {
        if (transactiontype.equalsIgnoreCase("credit")) {
            if (type.equalsIgnoreCase("deposit")) {
                return TransactionType.TOP_UP;
            } else { // withdraw
                return TransactionType.WITHDRAWAL;
            }
        } else if (transactiontype.equalsIgnoreCase("member")) {
            if (type.equalsIgnoreCase("register")) {
                return TransactionType.USER;
            } else if (type.equalsIgnoreCase("reload") || type.equalsIgnoreCase("deposit")) {
                return TransactionType.TOP_UP;
            } else if (type.equalsIgnoreCase("withdraw")) { // withdraw
                return TransactionType.WITHDRAWAL;
            }
        } else if (transactiontype.equalsIgnoreCase("game")) {
            if (type.equalsIgnoreCase("reload") || type.equalsIgnoreCase("deposit")) {
                return TransactionType.YXI_TOP_UP;
            } else if (type.equalsIgnoreCase("withdraw")) { // withdraw
                return TransactionType.YXI_WITHDRAWAL;
            }
        } else if (transactiontype.equalsIgnoreCase("shop")) {
            if (type.equalsIgnoreCase("clear") || type.equalsIgnoreCase("shopcredit.clear")) {
                return TransactionType.MANAGER_SETTLEMENT;
            } else if (type.equalsIgnoreCase("limit") || type.equalsIgnoreCase("shopcredit.limit")) {
                return TransactionType.MANAGER_TOP_UP;
            }
        } else if (transactiontype.equalsIgnoreCase("randommember")) {
            if (type.equalsIgnoreCase("register")) {
                return TransactionType.RANDOM_USER;
            }
        } else if (transactiontype.equalsIgnoreCase("player")) {
            if (type.equalsIgnoreCase("register")) {
                return TransactionType.PLAYER_USER;
            }
        }
        return TransactionType.UNKNOWN;
    }

    @Nullable
    public TransactionStatus getTransactionStatus() {
        if (status == 1) {
            return TransactionStatus.SUCCESS;
        }
        return TransactionStatus.FAILED;
    }
}
