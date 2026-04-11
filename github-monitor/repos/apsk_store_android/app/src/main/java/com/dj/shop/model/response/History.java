package com.dj.shop.model.response;

import androidx.annotation.Nullable;

import com.dj.shop.enums.TransactionStatus;
import com.dj.shop.enums.TransactionType;
import com.dj.shop.util.StringUtil;

public class History {
    private String title;
    private long member_id;
    private String created_on;
    private String transactiontype;
    private String type;
    private String phone;

    private long gamemember_id;
    private String name;
    private long provider_id;
    private String provider_name;

    private String member_name;
    private String amount;
    private int status;
    private long payment_id;
    private String payment_name;

    private long manager_id;
    private String manager_name;

    public String getTitle() {
        return title;
    }

    public void setTitle(String title) {
        this.title = title;
    }

    public String getMember_id() {
        return String.valueOf(member_id);
    }

    public void setMember_id(long member_id) {
        this.member_id = member_id;
    }

    public String getCreated_on() {
        return created_on;
    }

    public void setCreated_on(String created_on) {
        this.created_on = created_on;
    }

    public String getTransactiontype() {
        return transactiontype;
    }

    public void setTransactiontype(String transactiontype) {
        this.transactiontype = transactiontype;
    }

    public String getType() {
        return type;
    }

    public void setType(String type) {
        this.type = type;
    }

    public String getPhone() {
        return phone;
    }

    public void setPhone(String phone) {
        this.phone = phone;
    }

    public String getGamemember_id() {
        return String.valueOf(gamemember_id);
    }

    public void setGamemember_id(long gamemember_id) {
        this.gamemember_id = gamemember_id;
    }

    public String getName() {
        return name;
    }

    public void setName(String name) {
        this.name = name;
    }

    public String getProvider_id() {
        return String.valueOf(provider_id);
    }

    public void setProvider_id(long provider_id) {
        this.provider_id = provider_id;
    }

    public String getProvider_name() {
        return provider_name;
    }

    public void setProvider_name(String provider_name) {
        this.provider_name = provider_name;
    }

    public String getMember_name() {
        return member_name;
    }

    public void setMember_name(String member_name) {
        this.member_name = member_name;
    }

    public String getAmountStr() {
        return amount;
    }

    public double getAmount() {
        try {
            return Double.parseDouble(amount.replaceAll(",", ""));
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setAmount(String amount) {
        this.amount = amount;
    }

    public int getStatus() {
        return status;
    }

    public void setStatus(int status) {
        this.status = status;
    }

    public String getPayment_id() {
        return String.valueOf(payment_id);
    }

    public void setPayment_id(long payment_id) {
        this.payment_id = payment_id;
    }

    public String getPayment_name() {
        return !StringUtil.isNullOrEmpty(payment_name) ? payment_name.toUpperCase() : "-";
    }

    public void setPayment_name(String payment_name) {
        this.payment_name = payment_name;
    }

    public String getManager_id() {
        return String.valueOf(manager_id);
    }

    public void setManager_id(long manager_id) {
        this.manager_id = manager_id;
    }

    public String getManager_name() {
        return manager_name;
    }

    public void setManager_name(String manager_name) {
        this.manager_name = manager_name;
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
