<?php

namespace App\Http\Controllers;

use App\Libray\Response;
use App\Model\Income;
use App\Model\Withdraw;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Model\User;
use App\Model\Apply;

class WithdrawController extends Controller
{
    /**
     * 提现功能
     * @param Request $request
     * @param User $userModel
     * @param Withdraw $withdrawModel
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function withdraw(Request $request, User $userModel, Withdraw $withdrawModel)
    {
        $money = $request->input('money');
        $user_id = session()->get('uid');
        $userInfo = $userModel->where([
            'user_id' => $user_id
        ])->select('money')
          ->first();

        if ($userInfo->money < $money){
            return response(Response::Error('超过可提现金额'));
        }

        DB::beginTransaction();
        try{

            $withdrawModel->user_id = $user_id;
            $withdrawModel->money = $money;
            $withdrawModel->status = 1;
            $withdrawModel->created_at = date('Y-m-d H:i:s',time());
            $withdrawModel->updated_at = date('Y-m-d H:i:s',time());
            $withdrawModel->save();
            $userModel->where([
                'user_id' => $user_id
            ])->update([
                'money' => ($userInfo->money - $money)
            ]);
            DB::commit();
            return response(Response::Success_No_Data('提现申请成功'));
        }catch (\Exception $exception){
            DB::rollBack();
            return response(Response::Error('提现申请失败'));
        }
    }

    /**
     * 提现列表
     * @param Withdraw $withdrawModel
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function withdrawList(Withdraw $withdrawModel)
    {
        $withdrawlist = $withdrawModel->where([
            'user_id' => session()->get('uid')
        ])->select('withdraw_id','user_id','money','status','note', 'created_at')
          ->paginate(10)->toArray();

        return response(Response::Success($withdrawlist));
    }

    /**
     * 收入明细
     * @param Request $request
     * @param User $userModel
     * @param Income $incomeModel
     */
    public function incomeList(Request $request, User $userModel, Income $incomeModel){
        $user_id = session()->get('uid');
        $list = $incomeModel
            ->where(['user_id' => $user_id])
            ->select('income_id', 'app_name', 'app_logo', 'money', 'created_at')
            ->paginate(10)->toArray();
        $user_info = $userModel->where('user_id', $user_id)->first(['cumulative_amount']);
        $list['cumulative_amount'] = $user_info['cumulative_amount'];
        return response(Response::Success($list));
    }


    /**
     * 领取奖励功能
     * @param Request $request
     * @param Income $incomeModel
     * @param Apply $applyModel
     * @param User $userModel
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function incomeReceive(Request $request, Income $incomeModel, Apply $applyModel, User $userModel)
    {
        $app_id = $request->input('app_id');

        $apply = $applyModel->where('app_id',$app_id)->first();

        if (!$apply){
            return response(Response::Error('该应用不存在'));
        }

        if ($apply->num < 0){
            return response(Response::Error('该应用数量不足'));
        }
        $user_id = session()->get('uid');
        $user = $userModel->where('user_id',$user_id)->first();
        if ($user->type != $apply->type){
            return response(Response::Error('该用户类型不符合'));
        }
        $res = $incomeModel->where(['user_id' => session()->get('uid'), 'app_id' => $app_id])->first();
        if ($res){
            return response(Response::Error('该奖励已被领取'));
        }
        DB::beginTransaction();
        try{
            $incomeModel->user_id = $user_id;
            $incomeModel->money = $apply->money;
            $incomeModel->app_id = $app_id;
            $incomeModel->app_logo = $apply->logo;
            $incomeModel->app_name = $apply->name;
            $incomeModel->app_type = $apply->type;
            $incomeModel->created_at = date('Y-m-d H:i:s',time());
            $incomeModel->updated_at = date('Y-m-d H:i:s',time());

            $incomeModel->save();

            $money = $apply->money + $user->money;
            $cumulative_amount = $apply->money + $user->cumulative_amount;

            $userModel->where([
                'user_id' => $user_id
            ])->update([
                'money' => $money,
                'cumulative_amount' => $cumulative_amount,
            ]);

            $applyModel->where([
                'app_id' => $app_id
            ])->where('num','>',0)->update([
                'num' => ($apply->num - 1)
            ]);
            DB::commit();
            return response(Response::Success_No_Data('领取奖励成功'));
        }catch (\Exception $exception){
            DB::rollBack();
            \Log::info($exception->getMessage());
            return response(Response::Error('领取奖励失败'));
        }

    }
}