package com.dj.user.util;

import com.dj.user.model.request.RequestAddBank;
import com.dj.user.model.request.RequestBindEmail;
import com.dj.user.model.request.RequestBindGoogleOTP;
import com.dj.user.model.request.RequestBindPhone;
import com.dj.user.model.request.RequestBindVerifyOTP;
import com.dj.user.model.request.RequestBonusRedemption;
import com.dj.user.model.request.RequestChangeOTP;
import com.dj.user.model.request.RequestChangePassword;
import com.dj.user.model.request.RequestChangePlayerPassword;
import com.dj.user.model.request.RequestChangeVerifyOTP;
import com.dj.user.model.request.RequestDeleteBank;
import com.dj.user.model.request.RequestDownlineNew;
import com.dj.user.model.request.RequestFAQ;
import com.dj.user.model.request.RequestInvitationCodeEdit;
import com.dj.user.model.request.RequestInvitationCodeNew;
import com.dj.user.model.request.RequestLoginRegister;
import com.dj.user.model.request.RequestNotificationRead;
import com.dj.user.model.request.RequestPaymentStatus;
import com.dj.user.model.request.RequestPaymentType;
import com.dj.user.model.request.RequestPlayer;
import com.dj.user.model.request.RequestPlayerDelete;
import com.dj.user.model.request.RequestPlayerDetails;
import com.dj.user.model.request.RequestPlayerLogin;
import com.dj.user.model.request.RequestPlayerPassword;
import com.dj.user.model.request.RequestPlayerTopUpWithdraw;
import com.dj.user.model.request.RequestPlayerTransferAll;
import com.dj.user.model.request.RequestPlayerTransferPoint;
import com.dj.user.model.request.RequestProfile;
import com.dj.user.model.request.RequestProfileEdit;
import com.dj.user.model.request.RequestProfileGeneral;
import com.dj.user.model.request.RequestPromotion;
import com.dj.user.model.request.RequestQuickPay;
import com.dj.user.model.request.RequestRandomBindVerifyOTP;
import com.dj.user.model.request.RequestRefresh;
import com.dj.user.model.request.RequestReset;
import com.dj.user.model.request.RequestResetPassword;
import com.dj.user.model.request.RequestResetVerifyOTP;
import com.dj.user.model.request.RequestSliderRead;
import com.dj.user.model.request.RequestSummaryData;
import com.dj.user.model.request.RequestTopUp;
import com.dj.user.model.request.RequestTransaction;
import com.dj.user.model.request.RequestUpdateFirebase;
import com.dj.user.model.request.RequestVIP;
import com.dj.user.model.request.RequestVerifyOTP;
import com.dj.user.model.request.RequestVersion;
import com.dj.user.model.request.RequestWithdraw;
import com.dj.user.model.request.RequestYxi;
import com.dj.user.model.request.RequestYxiBookmark;
import com.dj.user.model.request.RequestYxiBookmarkDelete;
import com.dj.user.model.request.RequestYxiDetails;
import com.dj.user.model.request.RequestYxiProvider;
import com.dj.user.model.response.Bank;
import com.dj.user.model.response.BankAccount;
import com.dj.user.model.response.BaseResponse;
import com.dj.user.model.response.Commission;
import com.dj.user.model.response.CommissionSummary;
import com.dj.user.model.response.Country;
import com.dj.user.model.response.Downline;
import com.dj.user.model.response.FAQ;
import com.dj.user.model.response.Feedback;
import com.dj.user.model.response.FeedbackType;
import com.dj.user.model.response.FindUs;
import com.dj.user.model.response.Friend;
import com.dj.user.model.response.FriendCommission;
import com.dj.user.model.response.GameType;
import com.dj.user.model.response.GoogleAuthenticator;
import com.dj.user.model.response.InvitationCode;
import com.dj.user.model.response.InvitationSummary;
import com.dj.user.model.response.Member;
import com.dj.user.model.response.Notification;
import com.dj.user.model.response.PaymentType;
import com.dj.user.model.response.Player;
import com.dj.user.model.response.Promotion;
import com.dj.user.model.response.Redemption;
import com.dj.user.model.response.ReferralTutorial;
import com.dj.user.model.response.Slider;
import com.dj.user.model.response.SummaryData;
import com.dj.user.model.response.Transaction;
import com.dj.user.model.response.UserAgreement;
import com.dj.user.model.response.VIP;
import com.dj.user.model.response.Version;
import com.dj.user.model.response.Yxi;
import com.dj.user.model.response.YxiBookmark;
import com.dj.user.model.response.YxiPlatform;
import com.dj.user.model.response.YxiProvider;

import java.util.List;

import okhttp3.MultipartBody;
import okhttp3.RequestBody;
import retrofit2.Call;
import retrofit2.http.Body;
import retrofit2.http.Headers;
import retrofit2.http.Multipart;
import retrofit2.http.POST;
import retrofit2.http.Part;

public interface ApiService {

    @POST("member/version")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Version>> checkVersion(@Body RequestVersion request);

    @POST("member/version/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<Version>>> getVersionList();

    @POST("agreement/view")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<UserAgreement>> getUserAgreementUrl();

    @POST("member/register")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> register(@Body RequestLoginRegister request);

    @POST("member/login")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Member>> login(@Body RequestLoginRegister request);

    @POST("member/verifyOTP")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Member>> loginRegisterVerifyOTP(@Body RequestVerifyOTP request);

    @POST("member/resetpassword")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> requestReset(@Body RequestReset request);

    @POST("member/resetOTP")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> resetVerifyOTP(@Body RequestResetVerifyOTP request);

    @POST("member/resetnewpassword")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Member>> resetPassword(@Body RequestResetPassword request);

    @POST("member/logout")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> logout();

    @POST("member/refresh")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> refreshToken(@Body RequestRefresh request);

    @POST("member/firebase/device")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Member>> updateFirebaseToken(@Body RequestUpdateFirebase request);

    @POST("member/view")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Member>> getProfile(@Body RequestProfile request);

    @POST("member/edit")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Member>> editProfile(@Body RequestProfileEdit request);

    @POST("member/avatar/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> getAvatarList(@Body RequestProfile request);

    @POST("member/bind/phone/random")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> requestRandomBindPhone(@Body RequestBindPhone request);

    @POST("member/bind/phone/random/otp")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Member>> randomBindPhone(@Body RequestRandomBindVerifyOTP request);

    @POST("member/bind/phone")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> requestBindPhone(@Body RequestBindPhone request);

    @POST("member/bind/phone/otp")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Member>> bindPhone(@Body RequestBindVerifyOTP request);

    @POST("member/bind/email")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> requestBindEmail(@Body RequestBindEmail request);

    @POST("member/bind/email/otp")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Member>> bindEmail(@Body RequestBindVerifyOTP request);

    @POST("member/google/generate/2fa/qr")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<GoogleAuthenticator>> getGoogleAuthenticatorInfo(@Body RequestProfile request);

    @POST("member/bind/google")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Member>> bindGoogle(@Body RequestBindGoogleOTP request);

    @POST("member/changepassword")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> changePassword(@Body RequestChangePassword request);

    @POST("member/changepassword/send/otp")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> changePasswordRequestOTP(@Body RequestChangeOTP request);

    @POST("member/passwordOTP")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Member>> changePasswordVerifyOTP(@Body RequestChangeVerifyOTP request);

    @POST("member/bank/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<BankAccount>>> getBankAccountList(@Body RequestProfile request);

    @POST("member/bank/add")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<BankAccount>> addBankAccount(@Body RequestAddBank request);

    @POST("member/bank/fastpay")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<BankAccount>> updateQuickPay(@Body RequestQuickPay request);

    @POST("member/bank/delete")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<BankAccount>> deleteBankAccount(@Body RequestDeleteBank request);

    @POST("member/topup")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> topUp(@Body RequestTopUp request);

    @POST("member/withdraw")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Transaction>> withdraw(@Body RequestWithdraw request);

    @POST("member/withdraw/qr")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> getWithdrawQr(@Body RequestWithdraw request);

    @POST("member/performance/upline")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<String>> getDirectUpline(@Body RequestProfile request);

    @POST("member/performance/profile")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<InvitationSummary>> getInvitationSummary(@Body RequestProfile request);

    @POST("member/performance/invite/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<InvitationCode>>> getInvitationCodeList(@Body RequestProfile request);

    @POST("member/performance/invite/new")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<InvitationCode>> createNewInvitationCode(@Body RequestInvitationCodeNew request);

    @POST("member/performance/invite/default/edit")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<InvitationCode>> setDefaultInvitationCode(@Body RequestInvitationCodeEdit request);

    @POST("member/performance/invite/name/edit")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<InvitationCode>> setInvitationCodeLabel(@Body RequestInvitationCodeEdit request);

    @POST("member/performance/summary")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<SummaryData>> getSummaryData(@Body RequestSummaryData request);

    @POST("member/performance/commission/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<Commission>>> getCommissionList(@Body RequestProfile request);

    @POST("member/performance/commission/list/total")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<CommissionSummary>>> getKPIList(@Body RequestProfile request);

    @POST("member/performance/downline/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<Downline>>> getDownlineList(@Body RequestProfile request);

    @POST("member/performance/downline/new")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Member>> createNewDownline(@Body RequestDownlineNew request);

    @POST("member/performance/friend/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<Friend>>> getFriendList(@Body RequestProfile request);

    @POST("member/performance/friend/commission")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<FriendCommission>>> getFriendCommissionList(@Body RequestProfile request);

    @POST("bank/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<Bank>>> getBankList();

    @POST("member/feedback/type/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<FeedbackType>>> getFeedbackTypeList(@Body RequestProfile request);

    @POST("member/feedback/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<Feedback>>> getFeedbackList(@Body RequestProfileGeneral request);

    @Multipart
    @POST("member/feedback/send")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Feedback>> sendFeedback(
            @Part("member_id") RequestBody memberId,
            @Part("feedbacktype_id") RequestBody feedbackTypeId,
            @Part("feedback_desc") RequestBody feedbackDesc,
            @Part MultipartBody.Part photo
    );

    @POST("member/transaction/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> getTransactionList(@Body RequestTransaction request);

    @POST("member/notification/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<Notification>>> getNotificationList(@Body RequestProfile request);

    @POST("member/notification/read")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Notification>> markNotificationRead(@Body RequestNotificationRead request);

    @POST("member/slider/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<Slider>>> getSliderList(@Body RequestProfile request);

    @POST("member/slider/read")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Slider>> markSliderRead(@Body RequestSliderRead request);

    @POST("member/vip/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<VIP>>> getVIPList(@Body RequestVIP request);

    @POST("member/vip/bonus/remain/target")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> getRedeemDistance(@Body RequestProfile request);

    @POST("member/vip/bonus/first")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> redeemGeneralBonus(@Body RequestBonusRedemption request);

    @POST("member/vip/bonus/weekly")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> redeemWeeklyBonus(@Body RequestProfile request);

    @POST("member/vip/bonus/monthly")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> redeemMonthlyBonus(@Body RequestProfile request);

    @POST("member/vip/bonus/all")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> redeemAllBonus(@Body RequestProfile request);

    @POST("member/vip/bonus/history")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<Redemption>>> getRedemptionList(@Body RequestProfile request);

    @POST("member/game/bookmark/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<YxiBookmark>>> getFavList(@Body RequestYxiBookmark request);

    @POST("member/game/provider/bookmark/add")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<YxiBookmark>> favYxi(@Body RequestYxiBookmark request);

    @POST("member/game/provider/bookmark/delete")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<YxiBookmark>> unfavYxi(@Body RequestYxiBookmarkDelete request);

    @POST("member/game/type")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<GameType>>> getYxiTypeList(@Body RequestProfileGeneral request);

    @POST("member/game/platform/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<YxiPlatform>>> getYxiPlatformList(@Body RequestProfile request);

    @POST("member/game/provider/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<YxiProvider>>> getYxiProviderList(@Body RequestYxiProvider request);

    @POST("member/game/provider/view")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<YxiProvider>> getYxiProvider(@Body RequestPlayer request);

    @POST("member/game/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<Yxi>>> getYxiList(@Body RequestYxi request);

    @POST("member/game/view")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> getYxiDetails(@Body RequestYxiDetails request);

    @POST("member/player/list/add/reload/login")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> proceedToYxi(@Body RequestPlayer request);

    @POST("member/player/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<Player>>> getPlayerList(@Body RequestPlayer request);

    @POST("member/player/view")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Player>> getPlayerDetails(@Body RequestPlayerDetails request);

    @POST("member/player/password")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> getPlayerPassword(@Body RequestPlayerPassword request);

    @POST("member/player/changepassword")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Player>> changePlayerPassword(@Body RequestChangePlayerPassword request);

    @POST("member/player/add")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Player>> addPlayer(@Body RequestPlayer request);

    @POST("member/player/delete")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> deletePlayer(@Body RequestPlayerDelete request);

    @POST("member/player/reload")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> playerTopUp(@Body RequestPlayerTopUpWithdraw request);

    @POST("member/player/reload/out")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> playerTopUpAll(@Body RequestPlayerTopUpWithdraw request);

    @POST("member/player/withdraw")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> playerWithdraw(@Body RequestPlayerTopUpWithdraw request);

    @POST("member/player/withdraw/out")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> playerWithdrawAll(@Body RequestPlayerTopUpWithdraw request);

    @POST("member/player/transfer/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<YxiProvider>>> getYxiTransferList(@Body RequestProfile request);

    @POST("member/player/transfer/point")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> transferPoint(@Body RequestPlayerTransferPoint request);

    @POST("member/player/transfer/out")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> oneClickTransferYxiCredit(@Body RequestPlayerTransferAll request);

    @POST("member/player/login")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<String>> getYxiUrl(@Body RequestPlayerLogin request);

    @POST("member/country/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<Country>>> getCountryList(@Body RequestProfileGeneral request);

    @POST("country/list/phone")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<Country>>> getCountryList();

    @POST("member/payment/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<PaymentType>>> getPaymentTypeList(@Body RequestPaymentType request);

    @POST("member/payment/status/deposit")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> getDepositStatus(@Body RequestPaymentStatus request);

    @POST("member/payment/status/withdraw")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> getWithdrawalStatus(@Body RequestPaymentStatus request);

    @POST("member/promotion/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<Promotion>>> getPromotionList(@Body RequestPromotion request);

    @POST("member/question/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<FAQ>>> getFAQList(@Body RequestFAQ request);

    @POST("member/referral/qr")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> getReferralCode(@Body RequestProfile request);

    @POST("member/referral/tutorial")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<ReferralTutorial>> getReferralTutorial(@Body RequestProfile request);

    @POST("member/support/link")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> getSupportUrl(@Body RequestProfile request);

    @POST("member/official/link")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<FindUs>> getContactUs(@Body RequestProfile request);
}
