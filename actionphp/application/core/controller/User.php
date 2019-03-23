<?php
/**
 * +----------------------------------------------------------------------
 * | InitAdmin/actionphp [ InitAdmin渐进式模块化通用后台 ]
 * +----------------------------------------------------------------------
 * | Copyright (c) 2018-2019 http://initadmin.net All rights reserved.
 * +----------------------------------------------------------------------
 * | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
 * +----------------------------------------------------------------------
 * | Author: jry <ijry@qq.com>
 * +----------------------------------------------------------------------
*/

namespace app\core\controller;

use think\Db;
use think\Request;
use app\core\controller\common\Home;

/**
 * 用户控制器
 *
 * @author jry <ijry@qq.com>
 */
class User extends Home
{
    private $core_user;
    private $core_identity;
    private $core_login;

    public function __construct()
    {
        $this->core_user = new \app\core\model\User();
        $this->core_identity = new \app\core\model\Identity();
        $this->core_login = new \app\core\model\Login();
    }

    /**
    * 是否登陆
    *
    * @return \think\Response
    * @author jry <ijry@qq.com>
    */
    public function isLogin()
    {
        $uid = $this->isLogin();
        return json(['code' => 200, 'msg' => '已登录系统', 'data' => ['uid' => $uid]]);
    }

    /**
    * 获取用户信息
    *
    * @return \think\Response
    * @author jry <ijry@qq.com>
    */
    public function info()
    {
        $uid = $this->isLogin();
        try {
            $user_service = controller('core/User', 'service');
            $user_info = $user_service->info($uid);
            return json(['code' => 200, 'msg' => '用户信息', 'data' => ['user_info' => $user_info]]);
        } catch(\Exception $e) {
            return json(['code' => 0, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 用户登录
     *
     * @param  \think\Request  $request
     * @return \think\Response
     * @author jry <ijry@qq.com>
     */
    public function login(Request $request)
    {
        // 获取提交的账号密码
        $account = trim(input('post.account'));
        $password = input('post.password');

        // 数据验证
        if (!$account) {
            return json(['code' => 0, 'msg' => '请输入账号']);
        }
        if (!$password) {
            return json(['code' => 0, 'msg' => '请输入密码']);
        }

        //匹配登录方式
        if (preg_match("/^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/", $account)) {// 邮箱登录
            $map = [];
            $map['identity_type'] = 2;
            $map['identifier'] = $account;
            $user_identity_info = $this->core_identity
                ->where($map)
                ->find();
            if (!$user_identity_info) {
                return json(['code' => 0, 'msg' => '邮箱不存在']);
            }
            if ($user_identity_info['verified'] !== 1) {
                return json(['code' => 0, 'msg' => '邮箱未通过验证']);
            }
            $user_info = $this->core_user
                ->where(['id' => $user_identity_info['uid']])
                ->find();
            if (!$user_info) {
                return json(['code' => 0, 'msg' => '用户不存在']);
            }
            if ($user_info['status'] !== 1) {
                return json(['code' => 0, 'msg' => '账号状态异常']);
            }
        } elseif (preg_match("/^1\d{10}$/", $account)) { // 手机号登录
            $map = [];
            $map['identity_type'] = 1;
            $map['identifier'] = $account;
            $user_identity_info = $this->core_identity
                ->where($map)
                ->find();
            if (!$user_identity_info) {
                return json(['code' => 0, 'msg' => '手机号不存在']);
            }
            if ($user_identity_info['verified'] !== 1) {
                return json(['code' => 0, 'msg' => '手机号未通过验证']);
            }
            $user_info = $this->core_user->where(['id' => $user_identity_info['uid']])->find();
            if (!$user_info) {
                return json(['code' => 0, 'msg' => '用户不存在']);
            }
            if ($user_info['status'] !== 1) {
                return json(['code' => 0, 'msg' => '账号状态异常']);
            }
        } else { // 用户名登录
            $map = [];
            $map['username'] = $account;
            $user_info = $this->core_user
                ->where($map)
                ->find();
            if (!$user_info) {
                return json(['code' => 0, 'msg' => '用户名不存在']);
            }
            if ($user_info['status'] !== 1) {
                return json(['code' => 0, 'msg' => '账号状态异常']);
            }
        }
        if ($user_info['password'] !== user_md5($password, $user_info['key'])) {
            return json(['code' => 0, 'msg' => '密码错误']);
        }

        // 记录登录状态
        try {
            $user_service = controller('core/User', 'service');
            $jwt = $user_service->login($user_info, input('post.client'));
        } catch(\Exception $e) {
            return json(['code' => 0, 'msg' => '登录失败:' . $e->getMessage()]);
        }
        if ($jwt) {
            return json(['code' => 200, 'msg' => '登陆成功', 'data' => ['token' => $jwt]]);
        } else {
            return json(['code' => 0, 'msg' => '添加失败', 'data' => []]);
        }
    }

    /**
    * 注销登录
    *
    * @return \think\Response
    * @author jry <ijry@qq.com>
    */
    public function logout()
    {
        $ret = $this->is_login();
        if ($ret['code'] == 200) {
            $ret = $this->core_login
                ->where('token', $ret['data']['data']->token)
                ->delete(true);
            if ($ret) {
                return json(['code' => 200, 'msg' => '注销成功', 'data' => []]);
            } else {
                return json(['code' => 0, 'msg' => '注销失败', 'data' => []]);
            }
        } else {
            return json($ret);
        }
    }
}
