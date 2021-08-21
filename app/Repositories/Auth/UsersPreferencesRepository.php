<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/1/18
 * Time: 14:01
 */

namespace App\Repositories\Auth;

use App\Repositories\BaseRepository;
use App\Models\Auth\UsersPreferences;
use Auth;

class UsersPreferencesRepository extends BaseRepository
{

    public $model;
    public $user;

    public function __construct(UsersPreferences $model)
    {
        $this->model = $model;
        $this->user = Auth::user();
    }

    /**
     * 取显示字段配置
     * @param $categoryId
     * @return null
     */
    public function getPreferences($key)
    {
        $uid = $this->user->id;
        $preferences = $this->model->where(["uid" => $uid])->first();
        if (!empty($preferences)) {
            $preference = $preferences->$key;
            $data = json_decode($preference, true);
            return $data;
        }
        return false;
    }

    /**
     * @param $categoryId
     * @param $fields
     */
    public function setPreferences($key, $value)
    {
        $uid = $this->user->id;
        UsersPreferences::firstOrCreate(["uid" => $uid])->first();
        $this->model->where(["uid" => $uid])->update([$key => json_encode($value)]);
    }




}