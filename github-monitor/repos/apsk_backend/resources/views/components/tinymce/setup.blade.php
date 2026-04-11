<script src="{{ asset('assets/tinymce/tinymce.min.js') }}"></script>
<script>
    function initTinyMCE(selector) {
        let themeMode = document.documentElement.getAttribute('data-theme') || 'light';
        tinymce.init({
            selector: selector,
            plugins: 'link image code',
            toolbar: 'undo redo | bold italic | alignleft aligncenter alignright | code',
            skin: themeMode === 'dark' ? 'oxide-dark' : 'oxide',
            content_css: themeMode === 'dark' ? 'dark' : 'default',
        });
    }
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof initTinyMCE === 'function') {
            initTinyMCE('.my-editor');
        }

        // Ensure TinyMCE updates textarea before submit
        document.querySelector('form').addEventListener('submit', function () {
            document.querySelectorAll('.my-editor').forEach(function (el) {
                const editor = tinymce.get(el.id);
                if (editor) {
                    editor.save(); // same as triggerSave but per editor
                }
            });
        });
    });
</script>