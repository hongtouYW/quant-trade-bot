<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public $buttons;
    public $title;
    public $crudRoutePart;
    public $model;

    public function __construct($backButton = 1)
    {
        if($backButton){
            $this->buttons = view('widget.backButton', [
                'title' => $this->title,
                'crudRoutePart' => $this->crudRoutePart,
            ]);
        }
    }

    public function init($model){
        $this->model = $model;
        $this->title = $model::TITLE;
        $this->crudRoutePart = $model::CRUD_ROUTE_PART;
    }

    public function select(Request $request)
    {
        return $this->baseSelect($request, $this->model::query());
    }

    public function baseSelect($request, $query)
    {
        $init = $request->get('init',0);
        $search = $request->get('q',0);
        $idIn = "(0)";
        $init = json_decode($init);
        if(gettype($init) == 'array'){
            if(count($init) > 0){
                $temp = implode(",",$init);
                $idIn = "(".$temp.")";
            }
        }else{
            if($init){
                $idIn = "(".$init.")";
            }
        }
        if(!$search){
            $search = '';
        }
        $model = $this->model;

        $results = $query->active()->selectRaw('id,'. $model::SELECT . ' as text')
            ->where(DB::raw('LOWER('.$model::SELECT.')'), 'LIKE', '%' . strtolower($search) . '%')
            ->orderByRaw("CASE WHEN id IN ".$idIn." THEN 0 ELSE 1 END")
            ->simplePaginate(10);
        
        $items = $results->items();
       
        if($request->page == 1){
            $decodedJsonString = htmlspecialchars_decode($request->get('pre'));
            $pre = json_decode($decodedJsonString);
            
            if(gettype($pre) == 'array'){
                foreach($pre as $prekey=>$prevalue){
                    array_unshift($items,[
                        'id' => $prevalue->id,
                        'text' => $prevalue->text,
                    ]);
                }
            }
        } 
        return response()->json([
            'results' => $items,
            'pagination' => [
                'more' => $results->hasMorePages()
            ]
        ], Response::HTTP_OK);
    }
}
