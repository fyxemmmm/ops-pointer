<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/1/26
 * Time: 11:35
 */

namespace App\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use League\Fractal\TransformerAbstract;
use App\Transformers\BaseTransformer;
use Fractal;


class Transformer
{

    public function fieldsets($fields) {
        if(is_array($fields)) {
            $fields = join(",", $fields);
        }
        Fractal::fieldsets([config("fractal.collection_key") => $fields]);
    }

    public function collection($data, TransformerAbstract $transformer = null) {
        $transformer = $transformer ?: $this->fetchDefaultTransformer($data);
        return Fractal::collection($data, $transformer, config("fractal.collection_key"))->getArray();
    }

    public function item($data, TransformerAbstract $transformer = null) {
        $transformer = $transformer ?: $this->fetchDefaultTransformer($data);
        return Fractal::item($data, $transformer, config("fractal.collection_key"))->getArray();
    }

    protected function fetchDefaultTransformer($data) {
        if(($data instanceof LengthAwarePaginator || $data instanceof Collection) && $data->isEmpty()) {
            return new BaseTransformer();
        }
        $className = $this->getClassName($data);
        $transformer = $this->getDefaultTransformer($className);

        if(empty($transformer)) {
            $transformer = str_replace("Models","Transformers", $className)."Transformer";
            if(!class_exists($transformer)) {
                throw new \Exception("No transformer for $className");
            }
        }

        return new $transformer;
    }

    protected function getDefaultTransformer($className) {
        return config('fractal.transformers'. $className);
    }

    protected function getClassName($object) {
        if($object instanceof LengthAwarePaginator || $object instanceof Collection) {
            return get_class(array_first($object));
        }

        if(!is_string($object) && !is_object($object)) {
            throw new \Exception("No transformer of \"{$object}\" found.");
        }

        return get_class($object);
    }



}
