<?php
namespace app\index\model;

use think\Model;
use think\Db;
use app\index\model\User;
use think\facade\Request;

class Rating extends Model
{
    protected $table              = 'video_reviews';
    protected $autoWriteTimestamp = true; // auto handle created_at, updated_at
    protected $append             = ['user', 'like_count'];
    protected $hidden             = ['user_id'];

    public function getUserAttr($value, $data)
    {
        if (!empty($data['user_id'])) {
            $user = User::field('id,username')->where('id', $data['user_id'])->find();
            if ($user) {
                return [
                    'id' => $user['id'],
                    'username' => $user['username']
                ];
            }
        }
        return null;
    }
    
    // public function getLikeCountAttr($value, $data)
    // {
    //     return Db::name('video_review_likes')
    //              ->where('review_id', $data['id'])
    //              ->count();
    // }

    public function getLikeCountAttr($value, $data)
    {
        if (isset($data['id'])) {
            return Db::name('video_review_likes')
                    ->where('review_id', $data['id'])
                    ->count();
        }
        return 0;
    }

    
    public static function getThreadedReviews($video_id, $page = 1, $limit = 10)
    {
        $topLevel = self::where('video_id', $video_id)
            ->whereNull('parent_id')
            ->where('status', 1)
            ->order('created_at', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        foreach ($topLevel as &$review) {
            $review['replies'] = self::getReplies($review['id']);
        }
        return $topLevel;
    }
    
    public static function getReplies($parent_id)
    {
        $replies = self::where('parent_id', $parent_id)
            ->where('status', 1)
            ->order('created_at', 'asc')
            ->select()
            ->toArray();

        foreach ($replies as &$reply) {
            $reply['replies'] = self::getReplies($reply['id']);
        }
        return $replies;
    }
    
    public static function userHasReviewed($user_id, $video_id)
    {
        return self::where('user_id', $user_id)
            ->where('video_id', $video_id)
            ->whereNull('parent_id')
            ->find();
    }

    public static function updateVideoRating($video_id)
    {
        $avg   = self::where('video_id', $video_id)->whereNull('parent_id')->avg('rating');
        $count = self::where('video_id', $video_id)->whereNull('parent_id')->count();

        Db::name('video')
            ->where('id', $video_id)
            ->update([
                'rating_avg'   => round($avg, 2),
                'rating_count' => $count
            ]);
    }

    public static function likeReview($review_id, $user_id)
    {
        $exists = Db::name('video_review_likes')
            ->where('review_id', $review_id)
            ->where('user_id', $user_id)
            ->find();

        if ($exists) {
            // Unlike (remove existing like)
            $success = Db::name('video_review_likes')
                ->where('review_id', $review_id)
                ->where('user_id', $user_id)
                ->delete();
            return $success !== false ? false : null;
        }

        // Add new like
        $success = Db::name('video_review_likes')->insert([
            'review_id'  => $review_id,
            'user_id'    => $user_id,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        return $success ? true : null;
    }

    public static function editReview($user_id, $review_id, $content, $rating)
    {
        $review = self::where('id', $review_id)
            ->where('user_id', $user_id)
            ->find();

        if (!$review) return false;

        $review->rating = $rating;
        $review->review = $content;
        $review->status = 0; // re-verify after edit
        return $review->save();
    }
    
    public static function deleteReview($user_id, $review_id)
    {
        $review = self::where('id', $review_id)
            ->where('user_id', $user_id)
            ->find();

        if (!$review) return false;

        // Delete nested replies too
        self::where('parent_id', $review_id)->delete();
        return $review->delete();
    }
}
