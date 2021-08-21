<?php
namespace App\Repositories\Assets;

use App\Repositories\BaseRepository;
use App\Models\Assets\Layout;
use App\Models\Code;

class LayoutRepository extends BaseRepository
{

    public function __construct(Layout $model)
    {
        $this->model = $model;
    }


}