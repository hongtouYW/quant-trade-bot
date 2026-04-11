<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tbl_agent', function (Blueprint $table) {
            $table->bigIncrements('agent_id')->primary()->comment('代理商ID');
            $table->string('agent_code')->nullable()->comment('代理商编码');
            $table->string('agent_name')->comment('代理商名字');
            $table->string('country_code', 4)->comment('国家ID');
            $table->string('state_code', 10)->comment('州ID');
            $table->string('title')->nullable()->comment('头衔');
            $table->unsignedBigInteger('upline')->nullable()->comment('上线');
            $table->unsignedBigInteger('master')->nullable()->comment('顶级');
            $table->string('type')->nullable()->comment('代理商类型');
            $table->integer('isChatAccountCreate')->default(0)->comment('聊天账号已开');
            $table->string('support')->nullable()->comment('客服联系');
            $table->string('telegramsupport')->nullable()->comment('纸飞机客服');
            $table->string('whatsappsupport')->nullable()->comment('国际即时通讯客服');
            $table->longText('url')->nullable()->comment('代理链接');
            $table->decimal('balance', 65, 4)->default(0.0000)->comment('余额');
            $table->decimal('principal', 65, 4)->default(0.0000)->comment('当前本⾦');
            $table->string('icon')->nullable()->comment('代理icon');
            $table->integer('status')->default(1)->comment('状态 0 - 屏蔽 1 - 活跃');
            $table->integer('delete')->default(0)->comment('1 - 已删除');
            $table->timestamp('created_on')->nullable()->comment('创建时间');
            $table->timestamp('updated_on')->nullable()->comment('编辑时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_agent');
    }
};
