<?php
namespace App\Repositories\Assets;

use App\Repositories\BaseRepository;
use App\Models\Assets\MapRegion;

class MapRegionRepository extends BaseRepository
{

    public function __construct(MapRegion $model)
    {
        $this->model = $model;
    }


}