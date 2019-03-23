<?php
namespace app\admin\controller;

use app\admin\model\CategoryModel;
use app\admin\model\TopicDetailModel;
use app\admin\model\TopicModel;
use think\Db;
use think\Exception;
use think\facade\Log;
use think\Request;

class Topic extends Base
{
    public function index(Request $request)
    {
        $c_id = $request->param("id");
        $cateInfo = CategoryModel::where("id", $c_id)->find();
//        $data = $this->getTopic($c_id);
        $data = $this->getTrees(TopicModel::where("c_id", $c_id)->order(['sort'=>'asc','id'=>'asc'])->select()->toArray());
        $this->assign("data", $data);
        $this->assign("cateInfo", $cateInfo);
        return $this->fetch('./topic/index');
    }

    public function add(Request $request) {
        if ($request->isPost()) {
            $requestData = $this->validation($request->post(), 'Topic');
            try {
                $topicModel = new TopicModel();
                $topicModel->insert([
                    'title'     => $requestData['title'],
                    'c_id'      => $requestData['c_id'],
                    't_parent_id'   => 0,
                ]);
                return $this->responseToJson([],'添加成功');
            } catch (\Exception $e) {
                return $this->responseToJson([],'添加失败'.$e->getMessage() , 201);
            }
        }
        if ($request->has("c_id")) {
            $c_id = $request->param("c_id");
            $options = $this->getOptions(TopicModel::where("c_id", $c_id)->order('sort',"asc")->select()->toArray());
            $this->assign('c_id', $c_id);
            $this->assign('options', $options);
            return $this->fetch('./topic/add');
        } else {
            exit($this->alertInfo("相关参数未获取"));
        }

    }

    public function update(Request $request)
    {
        $topicModel = new TopicModel();
        if ($request->isPost()) {
            $requestData = $this->validation($request->post(), 'Topic');
            try {
                $topicModel->where("id", $requestData['id'])->update([
                    'title'     => $requestData['title'],
                    't_parent_id'   => 0,
                ]);
                return $this->responseToJson([],'编辑成功');
            } catch (\Exception $e) {
                return $this->responseToJson([],'编辑失败'.$e->getMessage() , 201);
            }
        }
        if ($request->has("id") && $request->has("c_id") ) {
            $id = $request->param("id");
            $c_id = $request->param("c_id");
            $data = $topicModel->where("id",$id)->find();
            $options = $this->getOptions(TopicModel::where("c_id", $c_id)->order('sort',"asc")->select()->toArray());
            $this->assign('c_id', $c_id);
            $this->assign('data', $data);
            $options = str_replace('<option value="'.$data->t_parent_id.'">',"<option selected value='".$data->t_parent_id."'>", $options);
            $this->assign('options', $options);
            return $this->fetch('./topic/edit');
        } else {
            return $this->responseToJson([],'相关参数未获取' , 201);
        }
    }

    public function delete(Request $request)
    {
        if ($request->has("ids") && !empty($request->param("ids"))) {
            $ids = $request->param("ids");
            try{
                TopicModel::destroy($ids);
                (new \app\admin\model\TopicDetailModel)->whereIn("t_id", $ids)->delete();
                return $this->responseToJson([],'删除成功' , 200);
            }catch (Exception $e) {
                return $this->responseToJson([],'删除失败'.$e->getMessage() , 201);
            }
        } else {
            return $this->responseToJson([],'相关参数未获取' , 201);
        }
    }

    public function getTopic($c_id)
    {
        $topicModel = new TopicModel();
        $data = $topicModel->where("c_id", $c_id)->where("t_parent_id",0)->field("id,t_parent_id,title,sort")->select()->toArray();
        foreach ($data as $key=>$value) {
            $this->getTopicTitle($data[$key]['child'], $value['id']);
        }
        return $data;
//        Log::error($result);
    }

    public function getTopicTitle(&$result=[],$t_id)
    {
        $topicModel = new TopicModel();
        $data = $topicModel->where("t_parent_id", $t_id)->field("id,t_parent_id,title,sort")->select()->toArray();
        if (!empty($data)) {
            foreach ($data as $item) {
                $result[$item['id']]['id'] = $item['id'];
                $result[$item['id']]['t_parent_id'] = $item['t_parent_id'];
                $result[$item['id']]['title'] = $item['title'];
                $result[$item['id']]['sort'] = $item['sort'];
                $this->getTopicTitle($result[$item['id']]['child'], $item['id']);
            }
        }
    }

    public function validation($data, $name)
    {
        $valid = $this->validate($data, $name);
        if (true !== $valid) {
            exit($this->responseToJson([], $valid, 201, false));
        }
        return $data;
    }

    private function getTrees($data = [], $pid = 0, $level = 1)
    {
        $html = '';
        foreach ($data as $item) {
            if ($item['t_parent_id'] == $pid) {
                $html .= '<li class="dd-item dd3-item"  data-type="child" data-parent="'.$pid.'" data-level="'.$level.'" data-id="' . $item['id'] . '"><div class="dd-handle dd3-handle"></div><div class="dd3-content get-top-detail" data-id="' . $item['id'] .'">';

                $html .= '<span class="pull-right">
                                <a href="javascript:;" onclick="addOrEdit(\'编辑\', '. $item['id'] .')"><i class="fa fa-pencil"></i></a>&ensp;
                                <a href="javascript:;" onclick="jumpDelete('. $item['id'] .')"><i class="fa fa-trash"></i></a>
                            </span><span class="label label-info"><i class="fa fa-pencil-square-o"></i></span>'. $item['title'] .'</div>';

                $child = $this->getTrees($data, $item['id'], $level+1);
                if ($child != '') {
                    $html  = str_replace("child","root", $html);
                    $html  = str_replace("pencil-square-o","bookmark", $html);
                    $html .= '<ol class="dd-list" >' . $child . '</ol>';
                }

                $html .= '</li>';
            }
        }
        return $html;
    }

    private function getOptions($data = [], $pid = 0, $level = 1)
    {
        $html = '';
        $sp   = '';
        for($i=0; $i<$level; $i++)
            $sp.= '&emsp;&ensp;';
        foreach ($data as $item) {
            if ($item['t_parent_id'] == $pid) {
                $html .= '<option value="' . $item['id'] . '">'.$sp.$item['title'].'</option>';
                $child = $this->getOptions($data, $item['id'], $level+1);
                if ($child != '') {
//                    $html  = str_replace("&ensp;","&emsp;&emsp;&emsp;", $html);
                    $html .= $child;
                }
            }
        }
        return $html;
    }

    public function sort(Request $request)
    {
        $ids = $request->param('ids');
        $parseNode = $this->parseNode($ids);
        $res = $this->updateBatch('t_special_topic', $parseNode);
        if (!$res) {
            return $this->responseToJson([], '保存失败', 201);
        }
        return $this->responseToJson([], '保存成功');
    }




    public function zend($array)
    {
        $rule_1 = '/^\d+(?!(\.|．)\d*)(?!\s)/';                 //匹配一级
//        $rule_2 = '/^\d+((\.|．)\d+)+\s+/';       //匹配多级
        $rule_2 = '/^\d+(\.|．)\d+\s+/';       //匹配二级
        $rule_3 = '/^\d+(\.|．)\d+(\.|．)\d+\s+/';       //匹配三级
        $rule_4 = '/(^\s*(\d\s+)|(（\d）))/';       //匹配选项
        $content = [];
        $subscript = [];
        foreach($array as $key=>$value) {
            $fixed = '';
            preg_match($rule_1,$value,$content_1);    //一级分类
            if (!empty($content_1)) {
                $content[$content_1[0]] = ['name'=>str_replace($content_1[0], '', $value), 'child' =>[]];
            } elseif (preg_match($rule_2,$value,$content_2) && !empty($content_2)) {
                $fixed = trim($content_2[0]);
                $cate_1 = explode(".", str_replace('．', '.', $fixed));         //分割标题前缀1.1
                $str_1  = str_replace($content_2[0], '', $value);
                $content[intval($cate_1[0])]['child'][intval($cate_1[1])] = ['name'=>$str_1,'child'=>[]];
            } elseif (preg_match($rule_3,$value,$content_3) && !empty($content_3)) {
                $fixed = trim($content_3[0]);
                $cate_2 = explode(".", str_replace('．', '.', $fixed));       //分割标题前缀1.1.1
                $subscript = $cate_2;
                $str_2  = str_replace($content_3[0], '', $value);
                $content[intval($cate_2[0])]['child'][intval($cate_2[1])]['child'][intval($cate_2[2])] = ['name'=>$str_2,'child'=>[]];
            } elseif (preg_match($rule_4,$value,$content_4) && !empty($content_4)) {
                $rule_5 = '/(^\s*(（\d）))/';
                $fixed  = str_replace(' ', '', $value);
                preg_match($rule_5,$value,$content_5);
                if (!empty($content_5)) {
                    $last_arr = end($content[intval($subscript[0])]['child'][intval($subscript[1])]['child'][intval($subscript[2])]['child'])['name'];
                    $fixed = $last_arr.$fixed;
                    array_pop($content[intval($subscript[0])]['child'][intval($subscript[1])]['child'][intval($subscript[2])]['child']);
                }
                $content[intval($subscript[0])]['child'][intval($subscript[1])]['child'][intval($subscript[2])]['child'][] = ['name'=>$fixed];
            } else{
                continue;
            }
        }
        return $content;
    }
}
