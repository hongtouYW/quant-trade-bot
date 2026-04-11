<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectServers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectServersController extends Controller
{
    public function __construct()
    {
        $this->init(ProjectServers::class);
        parent::__construct(0);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function form(Request $request)
    {
        $user = Auth::user();
        $project = $user->projects->first();
        if(!$project){
            return back()->withErrors([
                'msg' => '用户没项目，无法进入服务器设置',
            ]);
        }
        $server = $project->servers->first();
        $content = view('form', [
                'extra' => '',
            'edit' => -1,
            'title' => $this->title,
            'crudRoutePart' => $this->crudRoutePart,
            'buttons' => $this->buttons,
            'columns' => [
                'reminder' => [
                    'name' => '提醒',
                    'type' => 'html',
                    'value' => '请确保项目服务器已经对明顺服务器进行免密，并且填写的目录已经创建'
                ],
                'ip' => [
                    'name' => 'ip',
                    'type' => 'text',
                    'value' => $server->ip ?? '',
                    'required' => 1
                ],
                'port' => [
                    'name' => 'port',
                    'type' => 'number',
                    'value' => $server->port ?? ''
                ],
                'reminder2' => [
                    'name' => '提醒',
                    'type' => 'html',
                    'value' => '<span style="color:red;">请确保路径和绝对路径不要带有敏感字，如“av”，“成人“，”18“ 等</span>'
                ],
                'path' => [
                    'name' => '路径',
                    'type' => 'text',
                    'value' => $server->path ?? '',
                    'required' => 1
                ],
                'absolute_path' => [
                    'name' => '绝对路径',
                    'type' => 'text',
                    'value' => $server->absolute_path ?? '',
                    'required' => 1
                ],
                'username' => [
                    'name' => '服务器用户',
                    'type' => 'text',
                    'value' => $server->username ?? '',
                    'required' => 1
                ],
                'token' => [
                    'name' => '接口token',
                    'type' => 'text',
                    'value' => $project->token ?? '',
                    'readonly' => 1
                ], 
            ]
        ]);

        return view('template', compact('content'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function store(Request $request)
    {
        try {
        $validate = $request->validate([
            'ip' => ['required'],
            'port' => [],
            'path' => ['required'],
            'absolute_path' => ['required'],
            'username' => ['required'],
        ], [
            'ip.required' => 'ip不能为空',
            'path.required' => '路径不能为空',
            'absolute_path.required' => '绝对路径不能为空',
            'username.required' => '用户不能为空',
        ]);
        $user = Auth::user();
        $project = $user->projects->first();
        ProjectServers::updateOrInsert(
            ['project_id'=>$project->id],
            $validate
        );
        return redirect()->route($this->crudRoutePart . '.form')->with('success', $this->title . '修改成功');
        } catch (\Exception $e) {
            return back()->withInput()->withErrors(['msg' => $e->getMessage()]);
        }
    }
}
