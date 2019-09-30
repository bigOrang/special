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

namespace app\student\validate;

use think\Validate;

/**
 * 客服验证器
 * @package app\cms\validate
 * @author 蔡伟明 <314013107@qq.com>
 */
class AddStudent extends Validate
{
    // 定义验证规则
    protected $rule = [
        "name|新生姓名"      => "require|max:20",
        "parentName|家长姓名"      => "require|max:20",
//        "code|新生学号"      => "require|max:10|unique:StudentModel,code",
    ];

    protected $message  =   [
//        'code.require'  => '新生学号不能为空',
//        'code.max'      => '新生学号不能超过10个字符',
//        'code.unique'   => '新生学号不能重复',
        'name.require'  => '新生姓名不能为空',
        'name.max'      => '新生姓名不能超过20个字符',
        'parentName.require'  => '家长姓名不能为空',
        'parentName.max'      => '家长姓名不能超过20个字符',
    ];
}
