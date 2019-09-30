<?php
namespace app\teacher\controller;

use app\teacher\model\CategoryModel;
use app\teacher\model\SpecialModel;
use app\teacher\model\ClassModel;
use app\teacher\model\StudentModel;
use think\Controller;
use think\facade\Log;
use think\facade\Session;
use think\Request;

class Login extends Controller
{
    public function index()
    {
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

    public function getCharts(Request $request)
    {
        if ($request->isPost()) {
            $grade = $request->param('grade', 0);
            $class = $request->param('class', 0);
            $topic = $request->param('topic', 0);
            if (empty($grade) || empty($class) || empty($topic)) {
                $errorMsg = "getCharts:errorMsg". 'grade:'.$grade.'，class:'.$class.'，topic:'.$topic;
                Log::error($errorMsg);
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
                    ->field("max(c.score) as score")->group("c.t_id")->column("score");
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
