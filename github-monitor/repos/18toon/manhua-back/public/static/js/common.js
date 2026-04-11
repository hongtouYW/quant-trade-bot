/**
 * 公共方法
 * 放在这里的函数可以全局调用
 */

// 全局刷新任务数量
window.loadTaskCount = function(){
    $.get('/notify/count', function(res){
        if(res.code === 1){
            var taskNum = res.data.tasks;

            // 子菜单（我的任务）
            if(taskNum > 0){
                $("#task-count").text(taskNum).show();
            } else {
                $("#task-count").hide();
            }

            // 父菜单（任务管理）
            if(taskNum > 0){
                $("#task-parent-count").text(taskNum).show();
            } else {
                $("#task-parent-count").hide();
            }
        }
    });
};
