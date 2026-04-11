<?php

use Illuminate\Http\Request;
use App\Models\Credit;
use App\Models\Promotion;
use App\Models\Promotiontype;
use App\Models\Agent;
use App\Models\Agentcredit;
use Illuminate\Support\Facades\DB;

class Promotionevent
{
    public static function redeem( $tbl_member, $type, $newbie = false )
    {
        return DB::transaction(function () use ($tbl_member, $type, $newbie) {
            $tbl_member = $tbl_member->fresh();
            $agent_id = $tbl_member->agent_id ?? 0;
            $reason = null;
            $tbl_agent = Agent::where('status', 1)
                            ->where('delete', 0)
                            ->where('agent_id', $agent_id)
                            ->lockForUpdate()
                            ->first();
            if (!$tbl_agent) {
                return null;
            }
            $tbl_promotiontype = Promotiontype::where('status',1)
                                            ->where('delete',0)
                                            ->where('promotion_type',$type)
                                            ->where('agent_id',$agent_id)
                                            ->first();
            if (!$tbl_promotiontype) {
                return null;
            }
            $tbl_promotion = Promotion::where('status',1)
                                      ->where('delete',0)
                                      ->where('promotiontype_id',$tbl_promotiontype->promotiontype_id)
                                      ->where('agent_id',$agent_id)
                                      ->where('newbie',$newbie)
                                      ->first();
            if (!$tbl_promotion) {
                return null;
            }
            if ( $tbl_agent->balance < $tbl_promotion->amount ) {
                $reason = 'agent.insufficient';
                $status = -1;
            } else {
                $status = 1;
            }
            $tbl_credit = Credit::create([
                'member_id' => $tbl_member->member_id,
                'payment_id' => 1,
                'type' => $type,
                'amount' => $tbl_promotion->amount,
                'before_balance' => $tbl_member->balance,
                'after_balance' => $tbl_member->balance + $tbl_promotion->amount,
                'submit_on' => now(),
                'agent_id' => $tbl_member->agent_id,
                'reason' => $reason,
                'status' => $status,
                'delete' => 0,
            ]);
            $tbl_agentcredit = Agentcredit::create([
                'agent_id' => $tbl_agent->agent_id,
                'member_id' => $tbl_member->member_id,
                'type' => "promotion.".$type,
                'amount' => $tbl_promotion->amount,
                'before_balance' => $tbl_agent->balance,
                'after_balance' => $tbl_agent->balance - $tbl_promotion->amount,
                'submit_on' => now(),
                'reason' => $reason,
                'status' => $status,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ]);
            if ( $status === 1 ) {
                $tbl_member->increment('balance', $tbl_promotion->amount);
                $tbl_agent->decrement('balance', $tbl_promotion->amount);
            }
            return $tbl_promotion->amount;
        });
    }

    public static function newbie( $tbl_member, $type )
    {
        $entitle = Credit::where('status',1)
                         ->where('delete',0)
                         ->where('type',$type)
                         ->where('member_id', $tbl_member->member_id)
                         ->exists();
        return !$entitle ? true : false;
    }

}