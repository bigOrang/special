<?php
namespace app\admin\controller;

use app\admin\model\CategoryModel;
use app\admin\model\TopicDetailModel;
use app\admin\model\TopicModel;
use think\Db;
use think\Exception;
use think\facade\Log;
use think\Request;

class TopicDetail extends Base
{
    public function index(Request $request)
    {
        if ($request->isPost() && $request->has("id")) {
            $topicDetailModel = new TopicDetailModel();
            $t_id = $request->param("id");
            $data = $topicDetailModel->where("t_id", $t_id)->field("content as text, id, id as href")->select()->toArray();
            Log::error($data);
            return $this->responseToJson(json_encode($data, 320), 'success');
        }
        return $this->responseToJson([], '错误的访问方式','301');
    }

    public function add(Request $request) {
        if ($request->isPost() && $request->has("t_id")) {
            $requestData = $this->validation($request->post(), 'TopicDetail');
            try {
                $topicDetailModel = new TopicDetailModel();
                $topicDetailModel->insert([
                    'content'     => $requestData['content'],
                    't_id'      => $requestData['t_id'],
                ]);
                return $this->responseToJson([],'添加成功');
            } catch (\Exception $e) {
                return $this->responseToJson([],'添加失败'.$e->getMessage() , 201);
            }
        }
        return $this->responseToJson([], '错误的访问方式','301');
    }

    public function update(Request $request)
    {
        if ($request->isPost() && $request->has("id")) {
            $topicDetailModel = new TopicDetailModel();
            $requestData = $this->validation($request->post(), 'TopicDetail');
            try {
                $topicDetailModel->where("id", $requestData['id'])->update([
                    'content'     => $requestData['content'],
                ]);
                return $this->responseToJson([],'编辑成功');
            } catch (\Exception $e) {
                return $this->responseToJson([],'编辑失败'.$e->getMessage() , 201);
            }
        }
        return $this->responseToJson([], '错误的访问方式','301');
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

    public function validation($data, $name)
    {
        $valid = $this->validate($data, $name);
        if (true !== $valid) {
            exit($this->responseToJson([], $valid, 201, false));
        }
        return $data;
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
