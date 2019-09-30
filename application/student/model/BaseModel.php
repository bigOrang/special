<?php

namespace app\student\model;

use think\Db;
use think\Model;

class BaseModel extends Model
{
    protected static $school_id;
    protected $connection = '';
    public function __construct($data = [])
    {
        self::$school_id = session('add_student_school_id');
        $this->connection = session('add_student_db-config_' . self::$school_id);
        parent::__construct($data);
    }
}
