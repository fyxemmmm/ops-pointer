<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/1/18
 * Time: 14:22
 */

namespace App\Support;

use App\Models\Code;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use App;

class Response
{
    /**
     * 错误号
     * @var int
     */
    public $code = 0;

    /**
     * 消息
     * @var
     */
    public $msg;

    /**
     * 异常
     * @var
     */
    public $exception;

    /**
     * 数据
     * @var
     */
    public $data;

    /**
     * 元信息
     * @var
     */
    public $meta = [];

    /**
     * detail
     * @var
     */
    public $detail;

    public $time;

    public $transformerName;

    public $headerCode;


    /**
     * 输出内容
     * @var
     */
    private $_output;

    function __construct(){
        $this->transformer = new Transformer();
        $this->menuRepository = App::make("App\Repositories\Auth\MenuRepository");
        $this->userRepository = App::make("App\Repositories\Auth\UserRepository");
    }

    public function setMenu($menu = null) {
        if(is_null($menu)) {
            $menu = $this->menuRepository->getMenuList();
        }
        $this->addMeta([ "menu" => $menu]);
    }

    public function setUser($user = null) {
        if(is_null($user)) {
            $user = $this->userRepository->getBasic();
        }
        $this->addMeta([ "user" => $user]);
    }

    public function setData($data, $fields) {
        if(!is_null($data)) {
            if(is_object($data)) {
                if(!empty($fields)) {
                    $this->transformer->fieldsets($fields);
                }
                if ($data instanceof LengthAwarePaginator || $data instanceof Collection) {
                    $this->data = $this->transformer->collection($data, $this->transformerName);
                } else {
                    $this->data = $this->transformer->item($data, $this->transformerName);
                }
            }
            else {
                $this->data = $data;
            }
        }
    }

    public function setException($exception) {
        $this->exception = $exception;
    }

    public function setTransformer($transformer) {
        $this->transformerName = $transformer;
    }

    public function addMeta(array $value) {
        $this->meta = array_merge($this->meta, $value);
    }

    // 拼接 json 提示信息
    protected function prepare() {
        $output = [];
        list($code, $msg) = Code::getCode();
        $output['code'] = $code;
        $output['msg'] = $msg;
        $output['time'] = date("Y-m-d H:i:s");

        if(!is_null($this->data)) {
            $output['data'] = $this->data;
            if(!isset($output['data']['meta'])) {
                $output['data']['meta'] = [];
            }
            $output['data']['meta'] = array_merge($output['data']['meta'], $this->meta);
        }
        else if (!empty($this->meta)){
            $output['data']['meta'] = $this->meta;
        }
        if(empty($output['data']['meta'])) {
            unset($output['data']['meta']);
        }

        // 输出调用数据库中的 sql 语句
        if(config('app.debug') && GValue::$debug == 1) {
            $detail = Code::getDetail();
            if(!is_null($detail)) {
                $output['detail'] = $detail;
            }

            if($this->exception instanceof \Exception) {
                $output['exception'] = get_class($this->exception).":".$this->exception->getMessage();
            }

            $connections = array_keys(\Config::get("database.connections",[]));
            $output['query'] = [];
            foreach($connections as $conn) {
                $logs = \DB::connection($conn)->getQueryLog();
                if(!empty($logs)) {
                    $output['query'][$conn] = $logs;
                }
            }
        }

        $this->_output = $output;
    }

    public function send($data = null, $fields = null) {
        if(!is_null($data) && !is_bool($data))
        {
            $this->setData($data, $fields);
        }
        $this->prepare();
        $headerCode = $this->headerCode ?: 200;

        return response()->json($this->_output, $headerCode, ['Content-type' => 'application/json; charset=utf-8'],
            JSON_UNESCAPED_UNICODE);
    }


}