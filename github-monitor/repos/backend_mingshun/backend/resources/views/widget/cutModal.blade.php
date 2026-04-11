<div class="modal cut-modal" id="cut-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" id="close-cut-modal">
                    <span>×</span>
                </button>
            </div>
            <div class="modal-body">
                <form action="{{ route($crudRoutePart) }}"  class='changeStatusFormOnce' confirmWord = '确定切片？' method="POST">
                    @csrf
                    @include('widget.inputContainer', [
                        'key' => 'rule',
                        'name' => '规则',
                        'type' => $ruleValue,
                        'setting' => [
                            'containerKey' => 'rule',
                            'required' => 1,
                            'spacing' => 1
                        ],
                    ])
                     @include('widget.inputContainer', [
                        'key' => 'theme',
                        'name' => '主题',
                        'type' => $themeValue,
                        'setting' => [
                            'containerKey' => 'theme',
                            'spacing' => 1,
                            'multiple' => 1
                        ],
                    ])
                    <input id="chg-cut-modal-status" type="hidden" name="status" value={{ $value }}>
                    <input id="chg-cut-modal-id" type="hidden" name="id" value="">
                    <div class='row submit-button-container'>
                        <div class='col-12 submit-button'>
                            <button class="btn btn-sm btn-submit">
                                提交
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
