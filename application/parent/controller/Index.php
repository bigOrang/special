<?php
namespace app\parent\controller;

use app\parent\model\CategoryModel;
use app\parent\model\SpecialModel;
use app\parent\model\TopicDetailModel;
use app\parent\model\TopicModel;
use app\parent\model\StudentModel;
use app\parent\model\TopicUserModel;
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
        Session::delete("parent_questionIndex");
        Session::delete("parent_nextIndex");
        Session::delete("parent_topicUser");
        Session::delete("parent_topicCId");
        $c_id = $request->param('c_id',0);
        Log::error($c_id);
        $category = CategoryModel::where("parent_id",0)->where("s_id", session("parent_s_id"))->select();
        $category = json_decode(json_encode($category),true);
        $this->assign('category', $category);
        if ($c_id != 0) {
            $this->assign('cateNow', $c_id);
        }
        return $this->fetch('./index/index');
    }

    public function getStudentList(Request $request)
    {
        if ($request->isPost()) {
            $requestData   = $request->param();
            $studentModel = new StudentModel();
            $topicUserModel = new TopicUserModel();
            $limit = $request->param("limit", 10);
            $page = $request->param("page", 1);
            $limit = $limit * $page;
            $field = "a.*,b.id as answer_id, CASE WHEN b.td_id <> 0 and b.`c_id` = {$requestData['category']} THEN '已填写' ELSE '未填写' END AS is_over";
            $childSql = $topicUserModel->where("c_id", $requestData['category'])->field("id,c_id,td_id,t_id,user_code")->buildSql();
            $query = $studentModel->alias("a")
                ->leftJoin("{$childSql} b","a.code=b.user_code")
                ->leftJoin("t_wx_parent_bind d","d.stu_union_id=a.code")
                ->where("d.tch_account_id", session("parent_account_id"))
                ->order("a.code");
            if (isset($requestData['search']) && !empty(trim($requestData['search']))) {
                $query->where("a.name", "LIKE","%".$requestData['search']."%");
            }
            $data = $query->field($field)->group("a.code")->page(1,$limit)->select();
            $is_empty = empty(json_decode(json_encode($data),true)) ? 1 : 2;

            return $this->responseToJson(['data'=>$data,'is_empty'=>$is_empty],'查询成功');
        } else {
            return $this->responseToJson([],'不被允许的获取方式',201);
        }
    }

    public function detail(Request $request)
    {
        if ($request->isPost()) {
            $radioArr = $request->param("radio");
            $insertArr = [];
            $result = true;
            if (!empty($radioArr)) {
                $tId = array_keys($radioArr);
                $topicUserModel = new TopicUserModel();
                $data = $topicUserModel ->where("c_id", session("parent_topicCId"))
                    ->whereIn("t_id", $tId)
                    ->where("user_code", session("parent_topicUser"))
                    ->field("id,t_id")->select()->toArray();
                if (!empty($data)) {
                    foreach ($data as $key=>$value) {
                        $data[$key]['td_id'] = $radioArr[$value['t_id']];
                    }
                    $result = $this->updateBatch('t_special_topic_user', $data);
                } else {
                    foreach ($radioArr as $key =>$value) {
                        $insertArr[$key] = [
                            'td_id' => $value,
                            'c_id' => session("parent_topicCId"),
                            't_id' => $key,
                            'user_code' => session("parent_topicUser")
                        ];
                    }
                    $result = $topicUserModel->insertAll($insertArr);
                }
            }
            if ($request->param("is_end") == 1) {
                $c_id = session("parent_topicCId");
                Session::delete("parent_questionIndex");
                Session::delete("parent_nextIndex");
                Session::delete("parent_topicUser");
                Session::delete("parent_topicCId");
                if ($result !== false) {
                    return $this->responseToJson(['url'=> url("index/index"),'c_id' => $c_id],'考核成功');
                } else {
                    return $this->responseToJson(['url'=> url("index/index"),'c_id' => $c_id],'考核失败', 201);
                }
            } else {
                session("parent_questionIndex", session("parent_nextIndex"));
                if ($result !== false) {
                    return $this->responseToJson(['q_id'=>session("parent_questionIndex")],'考核成功');
                } else {
                    return $this->responseToJson([],'考核失败', 201);
                }
            }
        } else {
            $topicModel = new TopicModel();
            $topicDetail = new TopicDetailModel();
            $categoryModel = new CategoryModel();
            $specialModel = new SpecialModel();
            $topicUserModel = new TopicUserModel();
            $sId = session("parent_s_id");
            $cId = $request->param("c_id", null);
            $userCode = $request->param("id",00);
            $questionIndex = $request->param("q_id",00);
            $cId != 0 ? : $cId = null;
            if (!Session::has('parent_topicUser') && !Session::has('parent_topicCId')) {
                session("parent_topicUser", $userCode);
                session("parent_topicCId", $cId);
            } else {
                $cId = session("parent_topicCId");
                $userCode = session("parent_topicUser");
            }
            //1、获取当前试题的答题方式
            $type = $specialModel->where("id", $sId)->value("status");
            $this->assign("type", $type); //1-分制，2-等级制

            //2、获取当前试题的上级分类名称
            $steps = new Steps();
            $title = $categoryModel->where("id", $cId)->value("title");
            //3、获取上级分类的子分类集（默认为第一个），并判断是否为当前分类下的最后一个子分类集
            $cateArr = $categoryModel->where("parent_id", $cId)->column("title","id");
            if (Session::has("parent_questionIndex") && session("parent_questionIndex") == $questionIndex) {
                $category = session("parent_questionIndex");
                $b_title = $cateArr[$category];
                session("parent_questionIndex", $category);
            } else {
                $b_title = reset($cateArr);
                $category = key($cateArr);
                session("parent_questionIndex", $category);
            }
            if (empty($cateArr)) {
                $isEnd = true;
                session("parent_nextIndex", false);
            } else {
                foreach ($cateArr as $key=>$value) {
                    $steps->add($key);
                }
                $steps->setCurrent($category);
                session("parent_nextIndex", $steps->getNext());
                if ($steps->getNext() !== false) {
                    $isEnd = false;
                } else {
                    $isEnd = true;
                }
            }
            //4、获取小分类下的选项集
            $question = $topicModel->whereIn("c_id", $category)->field("id,title")->select()->toArray();
            $tIdArr =  array_column($question, "id");
            $topics = $topicDetail->field("id,content,t_id")->order(['sort' => 'desc','id'=>'asc'])->whereIn("t_id", $tIdArr)->select()->toArray();
            foreach ($question as $key=>$value) {
                foreach ($topics as $list=>$info) {
                    if ($info['t_id'] == $value['id']) {
                        $question[$key]['child'][$list] = $topics[$list];
                    }
                }
            }
            $findDetail = $topicUserModel->where("c_id", $cId)->where("user_code", session("parent_topicUser"))->whereIn("t_id", $tIdArr)->find();
            if (empty($findDetail)) {
                $insertAll = [];
                foreach ($tIdArr as $key=>$value) {
                    $insertAll[$key] = [
                        'c_id' => $cId,
                        't_id' => $value,
                        'td_id'=>0,
                        'user_code' => $userCode
                    ];
                }
                $topicUserModel->insertAll($insertAll);
            }
            if (empty($question) && empty(session("parent_nextIndex"))) {
                $category = CategoryModel::where("parent_id",0)->where("s_id", session("parent_s_id"))->select();
                $category = json_decode(json_encode($category),true);
                $this->assign('category', $category);
                $this->assign('cateNow', $cId);
                return $this->fetch("./index/index");
            }
            $this->assign("question", $question);
            $this->assign("title", $title.'--'.$b_title);
            $this->assign("c_id", $cId);
            $this->assign("code", $userCode);
            $this->assign("is_end", $isEnd);
            $this->assign("q_id", $questionIndex);
            return $this->fetch('./index/detail');
        }
    }

    public function detailEdit(Request $request)
    {
        if ($request->isPost()) {
            $radioArr = $request->param("radio");
            $result = true;
            if (!empty($radioArr)) {
                $tId = array_keys($radioArr);
                $topicUserModel = new TopicUserModel();
                $data = $topicUserModel ->where("c_id", session("parent_topicCId"))
                    ->whereIn("t_id", $tId)
                    ->where("user_code", session("parent_topicUser"))
                    ->field("id,t_id")->select()->toArray();
                if (!empty($data)) {
                    foreach ($data as $key=>$value) {
                        $data[$key]['td_id'] = $radioArr[$value['t_id']];
                    }
                    $result = $this->updateBatch('t_special_topic_user', $data);
                }
            }
            if ($request->param("is_end") == 1) {
                $c_id = session("parent_topicCId");
                Session::delete("parent_questionIndex");
                Session::delete("parent_nextIndex");
                Session::delete("parent_topicUser");
                Session::delete("parent_topicCId");
                if ($result !== false) {
                    return $this->responseToJson(['url'=> url("index/index"),'c_id' => $c_id],'考核成功');
                } else {
                    return $this->responseToJson(['url'=> url("index/index"),'c_id' => $c_id],'考核失败', 201);
                }
            } else {
                session("parent_questionIndex", session("parent_nextIndex"));
                if ($result !== false) {
                    return $this->responseToJson(['q_id'=>session("parent_questionIndex")],'考核成功');
                } else {
                    return $this->responseToJson([],'考核失败', 201);
                }
            }
        }
        $topicModel = new TopicModel();
        $topicDetail = new TopicDetailModel();
        $categoryModel = new CategoryModel();
        $specialModel = new SpecialModel();
        $topicUserModel = new TopicUserModel();
        $sId = session("parent_s_id");
        $cId = $request->param("c_id", null);
        $userCode = $request->param("id",0);
        $questionIndex = $request->param("q_id",0);
        $cId != 0 ? : $cId = null;
        if (!Session::has('parent_topicUser') && !Session::has('parent_topicCId')) {
            session("parent_topicUser", $userCode);
            session("parent_topicCId", $cId);
        } else {
            $cId = session("parent_topicCId");
            $userCode = session("parent_topicUser");
        }
        //1\获取答题类型
        $type = $specialModel->where("id", $sId)->value("status");
        $this->assign("type", $type); //1-分制，2-等级制
        //2、获取当前试题的上级分类名称
        $steps = new Steps();
        $title = $categoryModel->where("id", $cId)->value("title");
        //3、获取上级分类的子分类集（默认为第一个），并判断是否为当前分类下的最后一个子分类集
        $cateArr = $categoryModel->where("parent_id", $cId)->column("title","id");
        if (Session::has("parent_questionIndex") && session("parent_questionIndex") == $questionIndex) {
            $category = session("parent_questionIndex");
            $b_title = $cateArr[$category];
        } else {
            $b_title = reset($cateArr);
            $category = key($cateArr);
        }
        session("parent_questionIndex", $category);
        if (empty($cateArr)) {
            $isEnd = true;
            session("parent_nextIndex", false);
        } else {
            foreach ($cateArr as $key=>$value) {
                $steps->add($key);
            }
            $steps->setCurrent($category);
            session("parent_nextIndex", $steps->getNext());
            if ($steps->getNext() !== false) {
                $isEnd = false;
            } else {
                $isEnd = true;
            }
        }
        //4、获取小分类下的选项集
        $question = $topicModel->whereIn("c_id", $category)->field("id,title")->select()->toArray();
        $tIdArr =  array_column($question, "id");
        $topics = $topicDetail->field("id,content,t_id")->order(['sort' => 'desc','id'=>'asc'])->whereIn("t_id", $tIdArr)->select()->toArray();
        foreach ($question as $key=>$value) {
            foreach ($topics as $list=>$info) {
                if ($info['t_id'] == $value['id']) {
                    $question[$key]['child'][$list] = $topics[$list];
                }
            }
        }
        if (empty($question) && empty(session("parent_nextIndex"))) {
            $category = CategoryModel::where("parent_id",0)->where("s_id", session("parent_s_id"))->select();
            $category = json_decode(json_encode($category),true);
            $this->assign('category', $category);
            $this->assign('cateNow', $cId);
            return $this->fetch("./index/index");
        }
        //5获取当前问题下的考核
        $topicUsers = $topicUserModel->whereIn("t_id", $tIdArr)->where("user_code", session("parent_topicUser"))->column("td_id");
        $this->assign("question", $question);
        $this->assign("title", $title.'--'.$b_title);
        $this->assign("c_id", $cId);
        $this->assign("topicUsers", $topicUsers);
        $this->assign("is_end", $isEnd);
        $this->assign("q_id", $questionIndex);
        return $this->fetch('./index/edit');
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
