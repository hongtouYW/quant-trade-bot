<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subtitle_languages', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('name');
            $table->unsignedInteger('status');
        });

        DB::table('subtitle_languages')->insert([
            [
                'id' => 1,
                'label' => 'en',
                'name' => '英语',
                'status' => 1
            ],
            [
                'id' => 2,
                'label' => 'zh',
                'name' => '中文 / 汉语',
                'status' => 1
            ],
            [
                'id' => 3,
                'label' => 'de',
                'name' => '德语',
                'status' => 1
            ],
            [
                'id' => 4,
                'label' => 'es',
                'name' => '西班牙语',
                'status' => 1
            ],
            [
                'id' => 5,
                'label' => 'ru',
                'name' => '俄语',
                'status' => 1
            ],
            [
                'id' => 6,
                'label' => 'ko',
                'name' => '韩语 / 朝鲜语',
                'status' => 1
            ],
            [
                'id' => 7,
                'label' => 'fr',
                'name' => '法语',
                'status' => 1
            ],
            [
                'id' => 8,
                'label' => 'ja',
                'name' => '日语',
                'status' => 1
            ],
            [
                'id' => 9,
                'label' => 'pt',
                'name' => '葡萄牙语',
                'status' => 1
            ],
            [
                'id' => 10,
                'label' => 'tr',
                'name' => '土耳其语',
                'status' => 1
            ],
            [
                'id' => 11,
                'label' => 'pl',
                'name' => '波兰语',
                'status' => 1
            ],
            [
                'id' => 12,
                'label' => 'ca',
                'name' => '加泰罗尼亚语',
                'status' => 1
            ],
            [
                'id' => 13,
                'label' => 'nl',
                'name' => '荷兰语',
                'status' => 1
            ],
            [
                'id' => 14,
                'label' => 'ar',
                'name' => '阿拉伯语',
                'status' => 1
            ],
            [
                'id' => 15,
                'label' => 'sv',
                'name' => '瑞典语',
                'status' => 1
            ],
            [
                'id' => 16,
                'label' => 'it',
                'name' => '意大利语',
                'status' => 1
            ],
            [
                'id' => 17,
                'label' => 'id',
                'name' => '印度尼西亚语',
                'status' => 1
            ],
            [
                'id' => 18,
                'label' => 'hi',
                'name' => '印地语',
                'status' => 1
            ],
            [
                'id' => 19,
                'label' => 'fi',
                'name' => '芬兰语',
                'status' => 1
            ],
            [
                'id' => 20,
                'label' => 'fi',
                'name' => '越南语',
                'status' => 1
            ],
            [
                'id' => 21,
                'label' => 'he',
                'name' => '希伯来语',
                'status' => 1
            ],
            [
                'id' =>22,
                'label' => 'uk',
                'name' => '乌克兰语',
                'status' => 1
            ],
            [
                'id' => 23,
                'label' => 'el',
                'name' => '希腊语',
                'status' => 1
            ],
            [
                'id' => 24,
                'label' => 'ms',
                'name' => '马来语',
                'status' => 1
            ],
            [
                'id' => 25,
                'label' => 'cs',
                'name' => '捷克语',
                'status' => 1
            ],
            [
                'id' => 26,
                'label' => 'ro',
                'name' => '罗马尼亚语',
                'status' => 1
            ],
            [
                'id' => 27,
                'label' => 'da',
                'name' => '丹麦语',
                'status' => 1
            ],
            [
                'id' => 28,
                'label' => 'hu',
                'name' => '匈牙利语',
                'status' => 1
            ],
            [
                'id' => 29,
                'label' => 'ta',
                'name' => '泰米尔语',
                'status' => 1
            ],
            [
                'id' => 30,
                'label' => 'no',
                'name' => '挪威语',
                'status' => 1
            ],
            [
                'id' => 31,
                'label' => 'th',
                'name' => '泰语',
                'status' => 1
            ],
            [
                'id' => 32,
                'label' => 'ur',
                'name' => '乌尔都语',
                'status' => 1
            ],
            [
                'id' => 33,
                'label' => 'hr',
                'name' => '克罗地亚语',
                'status' => 1
            ],
            [
                'id' => 34,
                'label' => 'bg',
                'name' => '保加利亚语',
                'status' => 1
            ],
            [
                'id' => 35,
                'label' => 'lt',
                'name' => '立陶宛语',
                'status' => 1
            ],
            [
                'id' => 36,
                'label' => 'la',
                'name' => '拉丁语',
                'status' => 1
            ],
            [
                'id' => 37,
                'label' => 'mi',
                'name' => '毛利语',
                'status' => 1
            ],
            [
                'id' => 38,
                'label' => 'ml',
                'name' => '马拉雅拉姆语',
                'status' => 1
            ],
            [
                'id' => 39,
                'label' => 'cy',
                'name' => '威尔士语',
                'status' => 1
            ],
            [
                'id' => 40,
                'label' => 'sk',
                'name' => '斯洛伐克语',
                'status' => 1
            ],
            [
                'id' => 41,
                'label' => 'te',
                'name' => '泰卢固语',
                'status' => 1
            ],
            [
                'id' => 42,
                'label' => 'fa',
                'name' => '波斯语',
                'status' => 1
            ],
            [
                'id' => 43,
                'label' => 'lv',
                'name' => '拉脱维亚语',
                'status' => 1
            ],
            [
                'id' => 44,
                'label' => 'bn',
                'name' => '孟加拉语',
                'status' => 1
            ],
            [
                'id' => 45,
                'label' => 'sr',
                'name' => '塞尔维亚语',
                'status' => 1
            ],
            [
                'id' => 46,
                'label' => 'az',
                'name' => '阿塞拜疆语',
                'status' => 1
            ],
            [
                'id' => 47,
                'label' => 'sl',
                'name' => '斯洛文尼亚语',
                'status' => 1
            ],
            [
                'id' => 48,
                'label' => 'kn',
                'name' => '卡纳达语',
                'status' => 1
            ],
            [
                'id' => 49,
                'label' => 'et',
                'name' => '爱沙尼亚语',
                'status' => 1
            ],
            [
                'id' => 50,
                'label' => 'mk',
                'name' => '马其顿语',
                'status' => 1
            ],
            [
                'id' => 51,
                'label' => 'br',
                'name' => '布列塔尼语',
                'status' => 1
            ],
            [
                'id' => 52,
                'label' => 'eu',
                'name' => '巴斯克语',
                'status' => 1
            ],
            [
                'id' => 53,
                'label' => 'is',
                'name' => '冰岛语',
                'status' => 1
            ],
            [
                'id' => 54,
                'label' => 'hy',
                'name' => '亚美尼亚语',
                'status' => 1
            ],
            [
                'id' => 55,
                'label' => 'ne',
                'name' => '尼泊尔语',
                'status' => 1
            ],
            [
                'id' => 56,
                'label' => 'mn',
                'name' => '蒙古语',
                'status' => 1
            ],
            [
                'id' => 57,
                'label' => 'bs',
                'name' => '波斯尼亚语',
                'status' => 1
            ],
            [
                'id' => 58,
                'label' => 'kk',
                'name' => '哈萨克语',
                'status' => 1
            ],
            [
                'id' => 59,
                'label' => 'sq',
                'name' => '阿尔巴尼亚语',
                'status' => 1
            ],
            [
                'id' => 60,
                'label' => 'sw',
                'name' => '斯瓦希里语',
                'status' => 1
            ],
            [
                'id' => 61,
                'label' => 'gl',
                'name' => '加利西亚语',
                'status' => 1
            ],
            [
                'id' => 62,
                'label' => 'mr',
                'name' => '马拉地语',
                'status' => 1
            ],
            [
                'id' => 63,
                'label' => 'pa',
                'name' => '旁遮普语',
                'status' => 1
            ],
            [
                'id' => 64,
                'label' => 'si',
                'name' => '僧伽罗语',
                'status' => 1
            ],
            [
                'id' => 65,
                'label' => 'km',
                'name' => '高棉语',
                'status' => 1
            ],
            [
                'id' => 66,
                'label' => 'sn',
                'name' => '修纳语',
                'status' => 1
            ],
            [
                'id' => 67,
                'label' => 'yo',
                'name' => '约鲁巴语',
                'status' => 1
            ],
            [
                'id' => 68,
                'label' => 'so',
                'name' => '索马里语',
                'status' => 1
            ],
            [
                'id' => 69,
                'label' => 'af',
                'name' => '南非荷兰语',
                'status' => 1
            ],
            [
                'id' => 70,
                'label' => 'oc',
                'name' => '奥克语',
                'status' => 1
            ],
            [
                'id' => 71,
                'label' => 'ka',
                'name' => '格鲁吉亚语',
                'status' => 1
            ],
            [
                'id' => 72,
                'label' => 'be',
                'name' => '白俄罗斯语',
                'status' => 1
            ],
            [
                'id' => 73,
                'label' => 'tg',
                'name' => '塔吉克语',
                'status' => 1
            ],
            [
                'id' => 74,
                'label' => 'sd',
                'name' => '信德语',
                'status' => 1
            ],
            [
                'id' => 75,
                'label' => 'gu',
                'name' => '古吉拉特语',
                'status' => 1
            ],
            [
                'id' => 76,
                'label' => 'am',
                'name' => '阿姆哈拉语',
                'status' => 1
            ],
            [
                'id' => 77,
                'label' => 'yi',
                'name' => '意第绪语',
                'status' => 1
            ],
            [
                'id' => 78,
                'label' => 'lo',
                'name' => '老挝语',
                'status' => 1
            ],
            [
                'id' => 79,
                'label' => 'uz',
                'name' => '乌兹别克语',
                'status' => 1
            ],
            [
                'id' => 80,
                'label' => 'fo',
                'name' => '法罗语',
                'status' => 1
            ],
            [
                'id' => 81,
                'label' => 'ht',
                'name' => '海地克里奥尔语',
                'status' => 1
            ],
            [
                'id' => 82,
                'label' => 'ps',
                'name' => '普什图语',
                'status' => 1
            ],
            [
                'id' => 83,
                'label' => 'kk',
                'name' => '土库曼语',
                'status' => 1
            ],
            [
                'id' => 84,
                'label' => 'nn',
                'name' => '新挪威语',
                'status' => 1
            ],
            [
                'id' => 85,
                'label' => 'mt',
                'name' => '马耳他语',
                'status' => 1
            ],
            [
                'id' => 86,
                'label' => 'sa',
                'name' => '梵语',
                'status' => 1
            ],
            [
                'id' => 87,
                'label' => 'lb',
                'name' => '卢森堡语',
                'status' => 1
            ],
            [
                'id' => 88,
                'label' => 'my',
                'name' => '缅甸语',
                'status' => 1
            ],
            [
                'id' => 89,
                'label' => 'bo',
                'name' => '藏语',
                'status' => 1
            ],
            [
                'id' => 90,
                'label' => 'tl',
                'name' => '他加禄语',
                'status' => 1
            ],
            [
                'id' => 91,
                'label' => 'mg',
                'name' => '马尔加什语',
                'status' => 1
            ],
            [
                'id' => 92,
                'label' => 'as',
                'name' => '阿萨姆语',
                'status' => 1
            ],
            [
                'id' => 93,
                'label' => 'tt',
                'name' => '鞑靼语',
                'status' => 1
            ],
            [
                'id' => 94,
                'label' => 'haw',
                'name' => '夏威夷语',
                'status' => 1
            ],
            [
                'id' => 95,
                'label' => 'ln',
                'name' => '林加拉语',
                'status' => 1
            ],
            [
                'id' => 96,
                'label' => 'ha',
                'name' => '豪萨语',
                'status' => 1
            ],
            [
                'id' => 97,
                'label' => 'ba',
                'name' => '巴什基尔语',
                'status' => 1
            ],
            [
                'id' => 98,
                'label' => 'jw',
                'name' => '爪哇语',
                'status' => 1
            ],
            [
                'id' => 99,
                'label' => 'su',
                'name' => '巽他语',
                'status' => 1
            ],
            [
                'id' => 100,
                'label' => 'yue',
                'name' => '粤语',
                'status' => 1
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subtitle_languages');
    }
};
