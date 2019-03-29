<?php

namespace app\parent\model;

use think\Db;
use think\Model;

class BaseModel extends Model
{
    protected static $school_id;
    protected $connection = '';
    public function __construct($data = [])
    {
        self::$school_id = session('parent_school_id');
        $this->connection = session('parent_db-config_' . self::$school_id);
        parent::__construct($data);
    }
}
