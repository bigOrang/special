<?php
/**
 * Created by PhpStorm.
 * User: id_orange
 * Date: 2019/2/15
 * Time: 13:43
 */

namespace app\parent\model;


use think\Db;
use think\facade\Log;
use think\Model;
use think\model\concern\SoftDelete;

class TopicDetailModel extends BaseModel
{
//    use SoftDelete;
    protected $table = "t_special_topic_detail";
//    protected $dateFormat = 'Y-m-d H:i:s';
//    protected $autoWriteTimestamp = 'datetime';
//    protected $deleteTime = 'deleted_at';

}