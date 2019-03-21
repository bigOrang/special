<?php
namespace app\admin\controller;

use app\admin\model\AdvisoryModel;
use app\admin\model\CategoryModel;
use app\admin\model\SpecialModel;
use think\Db;
use think\Exception;
use think\facade\Log;
use think\Request;

class Category extends Base
{
    public function index(Request $request)
    {
        $specialModel = new SpecialModel();
        $categoryModel = new CategoryModel();
        if ($request->isPost()) {
            try{
                $limit = $request->param('limit', 10);
                $searchData = $request->param();
                Log::error($searchData);
                $data = $categoryModel->alias("a")->where(function ($query) use ($searchData) {
                    //模糊搜索
                    if (isset($searchData['search']) && !empty($searchData['search'])) {
                        $search = $searchData['search'];
                        $query->where(function ($q) use ($search) {
                            $q->where('a.title', 'like', '%' . $search . '%')
                                ->whereOr('b.title', 'like', '%' . $search . '%');
                        });
                    }
                    //过滤
                    if (isset($searchData['s_id']) && !empty($searchData['s_id']))
                        $query->where('a.s_id', $searchData['s_id']);
                    //分类查询
                    if (isset($searchData['category']) && !empty($searchData['category']))
                        $query->where('a.id', $searchData['category']);
                })
                    ->field("a.*,b.id AS parent_id,b.title AS parent_title,c.title as s_title")
                    ->leftJoin("t_special_category b","b.id=a.parent_id")
                    ->leftJoin("t_special c","a.s_id=c.id")
                    ->paginate($limit);
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
        $id = $request->param("id", 0);
        if (empty($id))
            exit($this->alertInfo("相关参数未获取"));
        $title = $specialModel->where("id", $id)->value("title");
        $category = $categoryModel->where("parent_id",0)->select()->toArray();
        $this->assign('s_id', $id);
        $this->assign('title', $title);
        $this->assign('category', $category);
        return $this->fetch('./category/index');
    }

    public function add(Request $request) {
        $categoryModel = new CategoryModel();
        if ($request->isPost()) {
            $requestData = $this->validation($request->post(), 'Category');
            Db::startTrans();
            try {
                $categoryModel->insert([
                    'title'       => $requestData['title'],
                    'parent_id'=> $requestData['parent_id'],
                    's_id'    => $requestData['s_id'],
                ]);
                // 提交事务
                Db::commit();
                return $this->responseToJson([],'添加成功');
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return $this->responseToJson([],'添加失败'.$e->getMessage() , 201);
            }
        }
        $sid = $request->param("s_id", 0);
        if (empty($sid))
            exit($this->alertInfo("相关参数未获取"));
        $category = $categoryModel->where("parent_id",0)
            ->where("s_id", $sid)->select()->toArray();
        $this->assign('s_id', $sid);
        $this->assign('category', $category);
        return $this->fetch('./category/add');
    }

    public function update(Request $request)
    {
        if ($request->isPost()) {
            $requestData = $this->validation($request->post(), 'Category');
            Db::startTrans();
            try {
                $categoryModel = new CategoryModel();
                $categoryModel->where("id", $requestData['id'])->update([
                    'title'       => $requestData['title'],
                    'parent_id'=> $requestData['parent_id'],
                ]);
                // 提交事务
                Db::commit();
                return $this->responseToJson([],'编辑成功');
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return $this->responseToJson([],'编辑失败'.$e->getMessage() , 201);
            }
        }
        if ($request->has("id") && $request->has("s_id")) {
            $id = $request->param("id");
            $sid = $request->param("s_id");
            $category_model = new CategoryModel();
            $data = $category_model->where("id", $id)->find();
            $category = $category_model->where("parent_id",0)
                ->where("s_id", $sid)->where("id","<>", $id)->select()->toArray();
            $data = json_decode(json_encode($data),true);
            $this->assign('s_id', $sid);
            $this->assign("data", $data);
            $this->assign('category', $category);
            return $this->fetch('./category/edit');
        } else {
            return $this->responseToJson([],'相关参数未获取' , 201);
        }
    }

    public function delete(Request $request)
    {
        if ($request->has("ids") && !empty($request->param("ids"))) {
            $ids = $request->param("ids");
            try{
                CategoryModel::destroy($ids);
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
