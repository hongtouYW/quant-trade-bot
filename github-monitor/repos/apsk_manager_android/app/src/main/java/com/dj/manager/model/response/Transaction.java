package com.dj.manager.model.response;

import androidx.annotation.Nullable;

import com.dj.manager.enums.TransactionStatus;
import com.dj.manager.enums.TransactionType;
import com.dj.manager.util.StringUtil;

public class Transaction {
    private String transactiontype;
    private long credit_id;
    private long member_id;
    private long shop_id;
    private long payment_id;
    private String phone;
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
    private Player gamemember;
    private Yxi game;

    private String prefixadmin;
    private String prefixmanager;
    private String prefixshop;

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

    public String getPhone() {
        return phone;
    }

    public void setPhone(String phone) {
        this.phone = phone;
    }

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

    public void setAmount(String amount) {
        this.amount = amount;
    }

    public double getBefore_balance() {
        try {
            return Double.parseDouble(before_balance.replaceAll(",", ""));
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setBefore_balance(String before_balance) {
        this.before_balance = before_balance;
    }

    public double getAfter_balance() {
        try {
            return Double.parseDouble(after_balance.replaceAll(",", ""));
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

    public Player getPlayer() {
        return player;
    }

    public void setPlayer(Player player) {
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

    public Player getGamemember() {
        return gamemember;
    }

    public void setGamemember(Player gamemember) {
        this.gamemember = gamemember;
    }

    public Yxi getGame() {
        return game;
    }

    public void setGame(Yxi game) {
        this.game = game;
    }

    public String getPrefixadmin() {
        return prefixadmin;
    }

    public void setPrefixadmin(String prefixadmin) {
        this.prefixadmin = prefixadmin;
    }

    public String getPrefixmanager() {
        return prefixmanager;
    }

    public void setPrefixmanager(String prefixmanager) {
        this.prefixmanager = prefixmanager;
    }

    public String getPrefixshop() {
        return prefixshop;
    }

    public void setPrefixshop(String prefixshop) {
        this.prefixshop = prefixshop;
    }

    public String getYxiId() {
        if (player == null) {
            return "";
        }
        return !StringUtil.isNullOrEmpty(player.getGame_id()) ? player.getGame_id() : "";
    }

    public String getProviderName() {
        if (player == null) {
            return "";
        }
        return player.getProviderName();
    }

    public String getProviderIcon() {
        if (gamemember == null || gamemember.getProvider() == null || StringUtil.isNullOrEmpty(gamemember.getProvider().getIcon())) {
            return "";
        }
        return gamemember.getProvider().getIcon();
    }

    public String getProviderYxiName() {
        String providerName;
        if (gamemember != null && !StringUtil.isNullOrEmpty(gamemember.getProviderName())) {
            providerName = gamemember.getProviderName();
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

    public String getPlayerLogin() {
        if (player == null) {
            return "";
        }
        return !StringUtil.isNullOrEmpty(player.getLoginId()) ? player.getLoginId() : getPlayerId();
    }

    public String getPlayerId() {
        if (player == null) {
            return "";
        }
        return !StringUtil.isNullOrEmpty(player.getGamemember_id()) ? player.getGamemember_id() : "";
    }

    public TransactionType getTransactionType() {
        if (transactiontype.equalsIgnoreCase("member")) {
            if (type.equalsIgnoreCase("reload") || type.equalsIgnoreCase("deposit")) {
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
            } else if (type.equalsIgnoreCase("shopcredit.userdelete")) {
                return TransactionType.CLOSE_SHOP;
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
