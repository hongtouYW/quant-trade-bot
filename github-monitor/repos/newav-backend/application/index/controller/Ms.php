<?php

namespace app\index\controller;

use think\Controller;
use app\index\model\Video as VideoModel;
use app\index\model\Actor as ActorModel;
use app\index\model\Tags as TagsModel;
use app\index\model\Publisher;
use app\index\model\Translate;
use think\Db;
class Ms extends Controller
{

    public function index(){

        echo 'Ms12-14-1';
    }

    // public function syncSheetSubtitles()
    // {
    //     set_time_limit(0);
    //     @ob_implicit_flush(true);
    //     @ob_end_flush();

    //     // [external_id, ja_url, zh_url, en_url, th_url, ru_url, es_url, (skip vi), ms_url]
    //     $rows = [
    //         ['video_id_16372', 'http://203.57.40.105:10180/storage/76eb3bcc-a00c-49e6-8306-1e4b1a916874/video_id_16372.vtt', 'http://203.57.40.105:10183/files/16372-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16372-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16372-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16372-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16372-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16372-id/subtitles.vtt'],
    //         ['video_id_16319', 'http://203.57.40.105:10180/storage/901e5fd2-6007-42bd-a084-8144c39df06d/video_id_16319.vtt', 'http://203.57.40.105:10183/files/1/output.vtt', 'http://203.57.40.105:10183/files/2/output.vtt', 'http://203.57.40.105:10183/files/3/output.vtt', 'http://203.57.40.105:10183/files/4/output.vtt', 'http://203.57.40.105:10183/files/5/output.vtt', 'http://203.57.40.105:10183/files/7/output.vtt'],
    //         ['video_id_16379', 'http://203.57.40.105:10180/storage/bb9c623b-a57b-4a73-aebc-63fcde927af7/video_id_16379.vtt', 'http://203.57.40.105:10183/files/16379-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16379-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16379-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16379-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16379-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16379-id/subtitles.vtt'],
    //         ['video_id_16371', 'http://203.57.40.105:10180/storage/8af1fe8a-29b8-4c51-9253-f760764e32fb/video_id_16371.vtt', 'http://203.57.40.105:10183/files/16371-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16371-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16371-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16371-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16371-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16371-id/subtitles.vtt'],
    //         ['video_id_16511', 'http://203.57.40.105:10180/storage/575b2ff9-029c-44a2-9406-73c5555509b1/video_id_16511.vtt', 'http://203.57.40.105:10183/files/16511-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16511-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16511-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16511-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16511-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16511-id/subtitles.vtt'],
    //         ['video_id_16512', 'http://203.57.40.105:10180/storage/b786b444-74bf-4d09-a740-220ff3edf68d/video_id_16512.vtt', 'http://203.57.40.105:10183/files/16512-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16512-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16512-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16512-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16512-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16512-id/subtitles.vtt'],
    //         ['video_id_16513', 'http://203.57.40.105:10180/storage/51f8c4ab-acfe-47b8-ac22-6b86f75a219b/video_id_16513.vtt', 'http://203.57.40.105:10183/files/16513-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16513-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16513-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16513-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16513-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16513-id/subtitles.vtt'],
    //         ['video_id_16483', 'http://203.57.40.105:10180/storage/8bff8ad7-2a48-4ec0-a58a-d30c5d2b722b/video_id_16483.vtt', 'http://203.57.40.105:10183/files/16483-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16483-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16483-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16483-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16483-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16483-id/subtitles.vtt'],
    //         ['video_id_16408', 'http://203.57.40.105:10180/storage/12a6a1d4-5947-4389-aea8-438c2f53a1ef/video_id_16408.vtt', 'http://203.57.40.105:10183/files/16408-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16408-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16408-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16408-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16408-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16408-id/subtitles.vtt'],
    //         ['video_id_16409', 'http://203.57.40.105:10180/storage/c2753ec3-e760-471e-a9d2-ad9ce19d3b80/video_id_16409.vtt', 'http://203.57.40.105:10183/files/16409-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16409-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16409-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16409-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16409-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16409-id/subtitles.vtt'],
    //         ['video_id_16410', 'http://203.57.40.105:10180/storage/6c10caf5-5a81-4217-98da-5aa194a33d55/video_id_16410.vtt', 'http://203.57.40.105:10183/files/16410-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16410-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16410-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16410-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16410-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16410-id/subtitles.vtt'],
    //         ['video_id_16411', 'http://203.57.40.105:10180/storage/0ef0a8a0-5767-4e26-ba65-77d42690f440/video_id_16411.vtt', 'http://203.57.40.105:10183/files/16411-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16411-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16411-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16411-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16411-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16411-id/subtitles.vtt'],
    //         ['video_id_16414', 'http://203.57.40.105:10180/storage/90d4bde2-7ea5-40c5-81c1-4557144c6d4e/video_id_16414.vtt', 'http://203.57.40.105:10183/files/16414-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16414-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16414-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16414-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16414-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16414-id/subtitles.vtt'],
    //         ['video_id_16415', 'http://203.57.40.105:10180/storage/3154c6ee-083f-4828-af43-5f4963f94acd/video_id_16415.vtt', 'http://203.57.40.105:10183/files/16415-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16415-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16415-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16415-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16415-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16415-id/subtitles.vtt'],
    //         ['video_id_16419', 'http://203.57.40.105:10180/storage/c93b2a50-672b-4811-910d-2d2d73634c8e/video_id_16419.vtt', 'http://203.57.40.105:10183/files/16419-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16419-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16419-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16419-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16419-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16419-id/subtitles.vtt'],
    //         ['video_id_16423', 'http://203.57.40.105:10180/storage/c388ac9e-3cc1-4a0f-8838-71671d5b9e05/video_id_16423.vtt', 'http://203.57.40.105:10183/files/16423-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16423-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16423-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16423-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16423-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16423-id/subtitles.vtt'],
    //         ['video_id_16424', 'http://203.57.40.105:10180/storage/19388fd8-4899-4e39-b271-8a6dac5d7d53/video_id_16424.vtt', 'http://203.57.40.105:10183/files/16424-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16424-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16424-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16424-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16424-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16424-id/subtitles.vtt'],
    //         ['video_id_16425', 'http://203.57.40.105:10180/storage/2b53f237-c6fd-46d1-a384-0dacedb8bc2f/video_id_16425.vtt', 'http://203.57.40.105:10183/files/16425-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16425-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16425-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16425-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16425-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16425-id/subtitles.vtt'],
    //         ['video_id_16426', 'http://203.57.40.105:10180/storage/db48d46d-74d3-46d4-8f34-d7bd47685807/video_id_16426.vtt', 'http://203.57.40.105:10183/files/16426-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16426-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16426-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16426-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16426-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16426-id/subtitles.vtt'],
    //         ['video_id_16427', 'http://203.57.40.105:10180/storage/3ed0eaf1-3c5b-4bb1-8259-e657f9583e73/video_id_16427.vtt', 'http://203.57.40.105:10183/files/16427-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16427-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16427-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16427-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16427-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16427-id/subtitles.vtt'],
    //         ['video_id_16429', 'http://203.57.40.105:10180/storage/1d9c0cb9-6289-47cb-892e-ac27769b6bfd/video_id_16429.vtt', 'http://203.57.40.105:10183/files/16429-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16429-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16429-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16429-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16429-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16429-id/subtitles.vtt'],
    //         ['video_id_16434', 'http://203.57.40.105:10180/storage/9ebd5dbc-55e8-4ee4-b511-9f5df96d449f/video_id_16434.vtt', 'http://203.57.40.105:10183/files/16434-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16434-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16434-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16434-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16434-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16434-id/subtitles.vtt'],
    //         ['video_id_16435', 'http://203.57.40.105:10180/storage/696ea9cb-b36e-4d0b-a2c4-842aee59273b/video_id_16435.vtt', 'http://203.57.40.105:10183/files/16435-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16435-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16435-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16435-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16435-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16435-id/subtitles.vtt'],
    //         ['video_id_16436', 'http://203.57.40.105:10180/storage/92103190-2c29-4804-a488-e82e78356207/video_id_16436.vtt', 'http://203.57.40.105:10183/files/16436-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16436-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16436-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16436-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16436-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16436-id/subtitles.vtt'],
    //         ['video_id_16437', 'http://203.57.40.105:10180/storage/212eb9aa-a3d3-4b6e-9bb3-dadbcf42f5bc/video_id_16437.vtt', 'http://203.57.40.105:10183/files/16437-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16437-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16437-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16437-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16437-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16437-id/subtitles.vtt'],
    //         ['video_id_16438', 'http://203.57.40.105:10180/storage/d73f457b-1335-48b8-98d5-2a3f712f3317/video_id_16438.vtt', 'http://203.57.40.105:10183/files/16438-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16438-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16438-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16438-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16438-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16438-id/subtitles.vtt'],
    //         ['video_id_16439', 'http://203.57.40.105:10180/storage/6bbe68da-3d94-4054-a026-5c9cbe58e4a9/video_id_16439.vtt', 'http://203.57.40.105:10183/files/16439-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16439-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16439-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16439-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16439-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16439-id/subtitles.vtt'],
    //         ['video_id_16440', 'http://203.57.40.105:10180/storage/838c7ad3-b173-495c-8f80-12dbcf0deff4/video_id_16440.vtt', 'http://203.57.40.105:10183/files/16440-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16440-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16440-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16440-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16440-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16440-id/subtitles.vtt'],
    //         ['video_id_16441', 'http://203.57.40.105:10180/storage/dad53bd6-f733-4d57-b65e-90309416f0b6/video_id_16441.vtt', 'http://203.57.40.105:10183/files/16441-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16441-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16441-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16441-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16441-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16441-id/subtitles.vtt'],
    //         ['video_id_16442', 'http://203.57.40.105:10180/storage/9e9f4c2d-feb5-4d23-ab18-3fc71f8e4b1c/video_id_16442.vtt', 'http://203.57.40.105:10183/files/16442-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16442-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16442-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16442-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16442-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16442-id/subtitles.vtt'],
    //         ['video_id_16443', 'http://203.57.40.105:10180/storage/bc877837-a8e7-45ef-ad01-001d7a4b6186/video_id_16443.vtt', 'http://203.57.40.105:10183/files/16443-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16443-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16443-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16443-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16443-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16443-id/subtitles.vtt'],
    //         ['video_id_16444', 'http://203.57.40.105:10180/storage/c8aa1374-0a6c-4978-9ce2-be5fc804a9e9/video_id_16444.vtt', 'http://203.57.40.105:10183/files/16444-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16444-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16444-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16444-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16444-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16444-id/subtitles.vtt'],
    //         ['video_id_16445', 'http://203.57.40.105:10180/storage/e32b1a78-c235-4496-915f-0c0b0882739d/video_id_16445.vtt', 'http://203.57.40.105:10183/files/16445-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16445-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16445-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16445-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16445-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16445-id/subtitles.vtt'],
    //         ['video_id_16446', 'http://203.57.40.105:10180/storage/db09c1c1-dfdc-4afa-b9cc-c8029e648952/video_id_16446.vtt', 'http://203.57.40.105:10183/files/16446-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16446-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16446-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16446-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16446-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16446-id/subtitles.vtt'],
    //         ['video_id_16447', 'http://203.57.40.105:10180/storage/c248c336-d160-4c3d-b2f7-68cd4119d02f/video_id_16447.vtt', 'http://203.57.40.105:10183/files/16447-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16447-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16447-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16447-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16447-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16447-id/subtitles.vtt'],
    //         ['video_id_16449', 'http://203.57.40.105:10180/storage/94762006-4ef4-4f83-abbe-924f0436f0df/video_id_16449.vtt', 'http://203.57.40.105:10183/files/16449-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16449-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16449-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16449-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16449-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16449-id/subtitles.vtt'],
    //         ['video_id_16070', 'http://203.57.40.105:10180/storage/c0b0f233-4b59-40b2-8f2a-60da1c73de6f/video_id_16070.vtt', 'http://203.57.40.105:10183/files/16070-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16070-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16070-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16070-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16070-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16070-id/subtitles.vtt'],
    //         ['video_id_2329',  'http://203.57.40.105:10180/storage/1caa2778-59dd-4471-8b03-e202c2c33825/video_id_2329.vtt',  'http://203.57.40.105:10183/files/2329-zh/subtitles.vtt',  'http://203.57.40.105:10183/files/2329-en/subtitles.vtt',  'http://203.57.40.105:10183/files/2329-th/subtitles.vtt',  'http://203.57.40.105:10183/files/2329-rs/subtitles.vtt',  'http://203.57.40.105:10183/files/2329-sp/subtitles.vtt',  'http://203.57.40.105:10183/files/2329-id/subtitles.vtt'],
    //         ['video_id_7100',  'http://203.57.40.105:10180/storage/77d22149-7011-45ea-8e46-14e8e96ea94e/video_id_7100.vtt',  'http://203.57.40.105:10183/files/7100-zh/subtitles.vtt',  'http://203.57.40.105:10183/files/7100-en/subtitles.vtt',  'http://203.57.40.105:10183/files/7100-th/subtitles.vtt',  'http://203.57.40.105:10183/files/7100-rs/subtitles.vtt',  'http://203.57.40.105:10183/files/7100-sp/subtitles.vtt',  'http://203.57.40.105:10183/files/7100-id/subtitles.vtt'],
    //         ['video_id_4120',  'http://203.57.40.105:10180/storage/23aa4015-ca22-48c7-a97f-941e2edf4a18/video_id_4120.vtt',  'http://203.57.40.105:10183/files/4120-zh/subtitles.vtt',  'http://203.57.40.105:10183/files/4120-en/subtitles.vtt',  'http://203.57.40.105:10183/files/4120-th/subtitles.vtt',  'http://203.57.40.105:10183/files/4120-rs/subtitles.vtt',  'http://203.57.40.105:10183/files/4120-sp/subtitles.vtt',  'http://203.57.40.105:10183/files/4120-id/subtitles.vtt'],
    //         ['video_id_2261',  'http://203.57.40.105:10180/storage/870e3330-2e65-42bb-acae-efa0a98691d4/video_id_2261.vtt',  'http://203.57.40.105:10183/files/2261-zh/subtitles.vtt',  'http://203.57.40.105:10183/files/2261-en/subtitles.vtt',  'http://203.57.40.105:10183/files/2261-th/subtitles.vtt',  'http://203.57.40.105:10183/files/2261-rs/subtitles.vtt',  'http://203.57.40.105:10183/files/2261-sp/subtitles.vtt',  'http://203.57.40.105:10183/files/2261-id/subtitles.vtt'],
    //         ['video_id_16452', 'http://203.57.40.105:10180/storage/e567402f-4da8-4753-b231-c465e3f36b40/video_id_16452.vtt', 'http://203.57.40.105:10183/files/16452-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16452-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16452-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16452-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16452-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16452-id/subtitles.vtt'],
    //         ['video_id_16366', 'http://203.57.40.105:10180/storage/ab96b727-2558-4c7c-b11d-f1aa024bd061/video_id_16366.vtt', 'http://203.57.40.105:10183/files/16366-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16366-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16366-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16366-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16366-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16366-id/subtitles.vtt'],
    //         ['video_id_16489', 'http://203.57.40.105:10180/storage/67ac330d-4fbe-43f8-8816-f52a1ec474f0/video_id_16489.vtt', 'http://203.57.40.105:10183/files/16489-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16489-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16489-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16489-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16489-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16489-id/subtitles.vtt'],
    //         ['video_id_16491', 'http://203.57.40.105:10180/storage/41f7366e-fb9a-4148-93f2-7f6b4a0c1f95/video_id_16491.vtt', 'http://203.57.40.105:10183/files/16491-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16491-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16491-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16491-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16491-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16491-id/subtitles.vtt'],
    //         ['video_id_16492', 'http://203.57.40.105:10180/storage/4d7f91e1-47c3-43d1-9fdf-473ecd43552c/video_id_16492.vtt', 'http://203.57.40.105:10183/files/16492-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16492-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16492-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16492-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16492-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16492-id/subtitles.vtt'],
    //         ['video_id_16493', 'http://203.57.40.105:10180/storage/cbc565f7-b757-4b05-a172-dedb081a1cf8/video_id_16493.vtt', 'http://203.57.40.105:10183/files/16493-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16493-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16493-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16493-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16493-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16493-id/subtitles.vtt'],
    //         ['video_id_16494', 'http://203.57.40.105:10180/storage/5f2a3230-4018-4331-b871-afe7889eef85/video_id_16494.vtt', 'http://203.57.40.105:10183/files/16494-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16494-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16494-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16494-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16494-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16494-id/subtitles.vtt'],
    //         ['video_id_16318', 'http://203.57.40.105:10180/storage/cec4d984-85ea-4e93-aa2d-6699f1e677d2/video_id_16318.vtt', 'http://203.57.40.105:10183/files/16318-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16318-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16318-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16318-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16318-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16318-id/subtitles.vtt'],
    //         ['video_id_16396', 'http://203.57.40.105:10180/storage/c53aac38-801b-49c9-aaa4-1a624f94e447/video_id_16396.vtt', 'http://203.57.40.105:10183/files/16396-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16396-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16396-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16396-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16396-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16396-id/subtitles.vtt'],
    //         ['video_id_16359', 'http://203.57.40.105:10180/storage/822edef8-640e-4f5c-b948-70fd6e41bbc9/video_id_16359.vtt', 'http://203.57.40.105:10183/files/16359-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16359-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16359-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16359-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16359-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16359-id/subtitles.vtt'],
    //         ['video_id_16360', 'http://203.57.40.105:10180/storage/d5f42482-1ffc-426e-bbae-a71e8e07fe7e/video_id_16360.vtt', 'http://203.57.40.105:10183/files/16360-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16360-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16360-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16360-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16360-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16360-id/subtitles.vtt'],
    //         ['video_id_16487', 'http://203.57.40.105:10180/storage/e23211aa-9392-4c1d-8943-57add97c3701/video_id_16487.vtt', 'http://203.57.40.105:10183/files/16487-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16487-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16487-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16487-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16487-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16487-id/subtitles.vtt'],
    //         ['video_id_16374', 'http://203.57.40.105:10180/storage/0cf8cc8b-45d1-4cb0-a292-5be235c2b3cc/video_id_16374.vtt', 'http://203.57.40.105:10183/files/16374-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16374-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16374-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16374-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16374-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16374-id/subtitles.vtt'],
    //         ['video_id_16386', 'http://203.57.40.105:10180/storage/7476fb71-db12-4652-bc07-ad3d4c1fd01d/video_id_16386.vtt', 'http://203.57.40.105:10183/files/16386-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16386-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16386-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16386-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16386-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16386-id/subtitles.vtt'],
    //         ['video_id_16385', 'http://203.57.40.105:10180/storage/b67335c6-8cff-4bd2-88be-4ae2a6ae60f3/video_id_16385.vtt', 'http://203.57.40.105:10183/files/16385-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16385-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16385-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16385-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16385-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16385-id/subtitles.vtt'],
    //         ['video_id_16383', 'http://203.57.40.105:10180/storage/f86d8157-a982-4595-972a-cb4c3113ddac/video_id_16383.vtt', 'http://203.57.40.105:10183/files/16383-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16383-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16383-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16383-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16383-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16383-id/subtitles.vtt'],
    //         ['video_id_16381', 'http://203.57.40.105:10180/storage/49d8a71b-fc92-4b58-87ee-3d117ff624b3/video_id_16381.vtt', 'http://203.57.40.105:10183/files/15/output.vtt', 'http://203.57.40.105:10183/files/16/output.vtt', 'http://203.57.40.105:10183/files/17/output.vtt', 'http://203.57.40.105:10183/files/18/output.vtt', 'http://203.57.40.105:10183/files/19/output.vtt', 'http://203.57.40.105:10183/files/21/output.vtt'],
    //         ['video_id_16370', 'http://203.57.40.105:10180/storage/4530db38-f8e6-47b9-892c-bf1b791e7e1f/video_id_16370.vtt', 'http://203.57.40.105:10183/files/8/output.vtt',  'http://203.57.40.105:10183/files/9/output.vtt',  'http://203.57.40.105:10183/files/10/output.vtt', 'http://203.57.40.105:10183/files/11/output.vtt', 'http://203.57.40.105:10183/files/12/output.vtt', 'http://203.57.40.105:10183/files/14/output.vtt'],
    //         ['video_id_16382', 'http://203.57.40.105:10180/storage/c67fcc7e-d597-4240-baf3-7ce9f975b12b/video_id_16382.vtt', 'http://203.57.40.105:10183/files/22/output.vtt', 'http://203.57.40.105:10183/files/23/output.vtt', 'http://203.57.40.105:10183/files/24/output.vtt', 'http://203.57.40.105:10183/files/25/output.vtt', 'http://203.57.40.105:10183/files/26/output.vtt', 'http://203.57.40.105:10183/files/28/output.vtt'],
    //         ['video_id_16451', 'http://203.57.40.105:10180/storage/b9839c3a-c277-4a7b-bb5e-e7ea186b83d3/video_id_16451.vtt', 'http://203.57.40.105:10183/files/16451-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16451-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16451-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16451-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16451-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16451-id/subtitles.vtt'],
    //         ['video_id_16471', 'http://203.57.40.105:10180/storage/3867f737-6a9f-48da-9083-75a8642c3b66/video_id_16471.vtt', 'http://203.57.40.105:10183/files/16471-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16471-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16471-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16471-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16471-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16471-id/subtitles.vtt'],
    //         ['video_id_16469', 'http://203.57.40.105:10180/storage/af8da9f2-df41-4385-a6d6-a83f29660792/video_id_16469.vtt', 'http://203.57.40.105:10183/files/16469-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16469-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16469-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16469-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16469-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16469-id/subtitles.vtt'],
    //         ['video_id_16468', 'http://203.57.40.105:10180/storage/92da1a3c-a745-419a-a880-eb474f98ae4c/video_id_16468.vtt', 'http://203.57.40.105:10183/files/16468-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16468-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16468-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16468-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16468-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16468-id/subtitles.vtt'],
    //         ['video_id_16467', 'http://203.57.40.105:10180/storage/202d26e3-53e5-4837-af19-721512cbb6ca/video_id_16467.vtt', 'http://203.57.40.105:10183/files/16467-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16467-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16467-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16467-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16467-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16467-id/subtitles.vtt'],
    //         ['video_id_16461', 'http://203.57.40.105:10180/storage/e596e29b-1703-4327-8acd-c8824ef298d1/video_id_16461.vtt', 'http://203.57.40.105:10183/files/16461-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16461-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16461-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16461-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16461-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16461-id/subtitles.vtt'],
    //         ['video_id_16460', 'http://203.57.40.105:10180/storage/865d4977-623b-45f0-ab3f-58ba895edcf2/video_id_16460.vtt', 'http://203.57.40.105:10183/files/16460-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16460-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16460-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16460-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16460-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16460-id/subtitles.vtt'],
    //         ['video_id_16458', 'http://203.57.40.105:10180/storage/dbf1a3a6-6b2f-473c-a3ec-8361bce793a7/video_id_16458.vtt', 'http://203.57.40.105:10183/files/16458-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16458-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16458-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16458-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16458-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16458-id/subtitles.vtt'],
    //         ['video_id_16457', 'http://203.57.40.105:10180/storage/4573362a-ad36-486e-8f98-3c03b4011116/video_id_16457.vtt', 'http://203.57.40.105:10183/files/16457-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16457-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16457-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16457-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16457-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16457-id/subtitles.vtt'],
    //         ['video_id_16456', 'http://203.57.40.105:10180/storage/1ac8d871-b0cb-4c2b-b1f6-f22b1b388349/video_id_16456.vtt', 'http://203.57.40.105:10183/files/16456-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16456-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16456-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16456-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16456-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16456-id/subtitles.vtt'],
    //         ['video_id_16453', 'http://203.57.40.105:10180/storage/8dc8f482-915f-443d-bced-ba6d44cc8914/video_id_16453.vtt', 'http://203.57.40.105:10183/files/16453-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16453-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16453-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16453-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16453-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16453-id/subtitles.vtt'],
    //         ['video_id_16470', 'http://203.57.40.105:10180/storage/c4024585-37a3-4d4d-b675-20b7a7dc679e/video_id_16470.vtt', 'http://203.57.40.105:10183/files/16470-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16470-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16470-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16470-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16470-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16470-id/subtitles.vtt'],
    //         ['video_id_16472', 'http://203.57.40.105:10180/storage/afc61dce-6f3d-4e0d-852b-ee098c66f8ce/video_id_16472.vtt', 'http://203.57.40.105:10183/files/16472-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16472-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16472-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16472-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16472-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16472-id/subtitles.vtt'],
    //         ['video_id_16473', 'http://203.57.40.105:10180/storage/0643ab2b-7e8b-4722-b112-0919d94b5086/video_id_16473.vtt', 'http://203.57.40.105:10183/files/16473-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16473-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16473-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16473-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16473-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16473-id/subtitles.vtt'],
    //         ['video_id_16474', 'http://203.57.40.105:10180/storage/0b530dc9-71f1-4818-b82d-26ed1effc7b0/video_id_16474.vtt', 'http://203.57.40.105:10183/files/16474-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16474-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16474-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16474-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16474-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16474-id/subtitles.vtt'],
    //         ['video_id_16476', 'http://203.57.40.105:10180/storage/7ede65e7-69c4-4fe3-83cf-80016eb2e064/video_id_16476.vtt', 'http://203.57.40.105:10183/files/16476-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16476-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16476-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16476-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16476-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16476-id/subtitles.vtt'],
    //         ['video_id_16477', 'http://203.57.40.105:10180/storage/b955c221-414d-4fdd-85b5-806f97f0bb5b/video_id_16477.vtt', 'http://203.57.40.105:10183/files/16477-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16477-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16477-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16477-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16477-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16477-id/subtitles.vtt'],
    //         ['video_id_16478', 'http://203.57.40.105:10180/storage/5e826cc1-e4cf-4534-97de-77c3dcc97f7e/video_id_16478.vtt', 'http://203.57.40.105:10183/files/16478-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16478-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16478-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16478-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16478-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16478-id/subtitles.vtt'],
    //         ['video_id_16479', 'http://203.57.40.105:10180/storage/3aaa3c68-1790-428b-bef3-1943cc89624c/video_id_16479.vtt', 'http://203.57.40.105:10183/files/16479-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16479-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16479-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16479-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16479-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16479-id/subtitles.vtt'],
    //         ['video_id_16480', 'http://203.57.40.105:10180/storage/cbb7e691-bcc2-4e7d-a6fc-2f9b252cfa74/video_id_16480.vtt', 'http://203.57.40.105:10183/files/16480-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16480-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16480-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16480-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16480-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16480-id/subtitles.vtt'],
    //         ['video_id_16484', 'http://203.57.40.105:10180/storage/9adcf873-75a4-4ada-8793-b819b79d609d/video_id_16484.vtt', 'http://203.57.40.105:10183/files/16484-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/16484-en/subtitles.vtt', 'http://203.57.40.105:10183/files/16484-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16484-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/16484-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/16484-id/subtitles.vtt'],
    //         ['video_id_16361', 'http://203.57.40.105:10180/storage/c00b709d-92ce-412d-8f4c-fc2813f9d62d/video_id_16361.vtt', 'http://203.57.40.105:10183/files/16361-zh/subtitles.vtt', 'http://203.57.40.105:10183/files/video_id_16361-en/subtitles.vtt', 'http://203.57.40.105:10183/files/video_id_16361-th/subtitles.vtt', 'http://203.57.40.105:10183/files/16361-rs/subtitles.vtt', 'http://203.57.40.105:10183/files/video_id_16361-sp/subtitles.vtt', 'http://203.57.40.105:10183/files/video_id_16361-id/subtitles.vtt'],
    //     ];

    //     // Process first N rows only — set to count($rows) when ready for all
    //     $rows = array_slice($rows, 80, 20);

    //     // col index → [db field, lang folder]
    //     $langMap = [
    //         1 => ['zimu',    'ja'],
    //         2 => ['zimu_zh', 'zh'],
    //         3 => ['zimu_en', 'en'],
    //         4 => ['zimu_th', 'th'],
    //         5 => ['zimu_ru', 'ru'],
    //         6 => ['zimu_es', 'es'],
    //         7 => ['zimu_ms', 'ms'],
    //     ];

    //     $tmpRoot     = rtrim(ROOT_PATH . 'runtime/zimu_tmp/', '/') . '/';
    //     $rsyncUser   = config('zimu.rsync.user');
    //     $rsyncHost   = config('zimu.rsync.host');
    //     $rsyncModule = config('zimu.rsync.module');
    //     $rsyncPass   = config('zimu.rsync.password');

    //     if (!is_dir($tmpRoot)) {
    //         mkdir($tmpRoot, 0755, true);
    //     }

    //     $total = count($rows);
    //     $done  = 0;
    //     $errors = [];

    //     echo "<pre>\n";
    //     echo "Starting sync for {$total} videos...\n\n";

    //     foreach ($rows as $row) {
    //         $externalId = $row[0]; // e.g. video_id_16372
    //         preg_match('/video_id_(\d+)/', $externalId, $m);
    //         $videoId = (int)($m[1] ?? 0);
    //         if (!$videoId) {
    //             echo "[SKIP] Invalid external id: {$externalId}\n";
    //             continue;
    //         }

    //         $video = VideoModel::find($videoId);
    //         if (!$video) {
    //             echo "[SKIP] video id={$videoId} not found in DB\n";
    //             $errors[] = $externalId . ' - not in DB';
    //             continue;
    //         }

    //         echo "[{$externalId}] Processing...\n";
    //         $subtitleBase = '/ms/amnew/' . $externalId;
    //         $updateFields = [];

    //         foreach ($langMap as $colIdx => [$dbField, $langFolder]) {
    //             $srcUrl = $row[$colIdx] ?? '';
    //             if (empty($srcUrl)) {
    //                 continue;
    //             }

    //             // Download VTT content
    //             $vttContent = @file_get_contents($srcUrl);
    //             if (empty($vttContent)) {
    //                 echo "  [{$langFolder}] DOWNLOAD FAILED: {$srcUrl}\n";
    //                 $errors[] = "{$externalId}/{$langFolder} - download failed";
    //                 continue;
    //             }

    //             // Write temp file mirroring remote path structure for --relative
    //             $localDir = $tmpRoot . ltrim($subtitleBase, '/') . '/' . $langFolder . '/';
    //             if (!is_dir($localDir)) {
    //                 mkdir($localDir, 0755, true);
    //             }
    //             $tmpFile = $localDir . 'subtitles.vtt';
    //             file_put_contents($tmpFile, $vttContent);

    //             // Temp rsync password file
    //             $passFile = $tmpRoot . 'rsync_pass_' . uniqid();
    //             file_put_contents($passFile, $rsyncPass);
    //             chmod($passFile, 0600);

    //             // Use --relative with ./ marker so rsync creates full dir tree on remote
    //             $localWithMarker = $tmpRoot . './' . ltrim($subtitleBase, '/') . '/' . $langFolder . '/subtitles.vtt';
    //             $remoteBase = sprintf('%s@%s::%s', $rsyncUser, $rsyncHost, $rsyncModule);
    //             $cmd = sprintf(
    //                 'rsync -avrP --relative %s %s --password-file=%s 2>&1',
    //                 escapeshellarg($localWithMarker),
    //                 escapeshellarg($remoteBase),
    //                 escapeshellarg($passFile)
    //             );
    //             $output = shell_exec($cmd);

    //             @unlink($tmpFile);
    //             @unlink($passFile);
    //             @rmdir($localDir);

    //             if ($output !== null && (strpos($output, 'sent ') !== false || strpos($output, 'bytes/sec') !== false)) {
    //                 $relativePath = "{$subtitleBase}/{$langFolder}/subtitles.vtt";
    //                 $updateFields[$dbField] = $relativePath;
    //                 echo "  [{$langFolder}] OK -> {$relativePath}\n";
    //             } else {
    //                 echo "  [{$langFolder}] RSYNC FAILED: " . trim($output) . "\n";
    //                 $errors[] = "{$externalId}/{$langFolder} - rsync failed";
    //             }
    //         }

    //         if (!empty($updateFields)) {
    //             $updateFields['zimu_status'] = 4;
    //             try {
    //                 $dbHost = config('database.hostname');
    //                 if ($dbHost === 'localhost') $dbHost = '127.0.0.1';
    //                 $pdo = new \PDO(
    //                     'mysql:host=' . $dbHost . ';port=' . config('database.hostport') . ';dbname=' . config('database.database') . ';charset=utf8mb4',
    //                     config('database.username'),
    //                     config('database.password'),
    //                     [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
    //                 );
    //                 $sets = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($updateFields)));
    //                 $stmt = $pdo->prepare("UPDATE video SET {$sets} WHERE id = ?");
    //                 $stmt->execute([...array_values($updateFields), $videoId]);
    //                 echo "  DB updated (zimu_status=4)\n";
    //             } catch (\Throwable $e) {
    //                 echo "  DB UPDATE FAILED: " . $e->getMessage() . "\n";
    //             }
    //         }

    //         $done++;
    //         echo "\n";
    //     }

    //     echo "============================\n";
    //     echo "Done: {$done}/{$total}\n";
    //     if (!empty($errors)) {
    //         echo "Errors (" . count($errors) . "):\n";
    //         foreach ($errors as $e) {
    //             echo "  - {$e}\n";
    //         }
    //     }
    //     echo "</pre>";
    // }
    
    // public function syncViSubtitles()
    // {
    //     set_time_limit(0);
    //     @ob_implicit_flush(true);
    //     @ob_end_flush();

    //     // [external_id, vi_url]
    //     $rows = [
    //         ['video_id_16372', 'http://203.57.40.105:10183/files/16372-vt/subtitles.vtt'],
    //         ['video_id_16319', 'http://203.57.40.105:10183/files/6/output.vtt'],
    //         ['video_id_16379', 'http://203.57.40.105:10183/files/16379-vt/subtitles.vtt'],
    //         ['video_id_16371', 'http://203.57.40.105:10183/files/16371-vt/subtitles.vtt'],
    //         ['video_id_16511', 'http://203.57.40.105:10183/files/16511-vt/subtitles.vtt'],
    //         ['video_id_16512', 'http://203.57.40.105:10183/files/16512-vt/subtitles.vtt'],
    //         ['video_id_16513', 'http://203.57.40.105:10183/files/16513-vt/subtitles.vtt'],
    //         ['video_id_16483', 'http://203.57.40.105:10183/files/16483-vt/subtitles.vtt'],
    //         ['video_id_16408', 'http://203.57.40.105:10183/files/16408-vt/subtitles.vtt'],
    //         ['video_id_16409', 'http://203.57.40.105:10183/files/16409-vt/subtitles.vtt'],
    //         ['video_id_16410', 'http://203.57.40.105:10183/files/16410-vt/subtitles.vtt'],
    //         ['video_id_16411', 'http://203.57.40.105:10183/files/16411-vt/subtitles.vtt'],
    //         ['video_id_16414', 'http://203.57.40.105:10183/files/16414-vt/subtitles.vtt'],
    //         ['video_id_16415', 'http://203.57.40.105:10183/files/16415-vt/subtitles.vtt'],
    //         ['video_id_16419', 'http://203.57.40.105:10183/files/16419-vt/subtitles.vtt'],
    //         ['video_id_16423', 'http://203.57.40.105:10183/files/16423-vt/subtitles.vtt'],
    //         ['video_id_16424', 'http://203.57.40.105:10183/files/16424-vt/subtitles.vtt'],
    //         ['video_id_16425', 'http://203.57.40.105:10183/files/16425-vt/subtitles.vtt'],
    //         ['video_id_16426', 'http://203.57.40.105:10183/files/16426-vt/subtitles.vtt'],
    //         ['video_id_16427', 'http://203.57.40.105:10183/files/16427-vt/subtitles.vtt'],
    //         ['video_id_16429', 'http://203.57.40.105:10183/files/16429-vt/subtitles.vtt'],
    //         ['video_id_16434', 'http://203.57.40.105:10183/files/16434-vt/subtitles.vtt'],
    //         ['video_id_16435', 'http://203.57.40.105:10183/files/16435-vt/subtitles.vtt'],
    //         ['video_id_16436', 'http://203.57.40.105:10183/files/16436-vt/subtitles.vtt'],
    //         ['video_id_16437', 'http://203.57.40.105:10183/files/16437-vt/subtitles.vtt'],
    //         ['video_id_16438', 'http://203.57.40.105:10183/files/16438-vt/subtitles.vtt'],
    //         ['video_id_16439', 'http://203.57.40.105:10183/files/16439-vt/subtitles.vtt'],
    //         ['video_id_16440', 'http://203.57.40.105:10183/files/16440-vt/subtitles.vtt'],
    //         ['video_id_16441', 'http://203.57.40.105:10183/files/16441-vt/subtitles.vtt'],
    //         ['video_id_16442', 'http://203.57.40.105:10183/files/16442-vt/subtitles.vtt'],
    //         ['video_id_16443', 'http://203.57.40.105:10183/files/16443-vt/subtitles.vtt'],
    //         ['video_id_16444', 'http://203.57.40.105:10183/files/16444-vt/subtitles.vtt'],
    //         ['video_id_16445', 'http://203.57.40.105:10183/files/16445-vt/subtitles.vtt'],
    //         ['video_id_16446', 'http://203.57.40.105:10183/files/16446-vt/subtitles.vtt'],
    //         ['video_id_16447', 'http://203.57.40.105:10183/files/16447-vt/subtitles.vtt'],
    //         ['video_id_16449', 'http://203.57.40.105:10183/files/16449-vt/subtitles.vtt'],
    //         ['video_id_16070', 'http://203.57.40.105:10183/files/16070-vt/subtitles.vtt'],
    //         ['video_id_2329',  'http://203.57.40.105:10183/files/2329-vt/subtitles.vtt'],
    //         ['video_id_7100',  'http://203.57.40.105:10183/files/7100-vt/subtitles.vtt'],
    //         ['video_id_4120',  'http://203.57.40.105:10183/files/4120-vt/subtitles.vtt'],
    //         ['video_id_2261',  'http://203.57.40.105:10183/files/2261-vt/subtitles.vtt'],
    //         ['video_id_16452', 'http://203.57.40.105:10183/files/16452-vt/subtitles.vtt'],
    //         ['video_id_16366', 'http://203.57.40.105:10183/files/16366-vt/subtitles.vtt'],
    //         ['video_id_16489', 'http://203.57.40.105:10183/files/16489-vt/subtitles.vtt'],
    //         ['video_id_16491', 'http://203.57.40.105:10183/files/16491-vt/subtitles.vtt'],
    //         ['video_id_16492', 'http://203.57.40.105:10183/files/16492-vt/subtitles.vtt'],
    //         ['video_id_16493', 'http://203.57.40.105:10183/files/16493-vt/subtitles.vtt'],
    //         ['video_id_16494', 'http://203.57.40.105:10183/files/16494-vt/subtitles.vtt'],
    //         ['video_id_16318', 'http://203.57.40.105:10183/files/16318-vt/subtitles.vtt'],
    //         ['video_id_16396', 'http://203.57.40.105:10183/files/16396-vt/subtitles.vtt'],
    //         ['video_id_16359', 'http://203.57.40.105:10183/files/16359-vt/subtitles.vtt'],
    //         ['video_id_16360', 'http://203.57.40.105:10183/files/16360-vt/subtitles.vtt'],
    //         ['video_id_16487', 'http://203.57.40.105:10183/files/16487-vt/subtitles.vtt'],
    //         ['video_id_16374', 'http://203.57.40.105:10183/files/16374-vt/subtitles.vtt'],
    //         ['video_id_16386', 'http://203.57.40.105:10183/files/16386-vt/subtitles.vtt'],
    //         ['video_id_16385', 'http://203.57.40.105:10183/files/16385-vt/subtitles.vtt'],
    //         ['video_id_16383', 'http://203.57.40.105:10183/files/16383-vt/subtitles.vtt'],
    //         ['video_id_16381', 'http://203.57.40.105:10183/files/20/output.vtt'],
    //         ['video_id_16370', 'http://203.57.40.105:10183/files/13/output.vtt'],
    //         ['video_id_16382', 'http://203.57.40.105:10183/files/27/output.vtt'],
    //         ['video_id_16451', 'http://203.57.40.105:10183/files/16451-vt/subtitles.vtt'],
    //         ['video_id_16471', 'http://203.57.40.105:10183/files/16471-vt/subtitles.vtt'],
    //         ['video_id_16469', 'http://203.57.40.105:10183/files/16469-vt/subtitles.vtt'],
    //         ['video_id_16468', 'http://203.57.40.105:10183/files/16468-vt/subtitles.vtt'],
    //         ['video_id_16467', 'http://203.57.40.105:10183/files/16467-vt/subtitles.vtt'],
    //         ['video_id_16461', 'http://203.57.40.105:10183/files/16461-vt/subtitles.vtt'],
    //         ['video_id_16460', 'http://203.57.40.105:10183/files/16460-vt/subtitles.vtt'],
    //         ['video_id_16458', 'http://203.57.40.105:10183/files/16458-vt/subtitles.vtt'],
    //         ['video_id_16457', 'http://203.57.40.105:10183/files/16457-vt/subtitles.vtt'],
    //         ['video_id_16456', 'http://203.57.40.105:10183/files/16456-vt/subtitles.vtt'],
    //         ['video_id_16453', 'http://203.57.40.105:10183/files/16453-vt/subtitles.vtt'],
    //         ['video_id_16470', 'http://203.57.40.105:10183/files/16470-vt/subtitles.vtt'],
    //         ['video_id_16472', 'http://203.57.40.105:10183/files/16472-vt/subtitles.vtt'],
    //         ['video_id_16473', 'http://203.57.40.105:10183/files/16473-vt/subtitles.vtt'],
    //         ['video_id_16474', 'http://203.57.40.105:10183/files/16474-vt/subtitles.vtt'],
    //         ['video_id_16476', 'http://203.57.40.105:10183/files/16476-vt/subtitles.vtt'],
    //         ['video_id_16477', 'http://203.57.40.105:10183/files/16477-vt/subtitles.vtt'],
    //         ['video_id_16478', 'http://203.57.40.105:10183/files/16478-vt/subtitles.vtt'],
    //         ['video_id_16479', 'http://203.57.40.105:10183/files/16479-vt/subtitles.vtt'],
    //         ['video_id_16480', 'http://203.57.40.105:10183/files/16480-vt/subtitles.vtt'],
    //         ['video_id_16484', 'http://203.57.40.105:10183/files/16484-vt/subtitles.vtt'],
    //         ['video_id_16361', 'http://203.57.40.105:10183/files/video_id_16361-vt/subtitles.vtt'],
    //     ];

    //     // Process first 20 rows only — remove slice when ready for all
    //     $rows = array_slice($rows, 80, 40);

    //     $tmpRoot     = rtrim(ROOT_PATH . 'runtime/zimu_tmp/', '/') . '/';
    //     $rsyncUser   = config('zimu.rsync.user');
    //     $rsyncHost   = config('zimu.rsync.host');
    //     $rsyncModule = config('zimu.rsync.module');
    //     $rsyncPass   = config('zimu.rsync.password');

    //     if (!is_dir($tmpRoot)) {
    //         mkdir($tmpRoot, 0755, true);
    //     }

    //     $total  = count($rows);
    //     $done   = 0;
    //     $errors = [];

    //     echo "<pre>\n";
    //     echo "Starting vi subtitle sync for {$total} videos...\n\n";

    //     foreach ($rows as $row) {
    //         $externalId = $row[0];
    //         $viUrl      = $row[1] ?? '';

    //         preg_match('/video_id_(\d+)/', $externalId, $m);
    //         $videoId = (int)($m[1] ?? 0);
    //         if (!$videoId) {
    //             echo "[SKIP] Invalid external id: {$externalId}\n";
    //             continue;
    //         }

    //         $video = VideoModel::find($videoId);
    //         if (!$video) {
    //             echo "[SKIP] video id={$videoId} not found in DB\n";
    //             $errors[] = $externalId . ' - not in DB';
    //             continue;
    //         }

    //         echo "[{$externalId}] Processing...\n";

    //         if (empty($viUrl)) {
    //             echo "  [vi] SKIP: no URL\n";
    //             continue;
    //         }

    //         $subtitleBase = '/ms/amnew/' . $externalId;

    //         // Download VTT
    //         $vttContent = @file_get_contents($viUrl);
    //         if (empty($vttContent)) {
    //             echo "  [vi] DOWNLOAD FAILED: {$viUrl}\n";
    //             $errors[] = "{$externalId}/vi - download failed";
    //             continue;
    //         }

    //         // Write temp file mirroring remote path structure for --relative
    //         $localDir = $tmpRoot . ltrim($subtitleBase, '/') . '/vi/';
    //         if (!is_dir($localDir)) {
    //             mkdir($localDir, 0755, true);
    //         }
    //         $tmpFile  = $localDir . 'subtitles.vtt';
    //         file_put_contents($tmpFile, $vttContent);

    //         $passFile = $tmpRoot . 'rsync_pass_' . uniqid();
    //         file_put_contents($passFile, $rsyncPass);
    //         chmod($passFile, 0600);

    //         $localWithMarker = $tmpRoot . './' . ltrim($subtitleBase, '/') . '/vi/subtitles.vtt';
    //         $remoteBase      = sprintf('%s@%s::%s', $rsyncUser, $rsyncHost, $rsyncModule);
    //         $cmd = sprintf(
    //             'rsync -avrP --relative %s %s --password-file=%s 2>&1',
    //             escapeshellarg($localWithMarker),
    //             escapeshellarg($remoteBase),
    //             escapeshellarg($passFile)
    //         );
    //         $output = shell_exec($cmd);

    //         @unlink($tmpFile);
    //         @unlink($passFile);
    //         @rmdir($localDir);

    //         $relativePath = "{$subtitleBase}/vi/subtitles.vtt";

    //         if ($output !== null && (strpos($output, 'sent ') !== false || strpos($output, 'bytes/sec') !== false)) {
    //             echo "  [vi] OK -> {$relativePath}\n";
    //             try {
    //                 $dbHost = config('database.hostname');
    //                 if ($dbHost === 'localhost') $dbHost = '127.0.0.1';
    //                 $pdo = new \PDO(
    //                     'mysql:host=' . $dbHost . ';port=' . config('database.hostport') . ';dbname=' . config('database.database') . ';charset=utf8mb4',
    //                     config('database.username'),
    //                     config('database.password'),
    //                     [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
    //                 );
    //                 $stmt = $pdo->prepare("UPDATE video SET `zimu_vi` = ? WHERE id = ?");
    //                 $stmt->execute([$relativePath, $videoId]);
    //                 echo "  DB updated (zimu_vi)\n";
    //             } catch (\Throwable $e) {
    //                 echo "  DB UPDATE FAILED: " . $e->getMessage() . "\n";
    //             }
    //         } else {
    //             echo "  [vi] RSYNC FAILED: " . trim($output) . "\n";
    //             $errors[] = "{$externalId}/vi - rsync failed";
    //         }

    //         $done++;
    //         echo "\n";
    //     }

    //     echo "============================\n";
    //     echo "Done: {$done}/{$total}\n";
    //     if (!empty($errors)) {
    //         echo "Errors (" . count($errors) . "):\n";
    //         foreach ($errors as $e) {
    //             echo "  - {$e}\n";
    //         }
    //     }
    //     echo "</pre>";
    // }

    public function check_mash()
    {
        $param = request()->post();
        $mash  = $param['mash'];
        
        if (empty($mash)) {
            return json(['code' => 400, 'msg' => '缺少番号参数']);
        }

        // Normalize: remove hyphen so both abc123 / abc-123 match
        $normalized = str_replace('-', '', $mash);
        $exists     = VideoModel::whereRaw("REPLACE(UPPER(mash), '-', '') = '{$normalized}'")->value('id');

        if ($exists) {
            return json(['code' => 200, 'msg' => '视频已经入库']);
        } else {
            return json(['code' => 404, 'msg' => '未能找到相关番号']);
        }
    }

    public function call_video()
    {
        $time = time();
        try {
            $rawInput = file_get_contents('php://input');
            save_log(json_encode(input(), JSON_UNESCAPED_UNICODE), 'ms_video');
            
            $data = json_decode($rawInput, true);
            if (empty($data['uid']) || empty($data['video'])) {
                return json(['code' => 0, 'msg' => '参数格式不正确']);
            }

            $remark         = $data['remark'] ?? [];
            $remark         = json_decode($remark, true);
            $title          = trim($remark['title'] ?? '') ?: trim($data['description'] ?? '');
            $mash           = trim($remark['fanhao'] ?? ($data['code'] ?? ''));
            $tags           = $data['tag'] ?? [];
            $actorName      = trim($remark['actress_name'] ?? ($data['author'] ?? ''));
            $desc           = trim($remark['description'] ?? ($data['description'] ?? ''));
            $publish_date   = !empty($remark['datePublished']) ? $remark['datePublished'] : $time;
            $publisher_name = !empty($remark['maker_name']) ? $remark['maker_name'] : "默认";

            // Remove mash from title and desc if present
            if (!empty($mash)) {
                $title = trim(str_replace($mash, '', $title));
                $desc  = trim(str_replace($mash, '', $desc));
            }
            
            if(VideoModel::where('identifier', $data['uid'])->value('id') || (!empty($mash) && VideoModel::where('mash', strtoupper($mash))->value('id'))) {
                $this->updateVideo($data);
                return json(['code' => 200, 'msg' => '视频已经更新']);

            }

            // handle multi actor start
            $actorRaw   = $remark['actress_name'] ?? ($data['author'] ?? '');
            $actorNames = [];

            // normalize: allow string or array, split by common separators
            if (is_string($actorRaw) && $actorRaw !== '') {
                $parts = preg_split('/[,\s，、;\/]+/u', $actorRaw);
                foreach ($parts as $p) {
                    $name = trim($p);
                    if ($name !== '') $actorNames[] = $name;
                }
            } elseif (is_array($actorRaw)) {
                foreach ($actorRaw as $p) {
                    $name = trim($p);
                    if ($name !== '') $actorNames[] = $name;
                }
            }
            // lookup/insert actor ids
            $actorIds = [];
            foreach ($actorNames as $name) {
                $existingId = ActorModel::where('name', $name)->value('id');
                if ($existingId) {
                    $actorIds[] = $existingId;
                } else {
                    $id = ActorModel::insertGetId(['name' => $name]);
                    if ($id) {
                        $actorModel = new ActorModel(['id' => $id, 'name' => $name]);
                        Translate::submit("actor", (int)$id);
                        // Translate::handle($actorModel, null);
                        $actorIds[] = $id;
                    }
                }
            }

            // fallback: if no actor found/parsed, keep it 0 (or empty) — matches your tag-style storage
            $actorIdsString = !empty($actorIds) ? implode(',', $actorIds) : '0';
            // handle multi actor end

            $publisher_id = Publisher::where('name', $publisher_name)->value('id');
            if (!$publisher_id) {
                $publisher_id = Publisher::insertGetId(['name' => $publisher_name]);
                // translate
                $publisher = new Publisher(['id' => $publisher_id, 'name' => $publisher_name]);
                Translate::submit("publisher", (int)$publisher_id);
                // Translate::handle($publisher, null);
            }
            
            $insert = [
                'identifier'   => $data['uid'],
                'title'        => $title,
                'mash'         => strtoupper($mash),
                'duration'     => $data['video']['duration'],
                'tags'         => $this->getTags($tags),
                'actor'        => $actorIdsString,
                'thumb'        => $data['video']['cover'] ?? '',
                'thumb_series' => implode(',', $data['video']['thumb_series']) ?? '',
                'preview'      => $data['video']['cover'] ?? '',
                'panorama'     => $data['video']['thumb_longview'] ?? '',
                'status'       => 1,
                'description'  => $desc,
                'video_url'    => $data['video']['play_url'] ?? '',
                'insert_time'  => $time,
                'update_time'  => $time,
                'publish_date' => $publish_date,
                'private'      => 2,
                'hotlist'      => 0,
                'publisher'    => $publisher_id,
                'mosaic'       => 0,
                'subtitle'     => 1,
                'zimu'         => $this->findVtt($data['subtitle']['or']),
                'zimu_zh'      => $this->findVtt($data['subtitle']['zh']),
                'zimu_en'      => $this->findVtt($data['subtitle']['en']),
                'zimu_ru'      => $this->findVtt($data['subtitle']['ru']),
                'zimu_ms'      => $this->findVtt($data['subtitle']['ms']),
                'zimu_th'      => $this->findVtt($data['subtitle']['th']),
                'zimu_es'      => $this->findVtt($data['subtitle']['es']),
                'data'         => json_encode($data, JSON_UNESCAPED_UNICODE),
            ];
            
            $videoId = VideoModel::insertGetId($insert);
            if (!$videoId) {
                \think\facade\Log::error('video failed');
                \think\facade\Log::error($mash);
                return json(['code' => 0, 'msg' => '入库失败']);
            }

            if (!empty($actorIds)) {
                ActorModel::whereIn('id', $actorIds)->inc('video_count')->update();
            }
            Publisher::where('id', $publisher_id)->inc('video_count')->update();
            $tagIds = explode(',', $insert['tags']);
            TagsModel::whereIn('id', $tagIds)->inc('video_count')->update();

            // translate
            // Translate::translateVideo($videoId);
        } catch (\Exception $e) {
            save_log($e->getMessage(), 'ms_error');
            \think\facade\Log::error('video failed');
            \think\facade\Log::error($mash);
            return json(['code' => 0, 'msg' => '入库失败']);
        }
        return json(['code' => 200, 'msg' => '入库成功']);
    }

    private function getTags($tags)
    {
        $tagIds = [];
        foreach ($tags as $tag) {
            $existing = TagsModel::where('name', $tag)->find();
            if ($existing) {
                $tagIds[] = $existing->id;
            } else {
                $id = TagsModel::insertGetId(['name' => $tag]);
                $tagDetails = new TagsModel(['id' => $id, 'name' => $tag]);
                Translate::submit("tag", (int)$id);
                // Translate::handle($tagDetails, null);
                $tagIds[] = $id;
            }
        }
        return implode(',', $tagIds);
    }

    private function updateVideo($data)
    {
        $param['thumb_series'] = implode(',', $data['video']['thumb_series']);
        $param['data'] = json_encode($data, JSON_UNESCAPED_UNICODE);

        try {
            $result = VideoModel::where('identifier', $data['uid'])->update($param);
            \think\facade\Log::error('Update result: ' . ($result ? 'SUCCESS' : 'FAILED'));
            \think\facade\Log::error('Rows affected: ' . $result);
            return $result;
        } catch (\Exception $e) {
            \think\facade\Log::error('Update error: ' . $e->getMessage());
            return false;
        }
    }

    private function findVtt($list)
    {
        if (empty($list) || !is_array($list)) {
            return '';
        }

        foreach ($list as $item) {
            if (!is_string($item) || $item === '') continue;
            $path = parse_url($item, PHP_URL_PATH) ?: $item;
            if (strlen($path) >= 14 && strcasecmp(substr($path, -14), '/subtitles.vtt') === 0) {
                return $item;
            }
        }
        return '';
    }

    public function testing(){
        $count = VideoModel::where(function($query) {
            $query->whereRaw('title = description')
                ->whereOr(function($q) {
                    $q->whereRaw("title LIKE CONCAT('%', mash, '%')")
                        ->where('mash', '<>', '');
                });
        })
        ->whereNotNull('data')
        ->where('data', '<>', '')
        ->count();

        echo "Affected records: " . $count;
    }


    public function fixIssue(){
        $count = 0;

        // $videos = VideoModel::where(function($query) {
        //     $query->whereRaw('title = description')
        //         ->whereOr(function($q) {
        //             $q->whereRaw("title LIKE CONCAT('%', mash, '%')")
        //                 ->where('mash', '<>', '');
        //         });
        // })
        // ->whereNotNull('data')
        // ->where('data', '<>', '')
        // ->where('id', '>=', 7000)
        // ->where('id', '<=', 9000)
        // ->limit(150)
        // ->select();
        $videos = VideoModel::whereNotNull('data')
            ->where('data', '<>', '')
            ->where('id', '>=', 7000)
            ->where('id', '<=', 16096)
            ->whereRaw("title != JSON_UNQUOTE(JSON_EXTRACT(data, '$.title'))")
            ->limit(150)
            ->select();

        foreach ($videos as $video) {

                $data = json_decode($video['data'], true);
                if (empty($data)) continue;

                $remark = json_decode($data['remark'] ?? '[]', true) ?? [];
                $mash   = strtoupper(trim($remark['fanhao'] ?? ($data['code'] ?? '')));

                $title = trim($data['title'] ?? $data['description'] ?? '');
                $desc  = trim($remark['description'] ?? ($data['description'] ?? ''));

                if (!empty($mash)) {
                    $title = trim(str_replace($mash, '', $title));
                    $desc  = trim(str_replace($mash, '', $desc));
                }

                VideoModel::where('id', $video['id'])->update([
                    'title'            => $title,
                    'title_zh'         => "",
                    'title_twzh'       => "",
                    'title_en'         => "",
                    'title_ru'         => "",
                    'title_ms'         => "",
                    'title_th'         => "",
                    'title_es'         => "",
                    'description'      => $desc,
                    'description_zh'   => "",
                    'description_twzh' => "",
                    'description_en'   => "",
                    'description_ru'   => "",
                    'description_ms'   => "",
                    'description_th'   => "",
                    'description_es'   => "",
                ]);
                $count++;
        }

        echo "Updated records: " . $count;
    }
}