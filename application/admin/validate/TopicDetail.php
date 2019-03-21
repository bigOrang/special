<?php
// +----------------------------------------------------------------------
// | 海豚PHP框架 [ DolphinPHP ]
// +----------------------------------------------------------------------
// | 版权所有 2016~2017 河源市卓锐科技有限公司 [ http://www.zrthink.com ]
// +----------------------------------------------------------------------
// | 官方网站: http://dolphinphp.com
// +----------------------------------------------------------------------
// | 开源协议 ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------

namespace app\admin\validate;

use think\Validate;

/**
 * 客服验证器
 * @package app\cms\validate
 * @author 蔡伟明 <314013107@qq.com>
 */
class TopicDetail extends Validate
{
    // 定义验证规则
    protected $rule = [
        "content|选项内容"      => "require|max:100|min:2|unique:TopicDetailModel,content",
    ];

    protected $message  =   [
        'content.require' => '选项内容不能为空',
        'content.max' => '选项内容不能超过100个字符',
        'content.min' => '选项内容不能少于2个字符',
        'content.unique' => '选项内容不能重复',
    ];
}
