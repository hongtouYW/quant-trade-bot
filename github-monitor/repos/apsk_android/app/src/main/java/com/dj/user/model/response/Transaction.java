package com.dj.user.model.response;

import com.dj.user.enums.CreditTransactionType;
import com.dj.user.util.StringUtil;

public class Transaction {
    private String transactiontype;
    private long credit_id;
    private long member_id;
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
    private String invoiceno;
    private String title;
    private BankAccount bankaccount;

    // Yxi
    private long gamepoint_id;
    private long gamemember_id;
    private String ip;
    private String start_on;
    private String end_on;
    private Player gamemember;

    // History
    private long gamelog_id;
    private String gamelogtarget_id;
    private long game_id;
    private String tableid;
    private String betamount;
    private String winloss;
    private String remark;
    private String error;
    private String startdt;
    private String enddt;
    private String game_name;
    private Yxi game;

    public String getTransactiontype() {
        return transactiontype;
    }

    public void setTransactiontype(String transactiontype) {
        this.transactiontype = transactiontype;
    }

    public String getCredit_id() {
        return String.valueOf(credit_id);
    }

    public void setCredit_id(int credit_id) {
        this.credit_id = credit_id;
    }

    public String getMember_id() {
        return String.valueOf(member_id);
    }

    public void setMember_id(long member_id) {
        this.member_id = member_id;
    }

    public String getShop_id() {
        return String.valueOf(shop_id);
    }

    public void setShop_id(Integer shop_id) {
        this.shop_id = shop_id;
    }

    public String getPayment_id() {
        return String.valueOf(payment_id);
    }

    public void setPayment_id(int payment_id) {
        this.payment_id = payment_id;
    }

    public String getType() {
        return type;
    }

    public void setType(String type) {
        this.type = type;
    }

    public double getAmount() {
        try {
            return Double.parseDouble(amount);
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setAmount(String amount) {
        this.amount = amount;
    }

    public double getBefore_balance() {
        try {
            return Double.parseDouble(before_balance);
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setBefore_balance(String before_balance) {
        this.before_balance = before_balance;
    }

    public double getAfter_balance() {
        try {
            return Double.parseDouble(after_balance);
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setAfter_balance(String after_balance) {
        this.after_balance = after_balance;
    }

    public String getReason() {
        return reason;
    }

    public void setReason(String reason) {
        this.reason = reason;
    }

    public String getSubmit_on() {
        return submit_on;
    }

    public void setSubmit_on(String submit_on) {
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

    public String getCreated_on() {
        return created_on;
    }

    public void setCreated_on(String created_on) {
        this.created_on = created_on;
    }

    public String getUpdated_on() {
        return updated_on;
    }

    public void setUpdated_on(String updated_on) {
        this.updated_on = updated_on;
    }

    public String getInvoiceno() {
        return invoiceno;
    }

    public void setInvoiceno(String invoiceno) {
        this.invoiceno = invoiceno;
    }

    public String getTitle() {
        return title;
    }

    public void setTitle(String title) {
        this.title = title;
    }

    public BankAccount getBankaccount() {
        return bankaccount;
    }

    public void setBankaccount(BankAccount bankaccount) {
        this.bankaccount = bankaccount;
    }

    public void setCredit_id(long credit_id) {
        this.credit_id = credit_id;
    }

    public void setShop_id(long shop_id) {
        this.shop_id = shop_id;
    }

    public void setPayment_id(long payment_id) {
        this.payment_id = payment_id;
    }

    public String getGamepoint_id() {
        return String.valueOf(gamepoint_id);
    }

    public void setGamepoint_id(long gamepoint_id) {
        this.gamepoint_id = gamepoint_id;
    }

    public String getGamemember_id() {
        return String.valueOf(gamemember_id);
    }

    public void setGamemember_id(long gamemember_id) {
        this.gamemember_id = gamemember_id;
    }

    public String getIp() {
        return ip;
    }

    public void setIp(String ip) {
        this.ip = ip;
    }

    public String getStart_on() {
        return start_on;
    }

    public void setStart_on(String start_on) {
        this.start_on = start_on;
    }

    public String getEnd_on() {
        return end_on;
    }

    public void setEnd_on(String end_on) {
        this.end_on = end_on;
    }

    public Player getGamemember() {
        return gamemember;
    }

    public void setGamemember(Player gamemember) {
        this.gamemember = gamemember;
    }

    public long getGamelog_id() {
        return gamelog_id;
    }

    public void setGamelog_id(long gamelog_id) {
        this.gamelog_id = gamelog_id;
    }

    public String getGamelogtarget_id() {
        return gamelogtarget_id;
    }

    public void setGamelogtarget_id(String gamelogtarget_id) {
        this.gamelogtarget_id = gamelogtarget_id;
    }

    public String getGame_id() {
        return String.valueOf(game_id);
    }

    public void setGame_id(long game_id) {
        this.game_id = game_id;
    }

    public String getTableid() {
        return tableid;
    }

    public void setTableid(String tableid) {
        this.tableid = tableid;
    }

    public double getBetamount() {
        try {
            return Double.parseDouble(betamount);
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setBetamount(String betamount) {
        this.betamount = betamount;
    }

    public double getWinloss() {
        try {
            return Double.parseDouble(winloss);
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setWinloss(String winloss) {
        this.winloss = winloss;
    }

    public String getRemark() {
        return remark;
    }

    public void setRemark(String remark) {
        this.remark = remark;
    }

    public String getError() {
        return error;
    }

    public void setError(String error) {
        this.error = error;
    }

    public String getStartdt() {
        return startdt;
    }

    public void setStartdt(String startdt) {
        this.startdt = startdt;
    }

    public String getEnddt() {
        return enddt;
    }

    public void setEnddt(String enddt) {
        this.enddt = enddt;
    }

    public String getGame_name() {
        return game_name;
    }

    public void setGame_name(String game_name) {
        this.game_name = game_name;
    }

    public Yxi getGame() {
        return game;
    }

    public void setGame(Yxi game) {
        this.game = game;
    }

    public String getYxiProviderName() {
        if (gamemember == null || gamemember.getProvider() == null) {
            return "-";
        }
        return gamemember.getYxiProviderName();
    }

    public CreditTransactionType getTransactionType() {
        if (type.equalsIgnoreCase("deposit")) {
            if (shop_id != 0) {
                return CreditTransactionType.SHOP_TOP_UP;
            } else {
                return CreditTransactionType.TOP_UP;
            }
        } else if (type.equalsIgnoreCase("gamedeposit")) {
            return CreditTransactionType.YXI_TOP_UP;
        } else if (type.equalsIgnoreCase("withdraw")) {
            if (shop_id != 0) {
                return CreditTransactionType.SHOP_WITHDRAWAL;
            } else {
                if (bankaccount != null) {
                    return CreditTransactionType.WITHDRAWAL;
                } else {
                    return CreditTransactionType.SHOP_WITHDRAWAL_QR;
                }
            }
        } else if (type.equalsIgnoreCase("gamewithdraw")) {
            return CreditTransactionType.YXI_WITHDRAWAL;
        } else if (type.equalsIgnoreCase("commission")) {
            return CreditTransactionType.COMMISSION;
        } else if (type.equalsIgnoreCase("firstbonus")) {
            return CreditTransactionType.GENERAL_BONUS;
        } else if (type.equalsIgnoreCase("weeklybonus")) {
            return CreditTransactionType.WEEKLY_BONUS;
        } else if (type.equalsIgnoreCase("monthlybonus")) {
            return CreditTransactionType.MONTHLY_BONUS;
        } else if (type.equalsIgnoreCase("newmemberregister")) {
            return CreditTransactionType.NEW_REGISTER;
        } else if (type.equalsIgnoreCase("newmemberreload")) {
            return CreditTransactionType.NEW_RELOAD;
        } else if (type.equalsIgnoreCase("newmemberrecruit")) {
            return CreditTransactionType.NEW_RECRUIT;
        } else if (type.equalsIgnoreCase("newmembergamereload")) {
            return CreditTransactionType.NEW_YXI_RELOAD;
        } else {
            return CreditTransactionType.NONE;
        }
    }

    public String getYxiName() {
        if (game == null) {
            return !StringUtil.isNullOrEmpty(game_name) ? game_name : "-";
        }
        return game.getGame_name();
    }

    public String getProviderYxiName() {
        String providerName;
        if (gamemember != null && !StringUtil.isNullOrEmpty(gamemember.getYxiProviderName())) {
            providerName = gamemember.getYxiProviderName();
        } else if (game != null && !StringUtil.isNullOrEmpty(game.getGame_name())) {
            providerName = game.getGame_name();
        } else {
            providerName = StringUtil.isNullOrEmpty(game_name) ? "-" : game_name;
        }
        String yxiName = StringUtil.isNullOrEmpty(remark) ? "-" : remark;
        // Both are "-" → Unknown
        if ("-".equals(providerName) && "-".equals(yxiName)) {
            return "Unknown";
        }
        // yxiName is "-" → only providerName
        if ("-".equals(yxiName)) {
            return providerName;
        }
        // Normal case
        return providerName + " (" + yxiName + ")";
    }

    public String getBankName() {
        if (bankaccount == null) {
            return "-";
        }
        return bankaccount.getBankName();
    }
}
