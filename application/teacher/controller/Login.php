<?php
namespace app\teacher\controller;

use app\teacher\model\CategoryModel;
use app\teacher\model\OtherClassModel;
use app\teacher\model\OtherGradeModel;
use app\teacher\model\OtherSchoolModel;
use app\teacher\model\OtherStudentModel;
use app\teacher\model\SpecialModel;
use app\teacher\model\ClassModel;
use app\teacher\model\StudentModel;
use think\Controller;
use think\Db;
use think\Exception;
use think\facade\Log;
use think\facade\Session;
use think\Request;

class Login extends Controller
{
    public function index()
    {
        $user_id = input('get.user_id');
        $school_id = input('get.school_id');
        $client_id = input('get.client_id');
        if (!empty($user_id) && !empty($school_id) && !empty($client_id)) {
            if (session('teacher_user_id') !== $user_id || session('teacher_school_id') !== $school_id) {
                Session::delete("teacher_is_login");
                Session::delete("teacher_grade");
                Session::delete("teacher_class");
                Session::delete("teacher_s_id");
            }
            session('teacher_user_id', $user_id);
            session('teacher_school_id', $school_id);
            session('teacher_client_id', $client_id);
            session('teacher_auth_status',1);
            session('teacher_is_login',1);
        } else {
            if (empty(session("teacher_user_id"))) {
                exit($this->fetch('./403',[
                    'msg' => '未获取到用户相关信息'
                ]));
            }
        }
        if (empty($school_id)) {
            $school_id = session('teacher_school_id');
        }
        $db = Db::table('t_sys_mod_biz_db')->where('school_id', $school_id)->find();
        if ($db) {
            $config = [
                'type'            => 'mysql',
                'hostname'        => $db['db_server'],
                'database'        => $db['db_name'],
                'username'        => $db['db_user'],
                'password'        => $db['db_pass'],
                'hostport'        => $db['db_port'],
                'dsn'             => '',
                'params'          => [],
                'charset'         => 'utf8',
                'prefix'          => '',
            ];
            session('teacher_db-config_'.$school_id, $config);
        } else {
            exit($this->fetch('./404',[
                'msg' => '初始化数据失败!'
            ]));
        }
//        if (!Session::has("teacher_is_login"))
//            $this->redirect("./teacher/login/index");
        return $this->fetch('./choose');
    }

    public function chooseAssess()
    {
        if (Session::has("teacher_user_id")) {
            $userId = session("teacher_user_id");
        } else {
            exit($this->fetch('./403',[
                'msg' => '身份过期，请重新登陆!'
            ]));
        }
        $classModel = new ClassModel();
        $specialModel = new SpecialModel();
        $data = $classModel->alias("a")->where("supervisor1_code", $userId)
            ->leftJoin("t_sys_grade b","a.grade_no=b.no")
            ->whereOr("supervisor2_code", $userId)
            ->whereOr("a.code","0000000")
            ->field("a.class_no, a.grade_no, b.name as grade_name, a.name as class_name")
            ->select()->toArray();
        $grade = array_values(array_unique(array_column($data, "grade_no")));
        foreach ($grade as $list=>$info) {
            foreach ($data as $key=>$value) {
                if ($value['grade_no'] == $info) {
                    $grade[$list] = ['text'=> $value['grade_name'],'value' => $value['grade_no']];
                    $class[$list][] = ['value' => $value['class_no'], 'text'=> $value['class_no'].'班'];
                    $grade[$list]['children'] = $class[$list];
                }
            }
        }
        $topic = $specialModel->where("type",1)->field("id as value,title as text")->select()->toArray();
        $this->assign("topicData", json_encode($topic, 320));
        $this->assign("gradeData", json_encode($grade, 320));
        return $this->fetch('./login');
    }

    public function chooseotherassess()
    {
        if (Session::has("teacher_user_id")) {
            $userId = session("teacher_user_id");
        } else {
            exit($this->fetch('./403',[
                'msg' => '身份过期，请重新登陆!'
            ]));
        }
        $specialModel = new SpecialModel();
        $topic = $specialModel->where("type",1)->field("id as value,title as text")->select()->toArray();
        $gender = [['value'=>'1','text'=>'男'], ['value'=>'2','text'=>'女']];
        $this->assign("topicData", json_encode($topic, 320));
        $this->assign("genderData", json_encode($gender, 320));
        return $this->fetch('./other');
    }

    public function chooseEchart()
    {
        if (Session::has("teacher_user_id")) {
            $userId = session("teacher_user_id");
        } else {
            exit($this->fetch('./403',[
                'msg' => '身份过期，请重新登陆!'
            ]));
        }
        $classModel = new ClassModel();
        $specialModel = new SpecialModel();
        $data = $classModel->alias("a")->where("supervisor1_code", $userId)
            ->leftJoin("t_sys_grade b","a.grade_no=b.no")
            ->whereOr("supervisor2_code", $userId)
            ->whereOr("a.code","0000000")
            ->field("a.class_no, a.grade_no, b.name as grade_name, a.name as class_name")
            ->select()->toArray();
        $grade = array_values(array_unique(array_column($data, "grade_no")));
        foreach ($grade as $list=>$info) {
            foreach ($data as $key=>$value) {
                if ($value['grade_no'] == $info) {
                    $grade[$list] = ['text'=> $value['grade_name'],'value' => $value['grade_no']];
                    $class[$list][] = ['value' => $value['class_no'], 'text'=> $value['class_no'].'班'];
                    $grade[$list]['children'] = $class[$list];
                }
            }
        }
        $topic = $specialModel->where("type",1)->field("id as value,title as text")->select()->toArray();
        $this->assign("topicData", json_encode($topic, 320));
        $this->assign("gradeData", json_encode($grade, 320));
        return $this->fetch('./chart');
    }

    public function chooseOtherEchart()
    {
        if (Session::has("teacher_user_id")) {
            $userId = session("teacher_user_id");
        } else {
            exit($this->fetch('./403',[
                'msg' => '身份过期，请重新登陆!'
            ]));
        }
        $schoolData = $gradeData = $classData = [];
        $specialModel = new SpecialModel();
        $school = OtherSchoolModel::column("school_name", "id");
        $schools = array_values($school);
        foreach ($schools as $key=>$value) {
            $schoolId = array_search($value, $school);
            $schoolData[$key] = ['text'=> $value,'value' => $schoolId];
            $grade = OtherGradeModel::where("school_id", $schoolId)->column("grade_name", "id");
            $grades = array_values($grade);
            if (!empty($grades)) {
                foreach ($grades as $list=>$info) {
                    $gradeId = array_search($info, $grade);
                    $gradeData[$key][$list] = ['text'=> $info.'年级', 'value'=>$gradeId];
                    $class = OtherClassModel::where("school_id", $schoolId)->where("grade_id", $gradeId)->column("class_name", "id");
                    $classes = array_values($class);
                    if (!empty($classes)) {
                        foreach ($classes as $i=>$j) {
                            $classId = array_search($j, $class);
                            $classData[$key][$list][$i] = ['text'=> $j.'班', 'value'=>$classId];
                        }
                        $gradeData[$key][$list]['children'] = $classData[$key][$list];
                    }
                }
                $schoolData[$key]['children'] = $gradeData[$key];
            }
        }

        $topic = $specialModel->where("type",1)->field("id as value,title as text")->select()->toArray();
        $this->assign("schoolData", json_encode($schoolData, 320));
        $this->assign("topicData", json_encode($topic, 320));
        return $this->fetch('./other/chart');
    }

    public function getCharts(Request $request)
    {
        if ($request->isPost()) {
            $grade = $request->param('grade');
            $class = $request->param('class');
            $topic = $request->param('topic');

            session("teacher_grade", $grade);
            session("teacher_class", $class);
            session("teacher_s_id", $topic);
            if (empty($request->has("grade")) || empty($request->has("class")) || empty($request->has("topic"))) {
                $errorMsg = "getCharts:errorMsg". 'grade:'.$grade.'，class:'.$class.'，topic:'.$topic;
                return $this->responseToJson([],'请求参数错误，请联系管理员', 201);
            } else {
                $students = StudentModel::alias("a")
                    ->leftJoin("t_sys_class b", "b.id=a.class_id")
                    ->where("b.grade_no", $grade)->where("class_no", $class)
                    ->column("a.code");
                $score = CategoryModel::where("s_id", $topic)->alias("a")
                    ->leftJoin("t_special_topic b", "a.id=b.c_id")
                    ->leftJoin("t_special_topic_detail c", "b.id=c.t_id")
                    ->leftJoin("t_special_topic_user d", "c.id=d.td_id")
                    ->group("d.user_code")
                    ->whereIn("user_code", $students)->column("sum(c.score)", "user_code");
                $mainScore = CategoryModel::where("s_id", $topic)->alias("a")
                    ->leftJoin("t_special_topic b", "a.id=b.c_id")
                    ->leftJoin("t_special_topic_detail c", "b.id=c.t_id")
                    ->group("c.t_id")->column("max(c.score) as score", "c.t_id");
                $mainScore = array_sum(array_filter($mainScore));
                $good = $general = $pass = $fail = 0;
                foreach ($score as $key=>$value) {
                    if ($mainScore * 0.9 <= $value) {
                        ++$good;
                    }elseif ($mainScore * 0.75 <= $value) {
                        ++$general;
                    }elseif ($mainScore * 0.6 <= $value) {
                        ++$pass;
                    }else {
                        ++$fail;
                    }
                }
                $eCharts = [
                    ['name'=>'优秀', 'value'=>$good],
                    ['name'=>'良好', 'value'=>$general],
                    ['name'=>'及格', 'value'=>$pass],
                    ['name'=>'不及格', 'value'=>$fail],
                ];
                return $this->responseToJson($eCharts,'success', 200);
            }
        } else {
            return $this->responseToJson([],'非法的访问方式', 201);
        }
    }

    public function getOtherCharts(Request $request)
    {
        if ($request->isPost()) {
            $school = $request->param('school');
            $grade = $request->param('grade');
            $class = $request->param('class');
            $topic = $request->param('topic');
            session("teacher_other_school", $school);
            session("teacher_other_grade", $grade);
            session("teacher_other_class", $class);
            session("teacher_other_s_id", $topic);
            if (empty($request->has("school")) || empty($request->has("grade")) || empty($request->has("class")) || empty($request->has("topic"))) {
                $errorMsg = "getCharts:errorMsg". 'grade:'.$grade.'，class:'.$class.'，topic:'.$topic;
                return $this->responseToJson([],'请求参数错误，请联系管理员', 201);
            } else {
                $students = OtherStudentModel::where("school_id", $school)->where("grade_id", $grade)
                    ->where("class_id", $class)->column("id");
                $score = CategoryModel::where("s_id", $topic)->alias("a")
                    ->leftJoin("t_special_topic b", "a.id=b.c_id")
                    ->leftJoin("t_special_topic_detail c", "b.id=c.t_id")
                    ->leftJoin("t_special_topic_user d", "c.id=d.td_id")
                    ->group("d.user_code")
                    ->whereIn("user_code", $students)->column("sum(c.score)", "user_code");
                $mainScore = CategoryModel::where("s_id", $topic)->alias("a")
                    ->leftJoin("t_special_topic b", "a.id=b.c_id")
                    ->leftJoin("t_special_topic_detail c", "b.id=c.t_id")
                    ->group("c.t_id")->column("max(c.score) as score", "c.t_id");
                $mainScore = array_sum(array_filter($mainScore));
                $good = $general = $pass = $fail = 0;
                foreach ($score as $key=>$value) {
                    if ($mainScore * 0.9 <= $value) {
                        ++$good;
                    }elseif ($mainScore * 0.75 <= $value) {
                        ++$general;
                    }elseif ($mainScore * 0.6 <= $value) {
                        ++$pass;
                    }else {
                        ++$fail;
                    }
                }
                $eCharts = [
                    ['name'=>'优秀', 'value'=>$good],
                    ['name'=>'良好', 'value'=>$general],
                    ['name'=>'及格', 'value'=>$pass],
                    ['name'=>'不及格', 'value'=>$fail],
                ];
                return $this->responseToJson($eCharts,'success', 200);
            }
        } else {
            return $this->responseToJson([],'非法的访问方式', 201);
        }
    }

    public function setGrade(Request $request)
    {
        if ($request->isPost()) {
            $requestData = $request->param();
            session("teacher_is_login",1);
            session("teacher_grade", $requestData['grade']);
            session("teacher_class", $requestData['class']);
            session("teacher_s_id", $requestData['topic']);
            return $this->responseToJson([],'success', 200);
        } else {
            return $this->responseToJson([],'非法的访问方式', 201);
        }
    }

    public function setOtherGrade(Request $request)
    {
        if ($request->isPost()) {
            $requestData = $request->post();
            Db::startTrans();
            try{
                $otherSchoolModel = new OtherSchoolModel();
                $otherGradeModel = new OtherGradeModel();
                $otherClassModel = new OtherClassModel();
                $otherStudentModel = new OtherStudentModel();
                $schoolId = $otherSchoolModel->where("school_name", $requestData['school_name'])->value("id");
                if (empty($schoolId)) {
                    if (empty($requestData['school_name'])) {
                        return $this->responseToJson([],'未获取到学校名称', 201);
                    }
                    $schoolId = $otherSchoolModel->insertGetId(['school_name'  =>  $requestData['school_name']]);
                }
                $gradeId = $otherGradeModel->where("school_id", $schoolId)->where("grade_name", $requestData['grade'])->value("id");
                if (empty($gradeId)) {
                    if (empty($requestData['grade'])) {
                        return $this->responseToJson([],'未获取到年级名称', 201);
                    }
                    $gradeId = $otherGradeModel->insertGetId(['school_id'=> $schoolId, 'grade_name'=> $requestData['grade']]);
                }
                $classId = $otherClassModel->where("school_id", $schoolId)->where("grade_id", $gradeId)->where("class_name", $requestData['class'])->value("id");
                if (empty($classId)) {
                    if (empty($requestData['class'])) {
                        return $this->responseToJson([],'未获取到班级名称', 201);
                    }
                    $classId = $otherClassModel->insertGetId([
                        'school_id' =>  $schoolId,
                        'grade_id' =>  $gradeId,
                        'class_name' =>  $requestData['class'],
                    ]);
                }
                $studentId = $otherStudentModel->where("school_id", $schoolId)->where("grade_id", $gradeId)
                    ->where("class_id", $classId)->where("id_card", $requestData['id_card'])->value("id");
                if (empty($studentId)) {
                    if (empty($requestData['id_card'])) {
                        return $this->responseToJson([],'未获取到学生身份信息', 201);
                    }
                    if (empty($requestData['student_name'])) {
                        return $this->responseToJson([],'未获取到学生名称', 201);
                    }
                    $studentId = $otherStudentModel->insertGetId([
                        'school_id'     =>  $schoolId,
                        'grade_id'      =>  $gradeId,
                        'class_id'      =>  $classId,
                        'age'           =>  isset($requestData['age']) && !empty($requestData['age']) ? $requestData['age'] : '',
                        'gender'        =>  isset($requestData['gender']) && !empty($requestData['gender']) ? $requestData['gender'] : '',
                        'id_card'       =>  isset($requestData['id_card']) && !empty($requestData['id_card']) ? $requestData['id_card'] : '',
                        'birthday'      =>  isset($requestData['birthday']) && !empty($requestData['birthday']) ? $requestData['birthday'] : '0000-00-00',
                        'parent_name'   =>  isset($requestData['parent_name']) && !empty($requestData['parent_name']) ? $requestData['parent_name'] : '',
                        'parent_phone'  =>  isset($requestData['parent_phone']) && !empty($requestData['parent_phone']) ? $requestData['parent_phone'] : '',
                        'student_name'  =>  isset($requestData['student_name']) && !empty($requestData['student_name']) ? $requestData['student_name'] : '',
                    ]);
                }
                $requestData['id'] = $studentId;
                session("other_student_info", $requestData);

                session("teacher_other_school", $schoolId);
                session("teacher_other_grade", $gradeId);
                session("teacher_other_class", $classId);
                session("teacher_other_s_id", $requestData['topic']);
                Db::commit();
                return $this->responseToJson([],'success', 200);
            } catch (Exception $exception) {
                Db::rollback();
                return $this->responseToJson([],$exception->getMessage(), 201);
            }
        } else {
            return $this->responseToJson([],'非法的访问方式', 201);
        }
    }

    public function out()
    {
        Session::delete("teacher_is_login");
        Session::delete("teacher_grade");
        Session::delete("teacher_class");
        Session::delete("teacher_s_id");
        return redirect('./teacher/login/index');
    }

    public function responseToJson($data = [], $msg = 'ok', $code = 200, $default = true) {
        if ($default) {
            return [
                'code' => $code,
                'msg' => $msg,
                'data' => $data,
            ];
        }
        return json_encode([
            'code' => $code,
            'msg' => $msg,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE);
    }
}
