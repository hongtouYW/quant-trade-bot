<?php

namespace App\Trait;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;

trait Import
{
    public function import(Request $request, $defaults = ['status' => 1], $unique = [])
    {
        try {
            if(empty($unique)){
                $unique = ['field' => 'name', 'table' => (new $this->model)->getTable()];
            }
            $processing_data = $this->csvImportProcessing(
                $request,
                $this->crudRoutePart,
                $this->title,
                $this->model::IMPORT,
                $defaults,
                $unique
            );
            foreach($processing_data as $value){
                $this->model::create($value);
            }
            return redirect()->route($this->crudRoutePart . '.index')->with('success', $this->title . '导入成功');
        } catch (\Exception $e) {
            return redirect()->route($this->crudRoutePart . '.index')->withErrors([
                'msg' => $e->getMessage(),
            ]);
        }
    }

    public function importExample()
    {
        return $this->arrayToCsv([
            $this->model::IMPORT,
            [$this->title . '1'],
            [$this->title . '2'],
        ], $this->title);
    }

    public static function arrayToCsv($array, $title = 'example')
    {
        $output = fopen('php://temp', 'w');

        foreach ($array as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csvData = stream_get_contents($output);
        fclose($output);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $title . '.csv"',
        ];

        return Response::make($csvData, 200, $headers);
    }

    public static function csvImportProcessing($request, $crudRoutePart, $title, $original, $defaults = [], $unique = [])
    {
        if ($request->hasFile('file')) {
            $path = $request->file('file')->getRealPath();
            $data = array_map('str_getcsv', file($path));

            $difference = [];
            $process_data = [];
            $header = [];
            foreach ($data as $key => $row) {
                if ($key == 0) {
                    $difference = array_diff($original, $row);
                    $difference2 = array_diff($row, $original);
                    if (!empty($difference) || !empty($difference2)) {
                        throw new \Exception($title . '导入失败,.csv格式错误。');
                    }
                    $header = $row;
                } else {
                    $temp = [];
                    foreach ($row as $key2 => $column) {
                        $temp[$header[$key2]] = $column;
                    }
                    foreach ($defaults as $default_key => $default_value) {
                        $temp[$default_key] = $default_value;
                    }
                    $process_data[] = $temp;
                }
            }
            if (!empty($unique)) {
                if (isset($unique['field']) && isset($unique['table'])) {
                    $validator = Validator::make($process_data, [
                        '*.' . $unique['field'] => ['required', Rule::unique($unique['table'], $unique['field'])],
                    ]);
                    if ($validator->fails()) {
                        foreach ($process_data as $index => $data) {
                            $fieldName = "{$index}." . $unique['field'];
                            $errors = $validator->errors();

                            $temp = [];
                            if ($errors->has($fieldName)) {
                                $duplicateName = $data[$unique['field']];
                                $error = "The value '{$duplicateName}' is already taken.";
                            }
                        }
                        throw new \Exception($error);
                    }
                }
            }
        }
        return $process_data;
    }
}
