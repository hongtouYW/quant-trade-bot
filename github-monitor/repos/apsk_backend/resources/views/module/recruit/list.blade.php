@extends('adminlte::page')
@php
    use Illuminate\Support\Facades\Auth;
@endphp

@section('title', __('module.recruit_management'))
@section('header-title', __('module.recruit_management'))
@section('header-description')
    {{ __('messages.welcome', ['name' => Auth::user()->user_name ?? 'User']) }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>{{ __('module.recruit_management') }}</h6>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <!-- Container for all trees -->
                <div id="org-chart"></div>
            </div>
        </div>
    </div>
</div>

<style>
#org-chart {
    width: 100%;
    min-height: 600px;
    overflow-x: auto;
}
.tree-container {
    width: max-content;
    min-height: 400px;
    margin-bottom: 50px;
}
.node {
    padding: 10px;
    border-radius: 8px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    text-align: center;
    min-width: 120px;
}
.node img {
    display: block;
    margin: 0 auto 5px auto;
    width: 50px;
    height: 50px;
    border-radius: 50%;
}
.node .invite {
    font-size: 12px;
    color: #555;
    margin-top: 2px;
}
</style>
@endsection

@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/treant-js/1.0/Treant.css" />
@endsection

@section('js')
<script src="https://cdnjs.cloudflare.com/ajax/libs/raphael/2.3.0/raphael.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/treant-js/1.0/Treant.min.js"></script>

<script>
$(function() {

    const MEMBER_ID_LABEL = @json(__('member.member_id'));
    const MEMBER_NAME_LABEL = @json(__('member.member_name'));
    const NO_DATA_FOUND = @json(__('member.no_data_found'));
    const INVITE_CODE_LABEL = @json(__('performance.invitecode'));

    function buildTreantNode(node) {
        let avatar = node.avatar ? node.avatar : '';
        let invite = node.invitecode ? node.invitecode : '';
        let memberId = node.member_id || NO_DATA_FOUND;
        let memberName = node.member_name || NO_DATA_FOUND;

        let html = `
            <div class="node">
                <img src="${avatar}">
                <div>${MEMBER_ID_LABEL}: ${memberId}</div>
                <div>${MEMBER_NAME_LABEL}: ${memberName}</div>
                <div class="invite">${INVITE_CODE_LABEL}: ${invite}</div>
            </div>
        `;

        return {
            innerHTML: html,
            children: (node.DownlinesRecursive || []).map(buildTreantNode)
        };
    }

    let treeData = {!! json_encode($treeData) !!};

    treeData.forEach((node, index) => {
        let containerId = 'tree-' + index;
        $('<div class="tree-container" id="'+containerId+'"></div>').appendTo('#org-chart');

        new Treant({
            chart: {
                container: '#' + containerId,
                rootOrientation: "WEST",   // horizontal
                nodeAlign: "LEFT",
                connectors: { type: "step" },
                animation: { nodeAnimation: "easeOutBounce", nodeSpeed: 500 },
                scrollbar: "native"
            },
            nodeStructure: buildTreantNode(node)
        });
    });
});
</script>
@endsection