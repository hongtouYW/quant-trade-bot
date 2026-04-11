<?php

namespace App\Trait;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;

trait Export
{
    public $header;

    public function Export()
    {
        $header = $this->header;
        $values = $this->model::all();
        $data = [
            $header,
        ];
        foreach($values as $key => $value){
            $temp_value = [];
            foreach($header as $header_key=> $header_value){
                if($header_key=='#'){
                    $temp_value[] = $key + 1;
                }else{
                    $temp_value[] = $value->$header_key;
                }
            }
            $data[] = $temp_value;
        }
        $csvData = '';
        foreach ($data as $row) {
            $csvData .= implode(',', $row) . "\r\n";
        }

        return response($csvData, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="'.$this->title.'.csv"');
    }
}
