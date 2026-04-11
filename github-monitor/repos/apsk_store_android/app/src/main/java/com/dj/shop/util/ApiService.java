package com.dj.shop.util;

import com.dj.shop.model.request.RequestHistory;
import com.dj.shop.model.request.RequestLogin;
import com.dj.shop.model.request.RequestMemberBlock;
import com.dj.shop.model.request.RequestMemberBlockReason;
import com.dj.shop.model.request.RequestMemberChangePassword;
import com.dj.shop.model.request.RequestMemberNew;
import com.dj.shop.model.request.RequestMemberPassword;
import com.dj.shop.model.request.RequestMemberSearch;
import com.dj.shop.model.request.RequestMemberTopUp;
import com.dj.shop.model.request.RequestMemberWithdraw;
import com.dj.shop.model.request.RequestMemberWithdrawQR;
import com.dj.shop.model.request.RequestPlayerNew;
import com.dj.shop.model.request.RequestPlayerSearch;
import com.dj.shop.model.request.RequestPlayerTopUpWithdraw;
import com.dj.shop.model.request.RequestProfile;
import com.dj.shop.model.request.RequestProfileGeneral;
import com.dj.shop.model.request.RequestRefresh;
import com.dj.shop.model.request.RequestTransaction;
import com.dj.shop.model.request.RequestUpdateFirebase;
import com.dj.shop.model.request.RequestVersion;
import com.dj.shop.model.response.BaseResponse;
import com.dj.shop.model.response.Country;
import com.dj.shop.model.response.FeedbackType;
import com.dj.shop.model.response.History;
import com.dj.shop.model.response.Member;
import com.dj.shop.model.response.PaymentType;
import com.dj.shop.model.response.Player;
import com.dj.shop.model.response.Shop;
import com.dj.shop.model.response.Transaction;
import com.dj.shop.model.response.Version;
import com.dj.shop.model.response.YxiProvider;

import java.util.List;

import okhttp3.MultipartBody;
import okhttp3.RequestBody;
import retrofit2.Call;
import retrofit2.http.Body;
import retrofit2.http.GET;
import retrofit2.http.Headers;
import retrofit2.http.Multipart;
import retrofit2.http.POST;
import retrofit2.http.Part;
import retrofit2.http.Url;

public interface ApiService {
    @GET
    Call<BaseResponse<Void>> getFromUrl(@Url String url);

    @POST("shop/version")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Version>> checkVersion(@Body RequestVersion request);

    @POST("shop/login")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Shop>> login(@Body RequestLogin request);

    @POST("shop/logout")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> logout();

    @POST("shop/refresh")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> refreshToken(@Body RequestRefresh request);

    @POST("shop/firebase/device")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Shop>> updateFirebaseToken(@Body RequestUpdateFirebase request);

    @POST("shop/dashboard")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Shop>> getShopStat(@Body RequestProfile request);

    @POST("shop/view")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Shop>> getShopProfile(@Body RequestProfile request);

    @POST("shop/balance")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Shop>> notifyLowBalance(@Body RequestProfile request);

    @POST("shop/member/search")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Member>> searchMember(@Body RequestMemberSearch request);

    @POST("shop/member/search/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<Member>>> searchMemberList(@Body RequestMemberSearch request);

    @POST("shop/member/password")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> getMemberPassword(@Body RequestMemberPassword request);

    @POST("shop/member/new")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Member>> createNewMember(@Body RequestMemberNew request);

    @POST("shop/member/random")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Member>> randomGenerateMember(@Body RequestProfile request);

    @POST("shop/member/changepassword")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> changeUserPassword(@Body RequestMemberChangePassword request);

    @POST("shop/member/block")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Member>> blockUser(@Body RequestMemberBlock request);

    @POST("shop/member/block/reason")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> blockUserReason(@Body RequestMemberBlockReason request);

    @POST("shop/member/topup")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> memberTopUp(@Body RequestMemberTopUp request);

    @POST("shop/member/withdraw")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Transaction>> memberWithdraw(@Body RequestMemberWithdraw request);

    @POST("shop/withdraw/qr/scan/password")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> memberWithdrawQR(@Body RequestMemberWithdrawQR request);

    @POST("shop/player/add")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Player>> createNewPlayer(@Body RequestPlayerNew request);

    @POST("shop/player/search")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Player>> playerSearch(@Body RequestPlayerSearch request);

    @POST("shop/player/reload")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> playerTopUp(@Body RequestPlayerTopUpWithdraw request);

    @POST("shop/player/withdraw")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> playerWithdraw(@Body RequestPlayerTopUpWithdraw request);

    @POST("shop/transaction/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<Transaction>>> getTransactionList(@Body RequestTransaction request);

    @POST("shop/member/transaction/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<History>>> getHistoryList(@Body RequestHistory request);

    @POST("shop/game/provider/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<YxiProvider>>> getYxiProviderList(@Body RequestProfile request);

    @POST("shop/feedback/type/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<FeedbackType>>> getFeedbackTypeList(@Body RequestProfile request);

    @Multipart
    @POST("shop/feedback/send")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<Void>> sendFeedback(
            @Part("shop_id") RequestBody shopId,
            @Part("feedbacktype_id") RequestBody feedbackTypeId,
            @Part("feedback_desc") RequestBody feedbackDesc,
            @Part MultipartBody.Part photo
    );

    @POST("shop/country/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<Country>>> getCountryList(@Body RequestProfileGeneral request);

    @POST("shop/payment/list")
    @Headers("X-No-Encryption: true")
    Call<BaseResponse<List<PaymentType>>> getPaymentTypeList(@Body RequestProfileGeneral request);
}
