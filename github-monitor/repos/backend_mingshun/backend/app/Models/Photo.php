<?php

namespace App\Models;

use App\Http\Helper;
use App\Trait\Log;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Photo extends Model
{
    use Log;
    use HasFactory;
    protected $fillable = [
        'title',
        'path',
        'uploader',
        'server_id',
        'project_id',
        'photo_project_rule_id',
        'status',
        'save_path'
    ];

    public const TITLE = '图片';
    public const CRUD_ROUTE_PART = 'photos';

    public const STATUS = [
        '1' => '增加水印中',      
        '2' => '增加水印失败',      
        '3' => '已完成',      
    ];

    public function servers()
    {
        return $this->belongsTo(Server::class, 'server_id');
    }

    public function uploaderUser()
    {
        return $this->belongsTo(User::class, 'uploader');
    }

    public function photoRule()
    {
        return $this->belongsTo(PhotoProjectRule::class, 'photo_project_rule_id');
    }

    public function projects()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }


    public static function addWatermarkImage($inputFile, $projectPhotoRuleId, $output, $projectId)
    {
        $projectPhotoRule = PhotoProjectRule::find($projectPhotoRuleId);

        $text =$projectPhotoRule->font_text1;
        if($projectPhotoRule->font_text2 ?? ''){
            $text .= "\n" .$projectPhotoRule->font_text2;
        }
        if($projectPhotoRule->font_text3 ?? ''){
            $text .= "\n" .$projectPhotoRule->font_text3;
        }

        if($projectPhotoRule->font_enable){
            $font = array(
                'position' => (int)$projectPhotoRule->font_position,
                'borderSpace' => (int)$projectPhotoRule->font_borderSpace,
                'fontName' => $projectPhotoRule->font_fontName,
                'fontSize' => (int)$projectPhotoRule->font_fontSize,
                'fontColor' => $projectPhotoRule->font_fontColor,
                'lineSpace' => (int)$projectPhotoRule->font_lineSpace,
                'text' => $text,
            );
        }else{
            $font = null;
        }

        if($projectPhotoRule->icon_enable){
            $logo = array(
                'file' => asset($projectPhotoRule->icon_path),
                'position' => (int)$projectPhotoRule->icon_position,
                'padding' => (int)$projectPhotoRule->icon_padding,
                'scale' => (int)$projectPhotoRule->icon_scale,
            );
        }else{
            $logo = null;
        }
       
        $project = Project::findOrFail($projectId);
        $project_server = $project->servers->first();
        if(!$project_server){
            return array(false,'同步服务器失败');
        }

        $data =  array(
            'inputFile' => $inputFile,
            'outputFile' => $output,
            'font' => $font,
            'logo' => $logo,
            'receiver' => array(
                'user' => $project_server->username,
                'host' => $project_server->ip,
                'port' => (int)$project_server->port,
                'path' => $project_server->absolute_path,
            )
        );

        $response = Helper::sendResourceRequest(
            'http://23.224.221.82:5567/build',
            json_encode($data),
            array('Content-Type: application/json'),
            'Add Watermark Image'
        );
        $response = json_decode($response);
        $errorMessage = '上传失败';
        if (($response->code ?? 0) == 200) {
            return array(true,$output);
        }else{
            if($response->msg ?? ''){
                if (strpos($response->msg, 'only accept file check') !== false) {
                    $errorMessage = '没有extension';
                } elseif (strpos($response->msg, 'file not allow to check') !== false) {
                    $errorMessage = '不在共享文件夹';
                } elseif (strpos($response->msg, 'file not found') !== false) {
                    $errorMessage = '不存在/地址不正确';
                } elseif (strpos($response->msg, 'GetVideoDetail') !== false) {
                    $errorMessage = '有问题';
                } elseif (strpos($response->msg, 'require body data') !== false) {
                    $errorMessage = '没有API body';
                } elseif (strpos($response->msg, 'video too small') !== false) {
                    $errorMessage = '太小';
                } elseif (strpos($response->msg, 'filename and image type not suite') !== false) {
                    $errorMessage = '图片格式与文件扩展名不匹配';
                } elseif (strpos($response->msg, 'this is not a image file') !== false) {
                    $errorMessage = '该文件不是一个图片文件';
                } elseif (strpos($response->msg, 'broken file') !== false) {
                    $errorMessage = '损坏的文件';
                } elseif (strpos($response->msg, 'cover size cannot bigger than 5MB') !== false) {
                    $errorMessage = '图片大小不能多过5MB';
                } elseif (strpos($response->msg, 'image file not support') !== false) {
                    $errorMessage = '不支持此图片格式';
                } 
            }
        }
        return array(false,$errorMessage);
    }


    public function scopeSearch($query, Request $request)
    {
        if ($request->id !== null) {
            $query->where(function ($q) use ($request) {
                $q->where('id', $request->id);
            });
        }

        if ($request->status !== null) {
            $query->where(function ($q) use ($request) {
                $q->where('id', $request->status);
            });
        }

        return $query;
    }
}
