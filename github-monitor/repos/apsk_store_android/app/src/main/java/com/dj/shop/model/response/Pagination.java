package com.dj.shop.model.response;

public class Pagination {
    private int total;
    private int perpage;
    private int currentpage;
    private int totalpages;
    private boolean hasnextpage;
    private boolean haspreviouspage;

    public int getTotal() {
        return total;
    }

    public void setTotal(int total) {
        this.total = total;
    }

    public int getPerpage() {
        return perpage;
    }

    public void setPerpage(int perpage) {
        this.perpage = perpage;
    }

    public int getCurrentpage() {
        return currentpage;
    }

    public void setCurrentpage(int currentpage) {
        this.currentpage = currentpage;
    }

    public int getTotalpages() {
        return totalpages;
    }

    public void setTotalpages(int totalpages) {
        this.totalpages = totalpages;
    }

    public boolean isHasnextpage() {
        return hasnextpage;
    }

    public void setHasnextpage(boolean hasnextpage) {
        this.hasnextpage = hasnextpage;
    }

    public boolean isHaspreviouspage() {
        return haspreviouspage;
    }

    public void setHaspreviouspage(boolean haspreviouspage) {
        this.haspreviouspage = haspreviouspage;
    }
}
