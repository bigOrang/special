<?php
/**
 * Created by PhpStorm.
 * User: shiwenbin
 * Date: 2018/11/20
 * Time: 18:03
 */
namespace app\student\model;

use think\model\concern\SoftDelete;

class StudentParentsModel extends BaseModel
{
    protected $table = 't_sys_student_parents';
}