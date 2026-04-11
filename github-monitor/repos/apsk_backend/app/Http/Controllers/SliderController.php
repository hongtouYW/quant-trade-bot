<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Slider;
use App\Models\Sliderread;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SliderController extends Controller
{
    /**
     * Search tbl_slider.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function list(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unvalidation'),
                    'error' => $validator->errors(),
                ],
                422
            );
        }
        try {
            $tbl_member = Member::where( 'member_id', $request->input('member_id') )->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $lang = $request->getPreferredLanguage();

            $tbl_slider = Slider::where('status', 1)
                                ->where('delete', 0)
                                ->where('lang', $lang)
                                ->where(function ($q) use ($tbl_member) {
                                    $q->where('agent_id', 0);
                                    if (!is_null($tbl_member->agent_id)) {
                                        $q->orWhere('agent_id', $tbl_member->agent_id);
                                    }
                                })
                                ->orderBy('created_on', 'desc');
            $tbl_slider = $tbl_slider->get();

            $readNotices = Sliderread::where('member_id', $request->input('member_id') )
                                        ->where('status', 1)
                                        ->where('delete', 0)
                                        ->pluck('slider_id');
            $readNotices = $readNotices->toArray();
            $readNoticesHash = array_flip($readNotices);
            $tbl_slider = $tbl_slider->map(function ($slider) use ($readNoticesHash) {
                return [
                    'slider_id' => $slider->slider_id,
                    'title' => $slider->title,
                    'slider_desc' => $slider->slider_desc,
                    'is_read' => isset($readNoticesHash[$slider->slider_id]) ? 1 : 0,
                    'lang' => $slider->lang,
                    'agent_id' => $slider->agent_id,
                    'status' => $slider->status,
                    'delete' => $slider->delete,
                    'created_on' => $slider->created_on,
                    'updated_on' => $slider->updated_on,
                ];
            })->toArray();
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('messages.list_success'),
                    'error' => "",
                    'data' => $tbl_slider,
                ],
                200
            );
        } catch (\Illuminate\Database\QueryException $e) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unexpected_error'),
                    'error' => $e->getMessage(),
                ],
                500
            );
        }
    }

    /**
     * Member Read tbl_slider.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function memberread(Request $request)
    {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }     
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer',
            'slider_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unvalidation'),
                    'error' => $validator->errors(),
                ],
                422
            );
        }
        try {
            $tbl_member = Member::where('member_id', $request->input('member_id'))->first();
            if (!$tbl_member) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('member.no_data_found'),
                        'error' => __('member.no_data_found'),
                    ],
                    400
                );
            }
            if ($tbl_member->status !== 1 || $tbl_member->delete === 1 || $tbl_member->alarm === 1 ) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('messages.profileinactive'),
                        'error' => __('messages.profileinactive'),
                    ],
                    401
                );
            }
            $tbl_slider = Slider::where('status', 1)
                                ->where('delete', 0)
                                ->where('slider_id', $request->input('slider_id'))
                                ->first();
            if (!$tbl_slider) {
                return sendEncryptedJsonResponse(
                    [
                        'status' => false,
                        'message' => __('slider.no_data_found'),
                        'error' => __('slider.no_data_found'),
                    ],
                    400
                );
            }
            $tbl_sliderread = Sliderread::where('status', 1)
                                        ->where('delete', 0)
                                        ->where('slider_id', $request->input('slider_id'))
                                        ->where('member_id', $request->input('member_id'))
                                        ->first();
            if (!$tbl_sliderread) {
                $tbl_noticeread = Sliderread::create([
                    'slider_id' => $request->input('slider_id'),
                    'member_id' => $request->input('member_id'),
                    'read_on' => now(),
                    'agent_id' => $tbl_member->agent_id,
                    'status' => 1,
                    'delete' => 0,
                    'created_on' => now(),
                    'updated_on' => now(),
                ]);
            }
            $tbl_slider->is_read = 1;
            return sendEncryptedJsonResponse(
                [
                    'status' => true,
                    'message' => __('notification.read'),
                    'error' => "",
                    'data' => $tbl_slider,
                ],
                200
            );
        } catch (\Illuminate\Database\QueryException $e) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.unexpected_error'),
                    'error' => $e->getMessage(),
                ],
                500
            );
        }
    }
}
