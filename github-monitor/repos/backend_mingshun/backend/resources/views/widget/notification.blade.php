<div class="notification-container" id="notification-container"></div>
<script>
    const notificationContainer = document.getElementById('notification-container');
    const NOTIFICATION_TYPES = {
        INFO: 'info',
        SUCCESS: 'success',
        WARNING: 'warning',
        DANGER: 'danger'
    }
    const NOTIFICATION_WORDS = {
        info: '咨询',
        success: '成功',
        warning: '警告',
        danger: '错误'
    }

    function addNotification(type, text) {
        // create the DIV and add the required classes
        const newNotification = document.createElement('div');
        newNotification.classList.add('notification', `notification-${type}`);
        const innerNotification = `<strong>${NOTIFICATION_WORDS[type]}:</strong> ${text}`;

        // insert the inner elements
        newNotification.innerHTML = innerNotification;

        // add the newNotification to the container
        notificationContainer.appendChild(newNotification);

        return newNotification;
    }

    function removeNotification(notification) {
        notification.classList.add('hide');

        // remove notification from the DOM after 0.5 seconds
        setTimeout(() => {
            notificationContainer.removeChild(notification);
        }, 500);
    }

    @if ($errors->any())
        @foreach ($errors->all() as $error)
            setTimeout(() => {
                const danger = addNotification(NOTIFICATION_TYPES.DANGER, '{{ $error }}');
                setTimeout(() => {
                    removeNotification(danger);
                }, 5000);
            }, 300);
        @endforeach
    @endif
    @if (session('success'))
        setTimeout(() => {
            const success = addNotification(NOTIFICATION_TYPES.SUCCESS, ' {{ session('success') }}');
            setTimeout(() => {
                removeNotification(success);
            }, 5000);
        }, 300);
    @endif
</script>
