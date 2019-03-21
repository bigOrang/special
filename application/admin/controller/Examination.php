<?php
namespace app\admin\controller;

use app\admin\model\AdvisoryModel;
use app\admin\model\CategoryModel;
use app\admin\model\SpecialModel;
use think\Db;
use think\Exception;
use think\facade\Log;
use think\Request;

class Examination extends Base
{
    public function index(Request $request)
    {
        $specialModel = new SpecialModel();
        if ($request->isPost()) {
            try{
                $limit = $request->param('limit', 10);
                $searchData = $request->param();
                $data = $specialModel->alias("a")->where(function ($query) use ($searchData) {
                    //模糊搜索
                    if (isset($searchData['search']) && !empty($searchData['search'])) {
                        $query->where('a.title', 'like', '%' . $searchData['search'] . '%');
                    }
                    })->paginate($limit);
                $data = json_decode(json_encode($data),true);
            } catch (Exception $exception) {
                Log::error('获取数据错误：'. $exception->getMessage());
                $data = ['total' => 0, 'rows' => []];
            }
            return [
                'total' => $data['total'],
                'rows' => $data['data']
            ];
        }
        return $this->fetch('./examination/index');
    }

    public function add(Request $request) {
        if ($request->isPost()) {
            $requestData = $this->validation($request->post(), 'Special');
            try {
                $specialModel = new SpecialModel();
                $specialModel->insert([
                    'title'   => $requestData['title'],
                    'builder' => session('user_id'),
                    'status'  => $requestData['status'],
                    'type'    => $requestData['type'],
                ]);
                return $this->responseToJson([],'添加成功');
            } catch (\Exception $e) {
                return $this->responseToJson([],'添加失败'.$e->getMessage() , 201);
            }
        }
        return $this->fetch('./examination/add');
    }

    public function update(Request $request)
    {
        $specialModel = new SpecialModel();
        if ($request->isPost()) {
            $requestData = $this->validation($request->post(), 'Special');
            try {
                $specialModel->where("id", $requestData['id'])->update([
                    'title'=>$requestData['title'],
                    'status'  => $requestData['status'],
                    'type'    => $requestData['type'],
                    'last_builder' => session('user_id'),
                ]);
                return $this->responseToJson([],'编辑成功');
            } catch (\Exception $e) {
                return $this->responseToJson([],'编辑失败'.$e->getMessage() , 201);
            }
        }
        if ($request->has("id") && !empty($request->param("id"))) {
            $id = $request->param("id");
            $data = $specialModel->where("id", $id)->find();
            $data = json_decode(json_encode($data),true);
            $this->assign("info", $data);
            return $this->fetch('./examination/edit');
        } else {
            exit($this->alertInfo("相关参数未获取"));
        }
    }

    public function delete(Request $request)
    {
        if ($request->has("ids") && !empty($request->param("ids"))) {
            $ids = $request->param("ids");
            try{
                //删除试卷
                SpecialModel::destroy(function($query) use ($ids) {
                    $query->whereIn("id",$ids);
                });
                //todo 缺少分类，以及试题，答题情况
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
}
