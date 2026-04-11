$(function() {
    $('#dateTimeRange').daterangepicker({
        applyClass : 'btn-sm btn-success',
        cancelClass : 'btn-sm btn-default',
        locale: {
            applyLabel: '确认',
            cancelLabel: '取消',
            fromLabel : '起始时间',
            toLabel : '结束时间',
            customRangeLabel : '自定义',
            firstDay : 1
        },
        ranges : {
            //'最近1小时': [moment().subtract('hours',1), moment()],
            '今日': [moment().startOf('day'), moment()],
            '昨日': [moment().subtract('days', 1).startOf('day'), moment().subtract('days', 1).endOf('day')],
            '最近7日': [moment().subtract('days', 6), moment()],
            '最近30日': [moment().subtract('days', 29), moment()],
            '本月': [moment().startOf("month"),moment().endOf("month")],
            '上个月': [moment().subtract(1,"month").startOf("month"),moment().subtract(1,"month").endOf("month")]
        },
        opens : 'right',	// 日期选择框的弹出位置
        separator : '至',
        showWeekNumbers : true,		// 是否显示第几周


        //timePicker: true,
        //timePickerIncrement : 10,	// 时间的增量，单位为分钟
        //timePicker12Hour : false,	// 是否使用12小时制来显示时间


        //maxDate : moment(),			// 最大时间
        format: 'YYYY-MM-DD HH:mm'

    }, function(start, end, label) { // 格式化日期显示框
        $('#label').val(label);
    })
        .next().on('click', function(){
        $(this).prev().focus();
    });
});