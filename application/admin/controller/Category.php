<?php
namespace app\admin\controller;

use app\admin\model\AdvisoryModel;
use app\admin\model\CategoryModel;
use app\admin\model\SpecialModel;
use app\admin\model\TopicDetailModel;
use app\admin\model\TopicModel;
use app\parent\model\TopicUserModel;
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
                    ->field("a.*,CASE WHEN a.parent_id <> 0 THEN  CONCAT('└└└',a.title) ELSE a.title END AS new_title,b.id AS parent_id,b.title AS parent_title,c.title as s_title")
                    ->leftJoin("t_special_category b","b.id=a.parent_id")
                    ->leftJoin("t_special c","a.s_id=c.id")
                    ->order(['a.id'=>'asc','b.parent_id'=>'desc'])
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
                $cateGoryModel = new CategoryModel();
                $topicModel = new TopicModel();
                $c_ids = $cateGoryModel->where("id", $ids)->whereOr("parent_id", $ids)->column("id");
                $t_ids = $topicModel->whereIn("c_id", $c_ids)->column("id");
                CategoryModel::destroy($ids);
                $cateGoryModel->whereIn("id", $c_ids)->delete();
                $topicModel->whereIn("c_id", $c_ids)->delete();
                TopicDetailModel::whereIn("t_id", $t_ids)->delete();
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


    public function upload(Request $request)
    {
        if ($request->isPost()) {
            $s_id = $request->param("s_id", 0);
            $file = $request->file("files");
            if (empty($file)) {
                return $this->responseToJson([],'未获取到上传文件' , 201);
            }
            if (empty($s_id)) {
                return $this->responseToJson([],'未获取到相关参数' , 201);
            }
            $fileUrl = $_FILES['files']['tmp_name'];//文件临时存放路径
            $fileName = $_FILES['files']['name'];//文件名称
            //上传另存
//            $info = $file->rule('uniqid')->move( '.'.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'uploads');
//            $fileUrl = App::getRootPath() .'public'. DIRECTORY_SEPARATOR.'uploads'. DIRECTORY_SEPARATOR.$info->getSaveName();
            if (empty($fileUrl)) {
                return $this->responseToJson([],'请选择要导入的文件' , 201);
            }
            $type = strtolower(trim(substr(strrchr($fileName, '.'), 1)));//获取文件类型
            if ($type != 'csv') {
                return $this->responseToJson([],'请选择csv类型的文件上传' , 201);
            }
            //开始读取文件
            $handle = fopen($fileUrl, "r");
            if(!$handle){
                return $this->responseToJson([],'文件打开失败' , 201);
            }
            $hang = 1;
            $rule_1 = '/^\d+(?!(\.|．)\d*)(?!\s)/';                 //匹配一级
//        $rule_2 = '/^\d+((\.|．)\d+)+\s+/';       //匹配多级
            $rule_2 = '/^\d+(\.|．)\d+\s+/';       //匹配二级
            $rule_3 = '/^\d+(\.|．)\d+(\.|．)\d+\s+/';       //匹配三级
            $rule_4 = '/(^\s*(\d\s+)|(（\d）))/';       //匹配选项
            $content = [];
            $subscript = [];
            while ($data = fgetcsv($handle)) {
                $hang++;
                foreach ($data as $i => $val)
                    $data[$i] = mb_convert_encoding($val, "UTF-8", "GBK");
                if (!empty($data[0])) {
                    $value = $data[0];
                    $fixed = '';
                    preg_match($rule_1,$value,$content_1);    //一级分类
                    if (!empty($content_1)) {
                        $content['level_1'][$content_1[0]] = str_replace($content_1[0], '', $value);
                    } elseif (preg_match($rule_2,$value,$content_2) && !empty($content_2)) {
                        $fixed = trim($content_2[0]);
                        $cate_1 = explode(".", str_replace('．', '.', $fixed));         //分割标题前缀1.1
                        $content['level_2'][intval($cate_1[0])][intval($cate_1[1])] = str_replace($content_2[0], '', $value);
                    } elseif (preg_match($rule_3,$value,$content_3) && !empty($content_3)) {
                        $fixed = trim($content_3[0]);
                        $cate_2 = explode(".", str_replace('．', '.', $fixed));       //分割标题前缀1.1.1
                        $subscript = $cate_2;
                        $content['level_3'][intval($cate_2[0])][intval($cate_2[1])][intval($cate_2[2])] = str_replace($content_3[0], '', $value);
                    } elseif (preg_match($rule_4,$value,$content_4) && !empty($content_4)) {
                        $rule_5 = '/(^\s*(（\d）))/';
                        $fixed  = str_replace(' ', '', $value);
                        preg_match($rule_5,$value,$content_5);
                        if (!empty($content_5)) {
                            $last_arr = end($content['level_4'][intval($subscript[0])][intval($subscript[1])][intval($subscript[2])]);
                            $fixed = $last_arr.$fixed;
                            array_pop($content['level_4'][intval($subscript[0])][intval($subscript[1])][intval($subscript[2])]);
                        }
                        $content['level_4'][intval($subscript[0])][intval($subscript[1])][intval($subscript[2])][] = $fixed;
                    } else{
                        continue;
                    }
                }
            }
            fclose($handle);
            $len_result = count($content);
            if ($len_result == 0)
                return $this->responseToJson([],'文件没有任何数据' , 201);
            Db::startTrans();
            try {
                $insertNum = 0;
                $topModel = new TopicModel();
                $cateModel = new CategoryModel();
                $topDetailModel = new TopicDetailModel();
                foreach ($content['level_1'] as $key=>$value) {
                    $insertNum++;
                    $setIn    = $cateModel->where("s_id", $s_id)->where("parent_id", 0)->where("title", $value)->count();
                    if (empty($setIn)) {
                        $thisId = $cateModel->insertGetId(['s_id'=>$s_id, 'parent_id'=>0, 'title'=> $value]); //一级分类插入自增ID
                        foreach ($content['level_2'][$key] as $key2 => $value2) {
                            $insertNum++;
                            $setIn2    = $cateModel->where("s_id", $s_id)->where("parent_id", $thisId)->where("title", $value2)->count();
                            if (empty($setIn2)) {
                                $thisId2 = $cateModel->insertGetId(['s_id'=>$s_id, 'parent_id'=>$thisId, 'title'=> $value2]);
                                foreach ($content['level_3'][$key][$key2] as $key3 => $value3) {
                                    $insertNum++;
                                    $setIn3    = $topModel->where("c_id", $thisId2)->where("t_parent_id", 0)->where("title", $value3)->count();
                                    if (empty($setIn3)) {
                                        $insertLevel4Data = [];
                                        $thisId3 = $topModel->insertGetId(['c_id'=>$thisId2, 't_parent_id'=>0, 'title'=> $value3]);
                                        $score = 0;
                                        foreach ($content['level_4'][$key][$key2][$key3] as $key4 => $value4) {
                                            $insertNum++;
                                            $insertLevel4Data[] = [
                                                't_id'=>$thisId3,
                                                'score' => $score++,
                                                'content'=> $value4
                                            ];
                                        }
                                        Log::error($insertLevel4Data);
                                        $topDetailModel->insertAll($insertLevel4Data);
                                    }
                                }
                            }
                        }
                    }
                }
                Db::commit();
                return $this->responseToJson([],"导入文件成功，共计{$insertNum}条" , 200);
            } catch (\Exception $e) {
                Db::rollback();
                Log::error($e->getMessage());
                return $this->responseToJson([],'导入文件失败' , 201);
            }
        }
        if ($request->has("s_id") && !empty($request->param("s_id"))) {
            $s_id = $request->param('s_id');
            $this->assign('s_id', $s_id);
            return $this->fetch('./category/upload');
        } else {
            exit($this->fetch('./404',[
                'msg' => '相关参数未获取'
            ]));
        }
    }
}
