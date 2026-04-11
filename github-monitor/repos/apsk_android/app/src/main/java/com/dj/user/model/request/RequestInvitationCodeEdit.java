package com.dj.user.model.request;

public class RequestInvitationCodeEdit {
    private String member_id;
    private String invitation_id;
    private String invitecode_name;

    public RequestInvitationCodeEdit(String member_id, String invitation_id) {
        this.member_id = member_id;
        this.invitation_id = invitation_id;
    }

    public RequestInvitationCodeEdit(String member_id, String invitation_id, String invitation_name) {
        this.member_id = member_id;
        this.invitation_id = invitation_id;
        this.invitecode_name = invitation_name;
    }
}
