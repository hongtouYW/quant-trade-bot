<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;

class MergeUsers extends Command
{
    protected function configure()
    {
        $this->setName('merge_users')
            ->setDescription('同步重复账号的数据到主账号，保留副账号原始记录');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln("====== 开始用户数据同步任务 ======");

        // 1. 查找重复用户名
        $duplicates = Db::query("SELECT username, COUNT(*) as count FROM qiswl_member GROUP BY username HAVING count > 1");

        $totalGroups = count($duplicates);
        $output->writeln("发现 {$totalGroups} 组重复用户。");

        foreach ($duplicates as $group) {
            $username = $group['username'];
            
            // 获取同名所有成员，按 viptime 降序排序，VIP时间长的排在前面
            $members = Db::table('qiswl_member')
                ->where('username', $username)
                ->orderRaw('(viptime+0) DESC, id ASC') 
                ->select();

            if (count($members) < 2) continue;

            // 弹出第一个作为主账号（被保留的对象）
            $master = array_shift($members);
            $masterId = $master['id'];
            
            $output->writeln("处理: {$username} -> 主ID: {$masterId}");

            foreach ($members as $index => $slave) {
                $slaveId = $slave['id'];
                
                Db::startTrans();
                try {
                    // --- A. 同步 qiswl_history (主账号不存在才插入) ---
                    $sqlHistory = "INSERT IGNORE INTO qiswl_history (member_id, manhua_id, addtime, type, capter_id)
                                   SELECT ? as master_id, manhua_id, addtime, type, capter_id 
                                   FROM qiswl_history WHERE member_id = ?";
                    Db::execute($sqlHistory, [$masterId, $slaveId]);

                    // --- B. 同步 qiswl_favorites (主账号不存在才插入) ---
                    $sqlFav = "INSERT INTO qiswl_favorites (member_id, manhua_id, addtime, status, type)
                               SELECT ? as master_id, manhua_id, addtime, status, type 
                               FROM qiswl_favorites s
                               WHERE s.member_id = ? 
                               AND NOT EXISTS (
                                   SELECT 1 FROM qiswl_favorites m 
                                   WHERE m.member_id = ? AND m.manhua_id = s.manhua_id
                               )";
                    Db::execute($sqlFav, [$masterId, $slaveId, $masterId]);

                    // --- C. 重命名副账号 ---
                    $suffix = $index + 1;
                    $newUsername = $this->getUniqueUsername($username, $suffix);
                    
                    Db::table('qiswl_member')->where('id', $slaveId)->update([
                        'username' => $newUsername
                    ]);

                    Db::commit();
                } catch (\Exception $e) {
                    Db::rollback();
                    $output->writeln("ID {$slaveId} 处理失败: " . $e->getMessage());
                }
            }
        }

        $output->writeln("SUCCESS: 同步任务全部完成！");
    }

    /**
     * 递归获取不重复的备选用户名
     */
    private function getUniqueUsername($username, $suffix)
    {
        $candidate = $username . '-' . $suffix;
        $exists = Db::table('qiswl_member')->where('username', $candidate)->value('id');
        if ($exists) {
            return $this->getUniqueUsername($username, $suffix + 1);
        }
        return $candidate;
    }
}