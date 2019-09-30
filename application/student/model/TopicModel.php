<?php
/**
 * Created by PhpStorm.
 * User: id_orange
 * Date: 2019/2/15
 * Time: 13:43
 */

namespace app\student\model;


use think\Model;
use think\model\concern\SoftDelete;

class TopicModel extends BaseModel
{
//    use SoftDelete;
    protected $table = "t_special_topic";
//    protected $dateFormat = 'Y-m-d H:i:s';
//    protected $autoWriteTimestamp = 'datetime';
//    protected $deleteTime = 'deleted_at';

}