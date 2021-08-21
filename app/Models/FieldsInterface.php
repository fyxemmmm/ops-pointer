<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/4/8
 * Time: 20:51
 */

namespace App\Models;

interface FieldsInterface {

    public function getByName($where);

    public function getFieldById($id, $field);

}