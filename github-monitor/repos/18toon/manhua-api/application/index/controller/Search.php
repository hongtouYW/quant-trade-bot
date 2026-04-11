<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/6/21
 * Time: 14:25
 */

namespace app\index\controller;
use app\index\model\Manhua;
use app\index\model\HotWord;
use app\index\model\Video as VideoModel;
use app\index\model\Pic as PicModel;
use app\index\model\Novel as NovelModel;
class Search extends Base
{

    /**
     * Notes:热搜词
     *
     * DateTime: 2023/9/10 18:41
     */
    public function hotWord()
    {

        $list = HotWord::lists();
        return show(1, $list);

    }

    /**
     * Notes:热搜内容
     *
     * DateTime: 2023/9/10 18:41
     */
    public static function hotContent()
    {

        $lists = [];
        $lists['video'] = VideoModel::hotSearch(0);
        $lists['av'] = VideoModel::hotSearch(1);
        // $lists['picture'] = PicModel::hotSearch();
        // $lists['novel'] = NovelModel::hotSearch();
        return show(1, $lists);
    }


/**
 * Notes: 搜索漫画
 *
 * DateTime: 2025/4/22 18:41
 */
public function comic()
{
    $keyword = trim(getInput('keyword'));
    $tags = trim(getInput('tags'));
    $ticai_id = (int) getInput('ticai_id');
    $page = (int) getInput('page', 1);
    $limit = (int) getInput('limit', 12);

    if ($limit <= 0) {
        $limit = 10;
    }

    if (empty($keyword) && empty($tags) && empty($ticai_id)) {
        return show(0, '关键词、标签或题材ID至少填写一个');
    }

    $where = [['status', '=', 1]];

    if (!empty($keyword)) {
        $where[] = ['title|auther', 'like', '%' . $keyword . '%'];
    }

    if (!empty($tags)) {
        $where[] = ['keyword', 'like', '%' . $tags . '%'];
    }

    if (!empty($ticai_id)) {
        $where[] = ['ticai_id', '=', $ticai_id];
    }

    $results = Manhua::field(Manhua::list_field)
        ->where($where)
        ->order('update_time desc')
        ->paginate([
            'list_rows' => $limit,
            'page' => $page,
        ])
        ->toArray();

    return show(1, $results);
}

}