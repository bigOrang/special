<?php
namespace app\home\controller;

use app\admin\model\AdvisoryModel;
use app\admin\model\CategoryModel;
use think\Db;
use think\Exception;
use think\facade\Log;
use think\Request;

class Index extends Base
{
    public function index(Request $request)
    {
        if ($request->isPost()) {
            $limit = $request->param('limit', 10);
            $searchData = $request->param();
        }
        $category = CategoryModel::select();
        $category = json_decode(json_encode($category),true);
        $this->assign('category', $category);
        return $this->fetch('./index/index');
    }

    public function getAdvisoryData(Request $request)
    {
        if ($request->isPost()) {
            $requestData   = $request->param();
            $advisoryModel = new AdvisoryModel();
            $limit = $request->param("limit", 10);
            $page = $request->param("page", 1);
            $limit = $limit * $page;
            $query = $advisoryModel->alias("a")
                ->leftJoin("t_advisory_category b","a.c_id=b.id")
                ->field("a.*,b.name");
            if (isset($requestData['category'])) {
                $query->whereIn("a.c_id", $requestData['category']);
            }
            if ($requestData['is_show'] == 1) {
                $query->where("a.user_id", session("user_id"));
            } else {
                $query->where("a.is_show", 0);
            }
            $data = $query->page(1,$limit)->select();
            $is_empty = empty(json_decode(json_encode($data),true)) ? 1 : 2;
            return $this->responseToJson(['data'=>$data,'is_empty'=>$is_empty],'添加成功');
        } else {
            return $this->responseToJson([],'不被允许的获取方式',201);
        }
    }

    public function add(Request $request) {
        if ($request->isPost()) {
            $requestData = $this->validation($request->post(), 'Advisory');
            try {
                $advisoryModel = new AdvisoryModel();
                $insert = $advisoryModel->insert([
                    'title'     => $requestData['title'],
                    'c_id'      => $requestData['c_id'],
                    'user_id'   => session("user_id"),
                    'content'   => isset($requestData['content']) ? trim($requestData['content']) : '',
                    'is_show'   => $requestData['is_show'] == 'on' ? 0 : 1
                ]);
                return $this->responseToJson([],'添加成功');
            } catch (\Exception $e) {
                return $this->responseToJson([],'添加失败' , 201);
            }
        }
    }

    public function detail(Request $request)
    {
        $id = $request->param("id", 0);
        if ($id == 0) {
            return $this->responseToJson([],'参数获取失败' , 201);
        } else {
            $advisoryModel = new AdvisoryModel();
            try {
                $detail = $advisoryModel->alias("a")->field("a.*,b.name")
                    ->leftJoin("t_advisory_category b", "a.c_id=b.id")
                    ->where("a.id", $id)->find();
                return $this->responseToJson($detail,'参数获取成功');
            } catch (Exception $e) {
                return $this->responseToJson([],'参数获取失败', 201);
            }
        }
    }

    public function validation($data, $name)
    {
        $valid = $this->validate($data, $name);
        if (true !== $valid) {
            exit($this->responseToJson([], $valid, 201, false));
        }
        if (empty(trim($data['title']))) {
            exit($this->responseToJson([], '咨询标题不能为空', 201, false));
        }
        return $data;
    }
}
