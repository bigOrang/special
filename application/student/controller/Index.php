<?php
namespace app\student\controller;

use app\student\model\CategoryModel;
use app\student\model\ClassModel;
use app\student\model\SpecialModel;
use app\student\model\StudentParentsModel;
use app\student\model\TopicDetailModel;
use app\student\model\TopicModel;
use app\student\model\StudentModel;
use app\student\model\TopicUserModel;
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
            $field = "a.*,b.id as answer_id, CASE WHEN b.td_id <> 0  THEN '已填写' ELSE '未填写' END AS is_over";
            $childSql = $topicUserModel->field("id,c_id,td_id,t_id,user_code")->buildSql();
            $query = $studentModel->alias("a")
                ->leftJoin("{$childSql} b","a.code=b.user_code")
                ->leftJoin("t_sys_class c","c.id=a.class_id")
                ->where("c.code", '0000000')
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

    public function add(Request $request)
    {
        if ($request->isPost()) {
            $data = $this->validation($request->param(), "AddStudent");
            Db::connect(session('add_student_db-config' . session("add_student_school_id")))->startTrans();
            try {
                $code = substr(time(),1);
                (new \app\student\model\StudentModel)->create([
                    'name' => $data['name'],
                    'class_id' => $data['class_id'],
                    'idcard' => $data['idcard'],
                    'gender' => $data['gender'],
                    'code' => $code
                ]);
                StudentParentsModel::create([
                    'user_code' => $code,
                    'name'      => $data['parentName'],
                    'phone'      => $data['phone'],
                ]);
                Db::connect(session('add_student_db-config' . session("add_student_school_id")))->commit();
            } catch (\Exception $e) {
                Db::connect(session('add_student_db-config' . session("add_student_school_id")))->rollback();
                Log::error('账号添加失败：【' . $e->getMessage() . '】');
                return $this->responseToJson([],'添加失败'.$e->getMessage() , 201);
            }
            $userId = \session("add_student_user_id");
            $schoolId = \session("add_student_school_id");
            $clientId = \session("add_student_client_id");
            $url = url("teacher/index/index") ."?user_id={$userId}&school_id={$schoolId}&client_id={$clientId}";
            return $this->responseToJson(['target'=>$url],'添加成功' , 200);
        }
        $classes = ClassModel::where("code",'0000000')->value("id");
        $this->assign("classes", $classes);
        return $this->fetch('./index/add');
    }

    public function edit(Request $request)
    {
        if ($request->isPost()) {
            $data = $this->validation($request->param(), "AddStudent");
            Db::connect(session('add_student_db-config' . session("add_student_school_id")))->startTrans();
            try {
                StudentModel::where("code", $data['code'])->update([
                    'name' => $data['name'],
                    'idcard' => $data['idcard'],
                    'gender' => $data['gender'],
                ]);
                StudentParentsModel::where("user_code", $data['code'])->update([
                    'name'      => $data['parentName'],
                    'phone'      => $data['phone'],
                ]);
                Db::connect(session('add_student_db-config' . session("add_student_school_id")))->commit();
            } catch (\Exception $e) {
                Db::connect(session('add_student_db-config' . session("add_student_school_id")))->rollback();
                Log::error('账号编辑失败：【' . $e->getMessage() . '】');
                return $this->responseToJson([],'编辑失败'.$e->getMessage() , 201);
            }
            return $this->responseToJson(['target'=>url("index/index")],'编辑成功' , 200);
        }
        $id = $request->param("id");
        $student = StudentModel::where("code", $id)->find();
        $parent = StudentParentsModel::where("user_code", $id)->find();
        $this->assign("student", $student);
        $this->assign("parent", $parent);
        return $this->fetch('./index/edit');
    }

    public function validation($data, $name)
    {
        $valid = $this->validate($data, $name);
        if (true !== $valid) {
            exit($this->responseToJson([], $valid, 201, false));
        }
        return array_filter($data);
    }
}
