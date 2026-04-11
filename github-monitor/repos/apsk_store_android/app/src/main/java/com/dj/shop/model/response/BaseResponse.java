package com.dj.shop.model.response;

public class BaseResponse<T> {
    private T data; // Only present when status == true
    private String error;
    private String message;
    private boolean status;
    private Token token; // Optional, may be null
    private String encode_sign;

    // For refresh token response (directly in root)
    private String access_token;
    private String refresh_token;
    private String token_type;
    private String expires_in;
    private String refresh_expires_in;
    private Pagination pagination;

    // Member new (directly in root)
    private String otpcode;
    // Member password (directly in root)
    private String password;

    // Member change password (directly in root)
    private String otp_phone;

    // Transaction details after yxi top up/withdraw (directly in root)
    private Transaction transaction;

    // Top up / withdraw
    private Score score; // credit; // member;
    private Shop shop;

    // New player (directly in root)
    private Member member;
    private String member_login;
    private String member_pass;
    private String member_password;
    private Player player;
    private String player_login;
    private String player_name;
    private String player_pass;

    // Dashboard stats (directly in root)
    private double totalincome;
    private int totalmember;
    private int totalplayer;

    // Player top up / withdraw (directly in root)
    private Transaction credit; // member; player;
    private Transaction point;

    private String phone;

    public T getData() {
        return data;
    }

    public void setData(T data) {
        this.data = data;
    }

    public String getError() {
        return error;
    }

    public void setError(String error) {
        this.error = error;
    }

    public String getMessage() {
        return message;
    }

    public void setMessage(String message) {
        this.message = message;
    }

    public boolean isStatus() {
        return status;
    }

    public void setStatus(boolean status) {
        this.status = status;
    }

    public Token getToken() {
        return token;
    }

    public void setToken(Token token) {
        this.token = token;
    }

    public String getEncode_sign() {
        return encode_sign;
    }

    public void setEncode_sign(String encode_sign) {
        this.encode_sign = encode_sign;
    }

    public String getAccess_token() {
        return access_token;
    }

    public void setAccess_token(String access_token) {
        this.access_token = access_token;
    }

    public String getRefresh_token() {
        return refresh_token;
    }

    public void setRefresh_token(String refresh_token) {
        this.refresh_token = refresh_token;
    }

    public String getToken_type() {
        return token_type;
    }

    public void setToken_type(String token_type) {
        this.token_type = token_type;
    }

    public String getExpires_in() {
        return expires_in;
    }

    public void setExpires_in(String expires_in) {
        this.expires_in = expires_in;
    }

    public String getRefresh_expires_in() {
        return refresh_expires_in;
    }

    public void setRefresh_expires_in(String refresh_expires_in) {
        this.refresh_expires_in = refresh_expires_in;
    }

    public Pagination getPagination() {
        return pagination;
    }

    public void setPagination(Pagination pagination) {
        this.pagination = pagination;
    }

    public String getOtpcode() {
        return otpcode;
    }

    public void setOtpcode(String otpcode) {
        this.otpcode = otpcode;
    }

    public String getPassword() {
        return password;
    }

    public void setPassword(String password) {
        this.password = password;
    }

    public String getOtp_phone() {
        return otp_phone;
    }

    public void setOtp_phone(String otp_phone) {
        this.otp_phone = otp_phone;
    }

    public Transaction getTransaction() {
        return transaction;
    }

    public void setTransaction(Transaction transaction) {
        this.transaction = transaction;
    }

    public Score getScore() {
        return score;
    }

    public void setScore(Score score) {
        this.score = score;
    }

    public Shop getShop() {
        return shop;
    }

    public void setShop(Shop shop) {
        this.shop = shop;
    }

    public Member getMember() {
        return member;
    }

    public void setMember(Member member) {
        this.member = member;
    }

    public String getMember_login() {
        return member_login;
    }

    public void setMember_login(String member_login) {
        this.member_login = member_login;
    }

    public String getMember_pass() {
        return member_pass;
    }

    public void setMember_pass(String member_pass) {
        this.member_pass = member_pass;
    }

    public String getMember_password() {
        return member_password;
    }

    public void setMember_password(String member_password) {
        this.member_password = member_password;
    }

    public Player getPlayer() {
        return player;
    }

    public void setPlayer(Player player) {
        this.player = player;
    }

    public String getPlayer_login() {
        return player_login;
    }

    public void setPlayer_login(String player_login) {
        this.player_login = player_login;
    }

    public String getPlayer_name() {
        return player_name;
    }

    public void setPlayer_name(String player_name) {
        this.player_name = player_name;
    }

    public String getPlayer_pass() {
        return player_pass;
    }

    public void setPlayer_pass(String player_pass) {
        this.player_pass = player_pass;
    }

    public double getTotalincome() {
        return totalincome;
    }

    public void setTotalincome(double totalincome) {
        this.totalincome = totalincome;
    }

    public int getTotalmember() {
        return totalmember;
    }

    public void setTotalmember(int totalmember) {
        this.totalmember = totalmember;
    }

    public int getTotalplayer() {
        return totalplayer;
    }

    public void setTotalplayer(int totalplayer) {
        this.totalplayer = totalplayer;
    }

    public Transaction getCredit() {
        return credit;
    }

    public void setCredit(Transaction credit) {
        this.credit = credit;
    }

    public Transaction getPoint() {
        return point;
    }

    public void setPoint(Transaction point) {
        this.point = point;
    }

    public String getPhone() {
        return phone;
    }

    public void setPhone(String phone) {
        this.phone = phone;
    }
}
