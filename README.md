# swoole-captcha

基于 php-captcha 的验证码插件。对 PSR7 适配。增加 Base64 输出和字节输出。

## 安装

```bash
$ composer require pluveto/swoole-captcha dev-master
```

## 使用

```php
/**
 * @api {get} /auth/captcha 获取验证码
 * @apiName GetCaptcha
 * @apiGroup auth
 * @apiVersion  1.0.0
 * @apiPermission none
 * 
 * @apiParam  {number{32-320}} [width=180] 宽度 
 * @apiParam  {number{32-320}} [height=64] 高度 
 * @apiParam  {boolean} [raw=true]
 * @apiParam  {string} [uuid=""] UUID
 */
public function getCaptcha()
{    
    $req = $this->params();

    if (!$req->uuid) {
        $uuid = uuid();
    } else {
        $uuid = $this->authValidation->validateUUID($req->uuid);
    }

    $captha = new CaptchaBuilder();

    $captha->initialize([
        'width' => $req->width,     // 宽度
        'height' => $req->height,   // 高度
        'line' => false,            // 直线
        'curve' => true,            // 曲线
        'noise' => 1,               // 噪点背景
        'fonts' => []               // 字体
    ]);
    $captha->create();
    $text = $captha->getText(); // 获取验证码文本

    // 将验证码放到缓存中
    $redis = ApplicationContext::getContainer()->get(\Hyperf\Redis\Redis::class);
    $redis->set('captcha_' . str_replace("-", "", $uuid), $text, 60);
    
    if ($req->raw) {
        // 直接输出验证码
        return $captha->output($this->response->raw(""), 1);
    } else {
        // 通过 base64 输出验证码
        return $this->success(
            [
                "uuid" => $uuid,
                "expiredAt" => time() + 60,
                "content" => $captha->getBase64(1)
            ]
        );
    }
}