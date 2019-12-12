<?php
namespace app\teacher\controller;

use app\admin\validate\Special;
use app\teacher\model\CategoryModel;
use app\teacher\model\OtherSchoolModel;
use app\teacher\model\OtherStudentModel;
use app\teacher\model\SpecialModel;
use app\teacher\model\TopicDetailModel;
use app\teacher\model\TopicModel;
use app\teacher\model\StudentModel;
use app\teacher\model\TopicUserModel;
use app\common\Steps;
use think\Db;
use think\Exception;
use think\facade\Log;
use think\facade\Session;
use think\Request;

class Index extends Base
{
    public function index(Request $request)
    {
        Session::delete("teacher_topicUser");
        $c_id = $request->param('c_id',0);
        if ($c_id != 0) {
            $this->assign('cateNow', $c_id);
        }
        return $this->fetch('./index/index');
    }

    public function other_index(Request $request)
     {
         Session::delete("teacher_topicUser");
         $c_id = $request->param('c_id',0);
         if ($c_id != 0) {
             $this->assign('cateNow', $c_id);
         }
         return $this->fetch('./other/index');
     }

    public function getStudentList(Request $request)
    {
        if ($request->isPost()) {
            $requestData   = $request->param();
            $studentModel = new StudentModel();
            $topicModel = new TopicModel();
            $topicUserModel = new TopicUserModel();
            $limit = $request->param("limit", 10);
            $page = $request->param("page", 1);
            $category = CategoryModel::where("parent_id","<>",0)->where("s_id", session("teacher_s_id"))->column("id");
            $allTopic = $topicModel->alias("a")->leftJoin("t_special_category b", "a.c_id=b.id")
                ->where("b.s_id", session("teacher_s_id"))->where("b.deleted_at", "NULL")->count("a.id");
            $limit = $limit * $page;
            $field = "a.*,b.id as answer_id, CASE WHEN b.num = 0 OR b.num IS NULL THEN '未填写' WHEN b.num = {$allTopic} THEN '已填写' WHEN b.num between 0 AND {$allTopic} THEN '未完成' END AS is_over";
            $childSql = $topicUserModel->whereIn("c_id", $category)->where("is_other", 0)->field("id,td_id,COUNT(CASE WHEN td_id <> 0 THEN 1 END) AS num,user_code")->group("user_code")->buildSql();
            $query = $studentModel->alias("a")
                ->leftJoin("{$childSql} b","a.code=b.user_code")
                 ->leftJoin("t_sys_class c","c.id=a.class_id")
                ->where("c.grade_no", session("teacher_grade"))
                ->where("c.class_no", session("teacher_class"))
                ->order("a.code");
            if (isset($requestData['search']) && !empty(trim($requestData['search']))) {
                $search = trim($requestData['search']);
                $query->where(function ($q) use ($search) {
                    $q->where("a.name", "LIKE","%".$search."%")
                        ->whereOr("a.code", "LIKE","%".$search."%");
                });
            }
            if (isset($requestData['category']) && !empty($requestData['category'])) {
                if ($requestData['category'] == 1) {
                    $query->where("b.num", $allTopic);
                } elseif ($requestData['category'] == 2) {
                    $query->whereBetween("b.num", [0, $allTopic]);
                } else {
                    $query->where(function ($q) {
                        $q->where("b.num", 0)->whereOr("b.num", "NULL");
                    });
                }
            }
            $data = $query->field($field)->group("a.code")->page(1,$limit)->select();
            $is_empty = empty(json_decode(json_encode($data),true)) ? 1 : 2;

            return $this->responseToJson(['data'=>$data,'is_empty'=>$is_empty],'查询成功');
        } else {
            return $this->responseToJson([],'不被允许的获取方式',201);
        }
    }

    public function getOtherStudentList(Request $request)
    {
        if ($request->isPost()) {
            $requestData   = $request->param();
            $studentModel = new OtherStudentModel();
            $topicModel = new TopicModel();
            $topicUserModel = new TopicUserModel();
            $topicId = session("teacher_other_s_id");
            $limit = $request->param("limit", 10);
            $page = $request->param("page", 1);
            $category = CategoryModel::where("parent_id","<>",0)->where("s_id", $topicId)->column("id");
            $allTopic = $topicModel->alias("a")->leftJoin("t_special_category b", "a.c_id=b.id")
                ->where("b.s_id", $topicId)->where("b.deleted_at", "NULL")->count("a.id");
            $limit = $limit * $page;
            $field = "a.*,b.id as answer_id, CASE WHEN b.num = 0 OR b.num IS NULL THEN '未填写' WHEN b.num = {$allTopic} THEN '已填写' WHEN b.num between 0 AND {$allTopic} THEN '未完成' END AS is_over";
            $childSql = $topicUserModel->whereIn("c_id", $category)->where("is_other", 1)->field("id,td_id,COUNT(CASE WHEN td_id <> 0 THEN 1 END) AS num,user_code")->group("user_code")->buildSql();
            $query = $studentModel->alias("a")
                ->where("school_id", session("teacher_other_school"))
                ->where("grade_id", session("teacher_other_grade"))
                ->where("class_id", session("teacher_other_class"))
                ->leftJoin("{$childSql} b","a.id=b.user_code")
                ->order("a.id");
            if (isset($requestData['search']) && !empty(trim($requestData['search']))) {
                $search = trim($requestData['search']);
                $query->where("a.name", "LIKE","%".$search."%");
            }
            if (isset($requestData['category']) && !empty($requestData['category'])) {
                if ($requestData['category'] == 1) {
                    $query->where("b.num", $allTopic);
                } elseif ($requestData['category'] == 2) {
                    $query->whereBetween("b.num", [0, $allTopic]);
                } else {
                    $query->where(function ($q) {
                        $q->where("b.num", 0)->whereOr("b.num", "NULL");
                    });
                }
            }
            $data = $query->field($field)->group("a.id")->page(1,$limit)->select();
            $is_empty = empty(json_decode(json_encode($data),true)) ? 1 : 2;

            return $this->responseToJson(['data'=>$data,'is_empty'=>$is_empty],'查询成功');
        } else {
            return $this->responseToJson([],'不被允许的获取方式',201);
        }
    }

    public function edit(Request $request)
    {
        if ($request->isPost()) {
            $radioArr = $request->param("radio");
            if (!empty($radioArr)) {
                foreach ($radioArr as $key=>$value) {
                    $topicUserModel = new TopicUserModel();
                    $data = $topicUserModel->where("t_id", $key)
                        ->where("user_code", session("teacher_topicUser"))
                        ->where("is_other", 0)
                        ->field("id,t_id")->select()->toArray();
                    if (!empty($data)) {
                        $topicUserModel->where("t_id", $key)
                            ->where("is_other", 0)
                            ->where("user_code", session("teacher_topicUser"))->update(['td_id'=>$value]);
                    } else {
                        $result = $topicUserModel->insert([
                            'td_id' => $value,
                            'c_id' => TopicModel::where("id", $key)->value("c_id"),
                            't_id' => $key,
                            'is_other' => 0,
                            'user_code' => session("teacher_topicUser")
                        ]);
                    }
                    if ($request->has("is_end")) {
                        return $this->responseToJson([],'success', 200);
                    } else {
                        Log::error('2');
                        return $this->responseToJson([],'success', 200);
                    }
                }
            } else {
                return $this->responseToJson([],'相关参数未获取', 302);
            }
        } else {
            $categoryModel = new CategoryModel();
            $specialModel = new SpecialModel();
            $sId = session("teacher_s_id");
            $userCode = $request->param("id",0);
            if (!Session::has('teacher_topicUser')) {
                session("teacher_topicUser", $userCode);
            }
            $topicId = $request->param("topic_id", null);
            $allCategory = $categoryModel->where("parent_id", 0)->where("s_id", $sId)->field("title,id,s_id")->select();
            $allCategory = json_decode(json_encode($allCategory, 320), true);
            if (empty($allCategory)) {
                exit($this->alertInfo('当前测评无试题'));
            }
            foreach ($allCategory as $key=>$value) {
                $topicDetail = $categoryModel->alias("a")->where("a.parent_id", $value['id'])
                    ->leftJoin("t_special_topic b", "a.id=b.c_id")->column("b.id");
                $allCategory[$key]['child'] = $topicDetail;
                $allCategory[$key]['select'] = $categoryModel->alias("a")
                    ->where("a.parent_id", $value['id'])
                    ->where("d.is_other", 0)
                    ->where("d.user_code", session("teacher_topicUser"))
                    ->leftJoin("t_special_topic b", "a.id=b.c_id")
                    ->leftJoin("t_special_topic_detail c", "b.id=c.t_id")
                    ->leftJoin("t_special_topic_user d", "d.td_id=c.id")->column("d.t_id");

            }
            $action = is_null($topicId) || empty($topicId) ? $allCategory[0]['child'][0] : $topicId;
            $batArray = $allCategory;
            $endArray = array_pop($batArray);
            $this->assign("topics", $allCategory);
            $this->assign('action', $action);
            $this->assign('end_action', end($endArray['child']));
            $this->assign("title", $specialModel->where("id", $sId)->value("title"));
            return $this->fetch('./index/edit');
        }
    }

    public function other_edit(Request $request)
    {
        $userInfo = session("other_student_info");
        if ($request->isPost()) {
            $radioArr = $request->param("radio");
            if (!empty($radioArr)) {
                foreach ($radioArr as $key=>$value) {
                    $topicUserModel = new TopicUserModel();
                    $data = $topicUserModel->where("t_id", $key)
                        ->where("user_code", $userInfo['id'])
                        ->where("is_other", 1)
                        ->field("id,t_id")->select()->toArray();
                    if (!empty($data)) {
                        $topicUserModel->where("t_id", $key)
                            ->where("is_other", 1)
                            ->where("user_code", $userInfo['id'])->update(['td_id'=>$value]);
                    } else {
                        $result = $topicUserModel->insert([
                            'td_id' => $value,
                            'c_id' => TopicModel::where("id", $key)->value("c_id"),
                            't_id' => $key,
                            'is_other' => 1,
                            'user_code' => $userInfo['id']
                        ]);
                    }
                    if ($request->has("is_end")) {
                        return $this->responseToJson([],'success', 200);
                    } else {
                        Log::error('2');
                        return $this->responseToJson([],'success', 200);
                    }
                }
            } else {
                return $this->responseToJson([],'相关参数未获取', 302);
            }
        } else {
            $categoryModel = new CategoryModel();
            $specialModel = new SpecialModel();
            $sId = session("teacher_other_s_id");
            if (empty($userInfo['id'])) {
                $this->redirect("./teacher/login/index");
            }
            $topicId = $request->param("topic_id", null);
            $allCategory = $categoryModel->where("parent_id", 0)->where("s_id", $sId)->field("title,id,s_id")->select();
            $allCategory = json_decode(json_encode($allCategory, 320), true);
            if (empty($allCategory)) {
                exit($this->alertInfo('当前测评无试题'));
            }
            foreach ($allCategory as $key=>$value) {
                $topicDetail = $categoryModel->alias("a")->where("a.parent_id", $value['id'])
                    ->leftJoin("t_special_topic b", "a.id=b.c_id")->column("b.id");
                $allCategory[$key]['child'] = $topicDetail;
                $allCategory[$key]['select'] = $categoryModel->alias("a")
                    ->where("d.is_other", 1)
                    ->where("a.parent_id", $value['id'])->where("d.user_code", $userInfo['id'])
                    ->leftJoin("t_special_topic b", "a.id=b.c_id")
                    ->leftJoin("t_special_topic_detail c", "b.id=c.t_id")
                    ->leftJoin("t_special_topic_user d", "d.td_id=c.id")->column("d.t_id");

            }
            $action = is_null($topicId) || empty($topicId) ? $allCategory[0]['child'][0] : $topicId;
            $batArray = $allCategory;
            $endArray = array_pop($batArray);
            $this->assign("topics", $allCategory);
            $this->assign('action', $action);
            $this->assign('end_action', end($endArray['child']));
            $this->assign("title", $specialModel->where("id", $sId)->value("title"));
            return $this->fetch('./other/edit');
        }
    }

    public function detail(Request $request)
    {
        $categoryModel = new CategoryModel();
        $specialModel = new SpecialModel();
        $sId = session("teacher_s_id");
        $userCode = $request->param("id",0);
        if (!Session::has('teacher_topicUser')) {
            session("teacher_topicUser", $userCode);
        }
        $topicId = $request->param("topic_id", null);
        $allCategory = $categoryModel->where("parent_id", 0)->where("s_id", $sId)->field("title,id,s_id")->select();
        foreach ($allCategory as $key=>$value) {
            $topicDetail = $categoryModel->alias("a")->where("a.parent_id", $value['id'])
                ->leftJoin("t_special_topic b", "a.id=b.c_id")->column("b.id");
            $allCategory[$key]['child'] = $topicDetail;
            $allCategory[$key]['select'] = $categoryModel->alias("a")
                ->where("d.is_other", 0)
                ->where("a.parent_id", $value['id'])->where("d.user_code", session("teacher_topicUser"))
                ->leftJoin("t_special_topic b", "a.id=b.c_id")
                ->leftJoin("t_special_topic_detail c", "b.id=c.t_id")
                ->leftJoin("t_special_topic_user d", "d.td_id=c.id")->column("d.t_id");

        }
        $action = is_null($topicId) || empty($topicId) ? $allCategory[0]['child'][0] : $topicId;
        $allCategory = json_decode(json_encode($allCategory, 320), true);
        $batArray = $allCategory;
        $endArray = array_pop($batArray);
        $this->assign("topics", $allCategory);
        $this->assign('action', $action);
        $this->assign('end_action', end($endArray['child']));
        $this->assign("title", $specialModel->where("id", $sId)->value("title"));
        return $this->fetch('./index/detail');
    }

    public function other_detail(Request $request)
    {
        $categoryModel = new CategoryModel();
        $specialModel = new SpecialModel();
        $sId = session("teacher_s_id");
        $userCode = $request->param("id",0);
        if (empty($userCode)) {
            $this->redirect("./teacher/login/index");
        }
        $topicId = $request->param("topic_id", null);
        $allCategory = $categoryModel->where("parent_id", 0)->where("s_id", $sId)->field("title,id,s_id")->select();
        foreach ($allCategory as $key=>$value) {
            $topicDetail = $categoryModel->alias("a")->where("a.parent_id", $value['id'])
                ->leftJoin("t_special_topic b", "a.id=b.c_id")->column("b.id");
            $allCategory[$key]['child'] = $topicDetail;
            $allCategory[$key]['select'] = $categoryModel->alias("a")
                ->where("d.is_other", 1)
                ->where("a.parent_id", $value['id'])->where("d.user_code", $userCode)
                ->leftJoin("t_special_topic b", "a.id=b.c_id")
                ->leftJoin("t_special_topic_detail c", "b.id=c.t_id")
                ->leftJoin("t_special_topic_user d", "d.td_id=c.id")->column("d.t_id");

        }
        $action = is_null($topicId) || empty($topicId) ? $allCategory[0]['child'][0] : $topicId;
        $allCategory = json_decode(json_encode($allCategory, 320), true);
        $batArray = $allCategory;
        $endArray = array_pop($batArray);
        $this->assign("topics", $allCategory);
        $this->assign('action', $action);
        $this->assign('end_action', end($endArray['child']));
        $this->assign("title", $specialModel->where("id", $sId)->value("title"));
        return $this->fetch('./other/detail');
    }

    public function getTopicDetail(Request $request)
    {
        $actionId = $request->param("action_id", 0);
        if (empty($actionId)) {
            return $this->responseToJson([],'未获取到相关参数', 201);
        } else {
            $topicModel = new TopicModel();
            $topicUserModel = new TopicUserModel();
            $topicDetail = $topicModel->alias("a")->leftJoin("t_special_topic_detail b", "a.id=b.t_id")
                ->where("a.id", $actionId)->field("a.title as question_name, b.*")->select();
            $selected = $topicUserModel->where("user_code", session("teacher_topicUser"))
                ->where("is_other", 1)
                ->where("t_id", $topicDetail[0]['t_id'])->value("td_id");
            $data = [
                'detail'    =>  $topicDetail,
                'select'    =>  $selected
            ];
            return $this->responseToJson($data,'success', 200);
        }
    }

    public function getOtherTopicDetail(Request $request)
    {
        $actionId = $request->param("action_id", 0);
        if (empty($actionId)) {
            return $this->responseToJson([],'未获取到相关参数', 201);
        } else {
            $studentInfo = session("other_student_info");
            $topicModel = new TopicModel();
            $topicUserModel = new TopicUserModel();
            $topicDetail = $topicModel->alias("a")->leftJoin("t_special_topic_detail b", "a.id=b.t_id")
                ->where("a.id", $actionId)->field("a.title as question_name, b.*")->select();
            $selected = $topicUserModel->where("user_code", $studentInfo['id'])
                ->where("is_other", 1)
                ->where("t_id", $topicDetail[0]['t_id'])->value("td_id");
            $data = [
                'detail'    =>  $topicDetail,
                'select'    =>  $selected
            ];
            return $this->responseToJson($data,'success', 200);
        }
    }

    public function choose(Request $request)
    {
        return $this->fetch('./index/choose');
    }

    public function other_choose(Request $request)
    {
        $this->assign("user_code", session("other_student_info")['id']);
        return $this->fetch('./other/choose');
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
