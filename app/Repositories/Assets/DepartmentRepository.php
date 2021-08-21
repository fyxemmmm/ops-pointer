<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/1/18
 * Time: 14:01
 */

namespace App\Repositories\Assets;

use App\Repositories\BaseRepository;
use App\Models\Assets\Department;

class DepartmentRepository extends BaseRepository
{

    public function __construct(Department $model)
    {
        $this->model = $model;
    }


}