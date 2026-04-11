package com.dj.user.model.request;

public class RequestTransaction {
    private String member_id;
    private String type;
    private String startdate;
    private String enddate;
    private int page;
    private int limit;

    public RequestTransaction(String memberId, String type, String startdate, String enddate, int page) {
        this.member_id = memberId;
        this.type = type;
        this.startdate = startdate;
        this.enddate = enddate;
        this.page = page;
        this.limit = 20;
    }
}
