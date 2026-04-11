package com.dj.user.model.response;

public class VIP {
    private long vip_id;
    private String vip_name;
    private String vip_desc;
    private int lvl;
    private String type;
    private String reward;
    private String icon;
    private String firstbonus;
    private String dailybonus;
    private String weeklybonus;
    private String monthlybonus;
    private String min_amount;
    private String max_amount;
    private long agent_id;
    private int status;
    private int delete;
    private String created_on;
    private String updated_on;
    private double score;
    private boolean isHeader;

    public VIP(boolean isHeader) {
        this.isHeader = isHeader;
    }

    public String getVip_id() {
        return String.valueOf(vip_id);
    }

    public void setVip_id(long vip_id) {
        this.vip_id = vip_id;
    }

    public String getVip_name() {
        return vip_name;
    }

    public void setVip_name(String vip_name) {
        this.vip_name = vip_name;
    }

    public String getVip_desc() {
        return vip_desc;
    }

    public void setVip_desc(String vip_desc) {
        this.vip_desc = vip_desc;
    }

    public int getLvl() {
        return lvl;
    }

    public void setLvl(int lvl) {
        this.lvl = lvl;
    }

    public String getType() {
        return type;
    }

    public void setType(String type) {
        this.type = type;
    }

    public String getReward() {
        return reward;
    }

    public void setReward(String reward) {
        this.reward = reward;
    }

    public String getIcon() {
        return icon;
    }

    public void setIcon(String icon) {
        this.icon = icon;
    }

    public double getFirstbonus() {
        try {
            return Double.parseDouble(firstbonus);
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setFirstbonus(String firstbonus) {
        this.firstbonus = firstbonus;
    }

    public double getDailybonus() {
        try {
            return Double.parseDouble(dailybonus);
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setDailybonus(String dailybonus) {
        this.dailybonus = dailybonus;
    }

    public double getWeeklybonus() {
        try {
            return Double.parseDouble(weeklybonus);
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setWeeklybonus(String weeklybonus) {
        this.weeklybonus = weeklybonus;
    }

    public double getMonthlybonus() {
        try {
            return Double.parseDouble(monthlybonus);
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setMonthlybonus(String monthlybonus) {
        this.monthlybonus = monthlybonus;
    }

    public double getMin_amount() {
        try {
            return Double.parseDouble(min_amount);
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setMin_amount(String min_amount) {
        this.min_amount = min_amount;
    }

    public double getMax_amount() {
        try {
            return Double.parseDouble(max_amount);
        } catch (NumberFormatException | NullPointerException e) {
            return 0.0;
        }
    }

    public void setMax_amount(String max_amount) {
        this.max_amount = max_amount;
    }

    public String getAgent_id() {
        return String.valueOf(agent_id);
    }

    public void setAgent_id(long agent_id) {
        this.agent_id = agent_id;
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

    public double getScore() {
        return score;
    }

    public void setScore(double score) {
        this.score = score;
    }

    public boolean isHeader() {
        return isHeader;
    }

    public void setHeader(boolean header) {
        isHeader = header;
    }

    public boolean isCurrent(int vip) {
        return lvl == vip;
    }
}
