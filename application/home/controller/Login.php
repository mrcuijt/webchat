<?php

/**
 * 登录
 * User: sky
 * Date: 2018/6/29
 * Time: 13:57
 */

namespace app\home\controller;

use \captcha\Captcha;
use app\home\model\Base as B;

class Login extends Base
{
    public function __construct()
    {
        parent::__construct(false);
    }

    /**
     * 获取验证码
     */
    public function getCaptcha()
    {
        $captcha = new Captcha();
        $captcha->doimg();
        // 验证码保存到redis中
        $redis = redis();
        $key = $this->captchaKey . getIp();
        $val = $captcha->getCode();
        $redis->setex($key, 60, $val);
    }

    /**
     * 登录页
     */
    public function index()
    {
        if (request()->isAjax()) {
            parent::validateSign();
            $post = trimArray(input('post.'));
            $validate = validate('Login');
            if (!$validate->scene('index')->check($post)) {
                $errmsg = $validate->getError();
                $this->exitJson(1, $errmsg);
            }
            $username = $post['username'];
            $password = aes_decrypt($post['password']);
            if (!$password) {
                $this->exitJson(1, '密码格式不正确');
            }
            $captcha = $post['captcha'];
            $redis = redis();
            $captchaKey = $this->captchaKey . getIp();
            if (strtolower($captcha) !== $redis->get($captchaKey)) {
                $this->exitJson(3, '验证码不正确或已失效');
            }
            $redis->del($captchaKey);
            $b = new B();
            $userRow = $b->dbGetOne('user', 'uid,username,password', ['username' => $username, 'status' => 1]);
            if (!$userRow) {
                $this->exitJson(2, '用户或密码不正确');
            }
            if (!password_verify($password, $userRow['password'])) {
                $this->exitJson(2, '用户或密码不正确');
            }

            unset($userRow['password']);
            // 账号信息
            $redis->hMset($this->userInfoKey . $userRow['uid'], $userRow);
            $redis->expire($this->userInfoKey . $userRow['uid'], $this->userInfoKeyExpires);
            // Token
            $accessToken = $this->makeAccessToken($userRow['uid']);
            if (!$accessToken) {
                $this->exitJson(4, '登录失败');
            }
            $this->exitJson(0, '登录成功', ['uid' => $userRow['uid'], 'access_token' => $accessToken]);
        } else {
            header('Location: ' . DOMAIN . 'home/login.html');
        }
    }

    /**
     * 登出
     */
    public function logout()
    {
        parent::checkLogin();
        $redis = redis();
        $redis->del($this->accessTokenKey . $this->loginUid);
        $redis->del($this->userInfoKey . $this->loginUid);
        $this->exitJson(0, '登出成功');
    }

    /**
     * 检查登录
     */
    public function checkLogin()
    {
        parent::checkLogin();
        $this->exitJson(0, '成功');
    }

}