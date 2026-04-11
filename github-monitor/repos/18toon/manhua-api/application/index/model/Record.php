<?php

namespace app\index\model;

use think\Model;

class Record extends Model
{

    public function getTitleAttr($value, $data)
    {

        $data = [
            'manhua' => Manhua::where('id', '=', $data['manhua_id'])->value('title'),
            'chapter' => Chapter::where('id', '=', $data['capter_id'])->value('title')
        ];
        return $data;
    }

    public static function lists($uid, $page, $limit)
    {

        $lists = self::field('manhua_id,capter_id,score,buytime')->where('member_id', '=', $uid)->order('id desc')
            ->paginate([
                'list_rows' => $limit,
                'page'     => $page,
            ]);
        return $lists->append(['title']);
    }

    public static function listsByMidWithBuyStatus($uid, $mid)
    {
        $member = null;
        if (!empty($uid)) {
            $member = USER::where('id', $uid)->find();
        }
    
        $chapters = Chapter::field('id, title, image, score, isvip')
            ->where('manhua_id', '=', $mid)
            ->order('id asc')
            ->select();

        $comicXianmian = Manhua::where('id', $mid)->value('xianmian');
    
        $boughtChapterIds = [];
        $readChapterIds = [];
        $latestReadChapterId = null;
    
        if (!empty($uid)) {
            $boughtChapterIds = self::where('member_id', '=', $uid)
                ->where('manhua_id', '=', $mid)
                ->column('capter_id');
    
            // 查出所有阅读记录和对应时间
            $readHistory = History::where('member_id', '=', $uid)
                ->where('manhua_id', '=', $mid)
                ->field('capter_id, addtime')
                ->select();
    
            // 提取已读章节 ID
            $readChapterIds = array_column($readHistory->toArray(), 'capter_id');
    
            // 找出 addtime 最大的记录的章节 ID
            if (!$readHistory->isEmpty()) {
                $latestRead = $readHistory->toArray();
                usort($latestRead, function($a, $b) {
                    return $b['addtime'] <=> $a['addtime'];
                });
                $latestReadChapterId = $latestRead[0]['capter_id'];
            }
        }
    
        foreach ($chapters as &$chapter) {
            if ($comicXianmian == 1) {
                // 👉 限免漫画，所有章节直接免费
                $chapter->isvip = 0;
                $chapter->is_lock = 0;
            } else {
                // 是否已解锁
                if ($chapter->score == 0 && $chapter->isvip == 0) {
                    $chapter->is_lock = 0;
                } elseif ($uid && $chapter->isvip == 1 && $member && $member->isvip_status == 1) {
                    $chapter->is_lock = 0;
                } elseif ($uid && in_array($chapter->id, $boughtChapterIds)) {
                    $chapter->is_lock = 0;
                } else {
                    $chapter->is_lock = 1;
                }
            }
            // 是否已阅读
            if ($uid && in_array($chapter->id, $readChapterIds)) {
                $chapter->is_read = ($chapter->id == $latestReadChapterId) ? 2 : 1;
            } else {
                $chapter->is_read = 0;
            }
        }
    
        return $chapters;
    }
    
}
