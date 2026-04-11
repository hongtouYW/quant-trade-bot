<?php

namespace app\index\controller;
use app\index\model\Rating as RatingModel;
use app\index\model\Token;
use app\lib\exception\BaseException;

class Rating extends Base
{
    public function submit()
    {
        $uid        = Token::getCurrentUid();
        $video_id   = getInput('vid');
        $rating     = getInput('rating');
        $review     = trim(getInput('review'));
        $parent_id  = getInput('parent_id');

        if ($parent_id === false || $parent_id === '' || $parent_id === null) {
            $parent_id = null; // use this due if frontend didnt pass the "parent_id" in input that will become falsde
        }

        if (!$video_id || !$rating) {
            throw new BaseException(8001);
        }

        if (is_null($parent_id)) {
            if (!$rating || $rating < 1 || $rating > 5) {
                throw new BaseException(8002);
            }

            // Each user can only submit 1 top-level review per video
            $exists = RatingModel::where('user_id', $uid)
                ->where('video_id', $video_id)
                ->whereNull('parent_id')
                ->find();

            if ($exists) {
                $exists->rating = $rating;
                $exists->review = $review;
                $exists->status = 0; // Mark as pending again
                $exists->save();

                RatingModel::updateVideoRating($video_id);
                return show(1, ['reviewSubmited' => true,
                                'update'         => true,
                                'msg'            => 'Review updated, pending approval']);
            }
        }

        // Save new review or reply
        $data = [
            'user_id'   => $uid,
            'video_id'  => $video_id,
            'review'    => $review,
            'parent_id' => $parent_id,
            'status'    => 0
        ];

        if (is_null($parent_id)) {
            $data['rating'] = $rating;
        }

        $res = RatingModel::create($data);
        if (!$res) {
            throw new BaseException(['msg' => 'Submit failed']);
        }

        if (is_null($parent_id)) {
            RatingModel::updateVideoRating($video_id);
        }

        return show(1, ['reviewSubmited' => true,
                        'update'         => false,
                        'msg'            => 'Submitted and pending approval']);
    }


    public function list()
    {
        $video_id = getInput('vid');
        $page     = !empty(getInput('page')) ? getInput('page') : 1;
        $limit    = !empty(getInput('limit')) ? getInput('limit') : 10;

        if (!$video_id) {
            return show(400, 'Missing video_id');
        }

        $list = RatingModel::getThreadedReviews($video_id, $page, $limit);
        return show(1, $list);
    }

    public function like()
    {
        $uid       = Token::getCurrentUid();
        $review_id = getInput('review_id');

        if (!$review_id) {
            return show(400, ['likeReview' => false, 'msg' => 'Missing review ID']);
        }

        $review = RatingModel::where('id', $review_id)->where('status', 1)->find();
        if (!$review) {
            return show(400, ['likeReview' => false, 'msg' => 'Review does not exist or not approved']);
        }

        $result = RatingModel::likeReview($review_id, $uid);
        if ($result === true) {
            return show(1, ['likeReview' => true, 'msg' => 'Like review successfully']);
        } elseif ($result === false) {
            return show(1, ['likeReview' => false, 'msg' => 'Like review removed']);
        } else {
            return show(500, ['likeReview' => false, 'msg' => 'Like review failed']);
        }
    }

    // Edit a review or reply
    public function edit()
    {
        $uid       = Token::getCurrentUid();
        $review_id = getInput('review_id');
        $content   = trim(getInput('review'));
        $rating    = trim(getInput('rating'));

        if (!$review_id || !$content) {
            return show(400, ['editReview' => false, 'msg' => 'Missing review id or content']);
        }

        $success = RatingModel::editReview($uid, $review_id, $content, $rating);

        if ($success) {
            return show(1, ['editReview' => true, 'msg' => 'Edit success']);
        } else {
            return show(0, ['editReview' => false, 'msg' => 'Edit failed or not authorized']);
        }
    }

    // Delete a review or reply
    public function delete()
    {
        $uid       = Token::getCurrentUid();
        $review_id = getInput('review_id');

        if (!$review_id) {
            return show(400, ['deleteReview' => false, 'msg' => 'Missing review_id']);
        }
        $success = RatingModel::deleteReview($uid, $review_id);
        if ($success) {
            return show(1, ['deleteReview' => true, 'msg' => 'Review deleted']);
        } else {
            return show(0, ['deleteReview' => false, 'msg' => 'Delete failed or not authorized']);
        }
    }

}
