<?php
/**
 * Created by PhpStorm.
 * User: id_orange
 * Date: 2019/2/15
 * Time: 13:43
 */

namespace app\student\model;


use think\model\concern\SoftDelete;

class ClassModel extends BaseModel
{
    protected $table = "t_sys_class";

    public static function getClass() {
        $classes = self::column('name,grade_no,class_no', 'id');
        $newArr = [];
        foreach ($classes as $k => $class) {
            $newArr = $newArr + [
                    $k => $class['grade_no'] . '年级' . $class['class_no'] . '班'
                ];
        }
        return $newArr;
    }
}