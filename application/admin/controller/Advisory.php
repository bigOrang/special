<?php
namespace app\admin\controller;

use app\admin\model\AdvisoryModel;
use app\admin\model\CategoryModel;
use think\Db;
use think\Exception;
use think\facade\Log;
use think\Request;

class Advisory extends Base
{
    public function index(Request $request)
    {
        if ($request->isPost()) {
            try{
                $limit = $request->param('limit', 10);
                $searchData = $request->param();
                $advisory = new AdvisoryModel();
                $data = $advisory->alias("b")->where(function ($query) use ($searchData){
                    //模糊搜索
                    if (isset($searchData['search']) || !empty($searchData['search']))
                        $query->where('b.title', 'like','%'.$searchData['search'].'%');
                    //时间查询
                    if (isset($searchData['time']) || !empty($searchData['time']))
                        $query->where(function ($q) use ($searchData) {
                            if (!empty($searchData['time'][0]))
                                $q->where('b.created_at', '>=', $searchData['time'][0]);
                            if (!empty($searchData['time'][1]))
                                $q->where('b.created_at', '<=', $searchData['time'][1]);
                        });
                    //分类查询
                    if (isset($searchData['category']) || !empty($searchData['category']))
                        $query->where('b.c_id', $searchData['category']);
                })->leftJoin("t_advisory_category a","a.id=b.c_id")
                    ->field("a.name,b.*")->paginate($limit);
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
        $category = CategoryModel::select();
        $category = json_decode(json_encode($category),true);
        $this->assign('category', $category);
        return $this->fetch('./advisory/index');
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

    public function validation($data, $name)
    {
        $valid = $this->validate($data, $name);
        if (true !== $valid) {
            exit($this->responseToJson([], $valid, 201, false));
        }
        return $data;
    }
}
