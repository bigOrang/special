<?php
namespace app\admin\controller;

use app\admin\model\CategoryModel;
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
        $data = $this->getTrees(TopicModel::where("c_id", $c_id)->order('sort',"asc")->select()->toArray());
        $this->assign("data", $data);
        $this->assign("cateInfo", $cateInfo);
        return $this->fetch('./topic/index');
    }

    public function add(Request $request) {
        if ($request->isPost()) {
            $requestData = $this->validation($request->post(), 'Advisory');
            try {
                $advisoryModel = new AdvisoryModel();
                $advisoryModel->insert([
                    'title'     => $requestData['title'],
                    'c_id'      => $requestData['c_id'],
                    'user_id'   => session('user_id'),
                    'content'   => isset($requestData['content']) ? trim($requestData['content']) : '',
                    'is_show'   => $requestData['is_show'],
                ]);
                return $this->responseToJson([],'添加成功');
            } catch (\Exception $e) {
                return $this->responseToJson([],'添加失败'.$e->getMessage() , 201);
            }
        }
        $category = CategoryModel::select();
        $category = json_decode(json_encode($category),true);
        $this->assign('category', $category);
        return $this->fetch('./advisory/add');
    }

    public function update(Request $request)
    {
        if ($request->isPost()) {
            $requestData = $this->validation($request->post(), 'Advisory');
            try {
                $advisoryModel = new AdvisoryModel();
                $advisoryModel->where("id", $requestData['id'])->update([
                    'title'=>$requestData['title'],
                    'c_id'      => $requestData['c_id'],
                    'content'=>$requestData['content'],
                    'is_show'   => $requestData['is_show']
                ]);
                return $this->responseToJson([],'编辑成功');
            } catch (\Exception $e) {
                return $this->responseToJson([],'编辑失败'.$e->getMessage() , 201);
            }
        }
        if ($request->has("id") && !empty($request->param("id"))) {
            $id = $request->param("id");
            $data = AdvisoryModel::where("id", $id)->find();
            $data = json_decode(json_encode($data),true);
            $category = CategoryModel::select();
            $category = json_decode(json_encode($category),true);
            $this->assign('category', $category);
            $this->assign("data", $data);
            return $this->fetch('./advisory/edit');
        } else {
            return $this->responseToJson([],'相关参数未获取' , 201);
        }
    }

    public function delete(Request $request)
    {
        if ($request->has("ids") && !empty($request->param("ids"))) {
            $ids = $request->param("ids");
            try{
                AdvisoryModel::destroy(function($query) use ($ids) {
                    $query->whereIn("id",$ids);
                });
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

    private function getTrees($data = [], $pid = 0, $level = 0)
    {
        $html = '';
        foreach ($data as $item) {
            if ($item['t_parent_id'] == $pid) {
                $html .= '<li class="dd-item" data-id="' . $item['id'] . '"><div class="dd-handle">';

                $html .= '<span class="pull-right">
                                <a href="javascript:;" onclick="addOrEdit(\'编辑\', '. $item['id'] .')"><i class="fa fa-pencil"></i></a>&ensp;
                                <a href="javascript:;" onclick="jumpDelete('. $item['id'] .')"><i class="fa fa-trash"></i></a>
                            </span><span class="label label-info"><i class="fa fa-cog"></i></span>'. $item['title'] .'</div>';

                $child = $this->getTrees($data, $item['id'], $level+1);
                if ($child != '') {
                    $html .= '<ol class="dd-list">' . $child . '</ol>';
                }

                $html .= '</li>';
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
