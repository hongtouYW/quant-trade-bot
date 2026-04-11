package com.dj.user.model.request;

public class RequestSummaryData {
    private String member_id;
    private String startdate;
    private String enddate;

    public RequestSummaryData(String member_id, String startdate, String enddate) {
        this.member_id = member_id;
        this.startdate = startdate;
        this.enddate = enddate;
    }
}
