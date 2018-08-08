<?php
/**
 * Created by PhpStorm.
 * User: cao
 * Date: 8/8/2018
 * Time: 12:09 AM
 */

namespace App\Http\Controllers\Message;

use App\Http\Controllers\Controller;
use App\Http\Requests\TestRequest;
use App\Libray\Message\sendSMS;
use App\Libray\Response;
use App\Model\User;
use App\Model\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
use DB;


class MessageController extends Controller
{


    public function test(TestRequest $request)
    {
        $test = Input::get('test');

        return response(Response::Success($test));
    }
    /** 发送绑定支付宝账号短信
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function bindingAliPayCode(Request $request) {
        $mobile = Input::get('mobile');

        // 判断手机号是否已绑定
        $count = User::where('mobile', $mobile)->count();
        if ($count > 0) {
            return response(Response::Error('该手机号码已被绑定！'));
        }

        // 同一个号码60秒之内不能重复发，无状态，无法用session进行判断
        $message = Message::select('created_at')->where('phone', $mobile)->orderBy('id', 'DESC')->first();

        if ($message && strtotime($message->created_at) + 60 > time()) {
            return response(Response::Error('请稍后重新发送验证码！'));
        }

        $a = new sendSMS();
        $b = $a->bindingAliPay($mobile);
        if ($b) {
            return response(Response::Success('验证码发送成功'));
        }

        return response(Response::Error('验证码发送失败，请重试！'));
    }

    /**
     * 检测验证码是否正确
     * @param $phone string
     * @param $use_type integer
     *
     * @return bool
     */
    public function checkCode() {
        $mobile = Input::get('mobile');
        $code = Input::get('code');
        $m = Message::where([
            'phone'     => $mobile,
            'type'      => 1,
            'user_id'   => 1
        ])->orderBy('id', 'DESC')->first();

        if (empty($m)) {
            return false;
        }

        // 最后一条是验证成功过的数据
        if ($m->status == 1) {
            return false;
        }

        // 判断验证码是否过期
        if (strtotime($m->created_at) + $m->expire_minutes * 60 < time()) {
            return false;
        }

        // 判断验证次数
        if ($m->check_times >= config('app.max_check_times')) {
            return false;
        }

        // 验证码错误，验证次数+1
        if ($m->code != $code) {
            $m->check_times = $m->check_times + 1;
            $m->save();
            return false;
        }

        DB::beginTransaction();
        try{
            $m->status = 1;
            $b = $m->save();
            if ($b){
                $userModel = new User();
                $user = $userModel->where([
                    'user_id' => 1
                ])->first();
                $user->mobile = $mobile;
                $user->save();
            }
            DB::commit();
            return response(Response::Success('绑定认证手机成功'));
        }catch (\Exception $exception){
            DB::rollBack();
            return response(Response::Error(trans("ResponseMsg.User.Register.Fail")));
        }
    }
}