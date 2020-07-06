<?php
/**
 * This file is part of the wangningkai/olaindex.
 * (c) wangningkai <i@ningkai.wang>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Setting;
use App\Models\User;
use OneDrive;
use Illuminate\Http\Request;
use Cache;

class AdminController extends BaseController
{
    /**
     * 全局设置
     * @param Request $request
     * @return mixed
     */
    public function config(Request $request)
    {
        if ($request->isMethod('get')) {
            return view(config('olaindex.theme') . 'admin.config');
        }
        $data = $request->except('_token');
        Setting::batchUpdate($data);
        $this->showMessage('保存成功！');
        return redirect()->back();
    }

    /**
     * 账号设置
     * @param Request $request
     * @return mixed
     */
    public function profile(Request $request)
    {
        if ($request->isMethod('get')) {
            return view(config('olaindex.theme') . 'admin.profile');
        }

        $request->validate([
            'old_password' => 'required',
            'password' => 'required|min:8|confirmed',
            'password_confirmation' => 'required|min:8',
        ]);

        /* @var $user User */
        $user = $request->user();
        if (!\Hash::check($request->get('old_password'), $user->password)) {
            $this->showMessage('原密码错误！', false);
            return redirect()->back();
        }

        if ($user->fill([
            'password' => \Hash::make($request->get('password'))
        ])->save()) {
            $this->showMessage('修改成功！');
        } else {
            $this->showMessage('密码修改失败！', false);
        }


        return redirect()->back();
    }

    /**
     * 账号列表
     * @return mixed
     */
    public function account()
    {
        $accounts = Account::query()
            ->select(['id', 'accountType', 'remark', 'status', 'updated_at'])
            ->simplePaginate();
        return view(config('olaindex.theme') . 'admin.account', compact('accounts'));
    }

    /**
     * 账号设置
     * @param Request $request
     * @param $id
     * @return mixed
     */
    public function accountConfig(Request $request, $id)
    {
        $account = Account::find($id);
        if (!$account) {
            $this->showMessage('账号不存在！', true);
            return redirect()->back();
        }
        $uuid = $account->hash_id;
        if ($request->isMethod('get')) {
            $config = setting($uuid, []);
            return view(config('olaindex.theme') . 'admin.account-config', compact('config'));
        }
        $data = $request->except('_token');
        setting_set($uuid, $data);
        $this->showMessage('保存成功！');
        return redirect()->back();
    }

    public function accountSet(Request $request)
    {
        $id = $request->post('id', 0);
        $account = Account::find($id);
        if (!$account) {
            return response()->json([
                'error' => '账号不存在！'
            ]);
        }
        setting_set('primary_account', $id);
        return response()->json([
            'error' => ''
        ]);
    }

    public function accountRemark($id, Request $request)
    {
        $account = Account::find($id);
        if (!$account) {
            return response()->json([
                'error' => '账号不存在！'
            ]);
        }
        $remark = $request->get('remark');
        $account->remark = $remark;
        if ($account->save()) {
            return response()->json();
        }
        return response()->json([
            'error' => ''
        ]);
    }

    /**
     * 账号详情
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function accountDetail($id)
    {
        $key = 'ac:id:' . $id;
        if (!Cache::has($key)) {
            $data = OneDrive::account($id)->fetchInfo();
            $quota = array_get($data, 'quota', '');
            if (!$quota) {
                return response()->json($data);
            }
            Cache::add($key, $data, 300);
            $info = Cache::get($key);
        } else {
            $info = Cache::get($key);
        }
        $data = array_get($info, 'quota', []);
        return response()->json($data);
    }

    /**
     * 网盘详情
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function driveDetail($id)
    {
        $key = 'dr:id:' . $id;
        if (!Cache::has($key)) {
            $data = OneDrive::account($id)->fetchMe();
            $id = array_get($data, 'id', '');
            if (!$id) {
                return response()->json($data);
            }
            Cache::add($key, $data, 300);
            $info = Cache::get($key);
        } else {
            $info = Cache::get($key);
        }
        return response()->json($info);
    }
}
