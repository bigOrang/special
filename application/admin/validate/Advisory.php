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
class Advisory extends Validate
{
    // 定义验证规则
    protected $rule = [
        "title|试题标题"      => "require|max:20|min:2|unique:SpecialModel,title",
        "type|是否仅自己查看"       => "require",
        "is_show|是否仅自己查看"       => "require",
    ];

    protected $message  =   [
        'c_id.require' => '试题区不能为空',
        'title.require' => '试题标题不能为空',
        'title.max' => '试题标题不能超过20个字符',
        'title.min' => '试题标题不能少于2个字符',
        'title.unique' => '试题标题不能重复',
        'is_show.require'  => '是否仅自己查看不能为空',
    ];
}
