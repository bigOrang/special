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
        $data = $this->getTrees(TopicModel::where("c_id", $c_id)->order(['sort'=>'asc','id'=>'desc'])->select()->toArray());
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
                    't_parent_id'   => $requestData['t_parent_id'],
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
                    't_parent_id'   => $requestData['t_parent_id'],
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
                $html .= '<li class="dd-item dd3-item"  data-type="child" data-parent="'.$pid.'" data-level="'.$level.'" data-id="' . $item['id'] . '"><div class="dd-handle dd3-handle"></div><div class="dd3-content" data-id="' . $item['id'] .'">';

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
}
