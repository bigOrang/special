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
class Topic extends Validate
{
    // 定义验证规则
    protected $rule = [
        "title|试题标题"      => "require|max:100|min:2|unique:TopicModel,title",
        "c_id|试题编号"       => "require",
        "t_parent_id|父级分类"       => "require",
    ];

    protected $message  =   [
        'c_id.require' => '试题编号不能为空',
        'title.require' => '试题标题不能为空',
        'title.max' => '试题标题不能超过100个字符',
        'title.min' => '试题标题不能少于2个字符',
        'title.unique' => '试题标题不能重复',
        't_parent_id.require'  => '父级分类不能为空',
    ];
}
