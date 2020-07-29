<?php

namespace Pluveto\Swoole\Captcha;

use Psr\Http\Message\ResponseInterface;

/**
 * Created by PhpStorm.
 * User: lifeilin
 * Date: 2017/1/11 0011
 * Time: 10:19
 * 
 * Modified by Pluveto to support swoole better.
 */
class CaptchaBuilder implements CaptchaBuilderInterface
{
    /**
     * @var resource 验证码图片
     */
    protected $image;
    /**
     * @var string 验证码文字
     */
    protected string $text;
    /**
     * @var string 随机字符
     */
    protected string $characters = '2346789abcdefghjmnpqrtuxyzABCDEFGHJMNPQRTUXYZ';
    /**
     * @var int 图片宽度
     */
    protected int $width = 150;
    /**
     * @var int 图片高度
     */
    protected int $height = 40;

    private $fonts = [];
    /**
     * @var int 验证码字符的个数
     */
    private int $number = 4;
    /**
     * @var int 字体大小
     */
    private int $fontSize = 24;
    /**
     * @var string 验证码字体
     */
    private string $textFont;

    private int $noiseLevel = 30;

    private int $backColor;
    /**
     * @var bool 是否添加干扰线
     */
    private bool $isDrawLine = false;
    /**
     * @var bool 是否启用曲线
     */
    private bool $isDrawCurve = true;
    /**
     * @var bool 是否启用背景噪音
     */
    private bool $isDrawNoise = true;


    public function __construct()
    {
        setlocale(LC_ALL, 'zh_CN.UTF-8');
        $this->image = null;
        $this->initialize([]);
    }

    public function initialize(array $config): void
    {

        isset($config['width']) && $this->width = $config['width'];
        $this->height = isset($config['height']) ? $config['height'] : 40;
        $this->number = isset($config['number']) ? $config['number'] : 4;
        $this->fontSize = intval($this->width / floatval($this->number * 1.5));
        isset($config['line']) && $this->isDrawLine = boolval($config['line']);
        isset($config['curve']) && $this->isDrawCurve = boolval($config['curve']);
        isset($config['noise']) && $this->isDrawNoise = boolval($config['noise']);

        if (isset($config['fonts']) && empty($config['fonts']) === false) {
            $this->fonts = $config['fonts'];
        } else {
            $fontDir = __DIR__ . '/fonts/';

            $this->fonts = array_filter(array_slice(scandir($fontDir), 2), function ($file) use ($fontDir) {
                return is_file($fontDir . $file) && strcasecmp(pathinfo($file, PATHINFO_EXTENSION), 'ttf') === 0;
            });
            if (empty($this->fonts) === false) {
                foreach ($this->fonts as &$font) {
                    $font = $fontDir . $font;
                }
                unset($font);
            }
        }
        $this->noiseLevel = $this->width * 10 / $this->height;
    }

    public function create(): CaptchaBuilder
    {
        $this->image = imagecreate($this->width, $this->height);

        list($red, $green, $blue) = $this->getLightColor();

        $this->backColor = imagecolorallocate($this->image, $red, $green, $blue);

        imagefill($this->image, 0, 0, $this->backColor);
        if (empty($this->fonts)) {
            throw new \Exception('字体不存在');
        }

        $this->textFont = $this->fonts[array_rand($this->fonts)];

        $this->isDrawNoise && $this->drawNoise();

        if ($this->isDrawLine) {
            $square = $this->width * $this->height;
            $effects = mt_rand($square / 3000, $square / 2000);
            for ($e = 0; $e < $effects; $e++) {
                $this->drawLine($this->image, $this->width, $this->height);
            }
        }
        $this->isDrawCurve && $this->drawSineLine();

        $codeNX = 0; // 验证码第N个字符的左边距
        $code = [];

        for ($i = 0; $i < $this->number; $i++) {
            $code[$i] = $this->characters[mt_rand(0, strlen($this->characters) - 1)];
            $codeNX += mt_rand($this->fontSize * 1, $this->fontSize * 1.3);

            list($red, $green, $blue) = $this->getDeepColor();
            $color = imagecolorallocate($this->image, $red, $green, $blue);
            if ($color === false) {
                $color = mt_rand(50, 200);
            }
            imagettftext($this->image, $this->fontSize, mt_rand(-40, 40), $codeNX, $this->fontSize * 1.2, $color, $this->textFont, $code[$i]);
        }

        $this->text = strtolower(implode('', $code));
        return $this;
    }

    public function save(string $filename, int $quality): bool
    {
        return imagepng($this->image, $filename, $quality);
    }

    public function output(ResponseInterface $response, int $quality): ResponseInterface
    {
        return $response->withHeader("Cache-Control", "private, max-age=0, no-store, no-cache, must-revalidate")
            ->withHeader("Pragma", "no-cache")->withHeader("content-type", "image/png")->withBody(new SwooleStream($this->getBytes($quality)));
    }
    public function getBase64(int $quality): string
    {
        $dataUri = "data:image/png;base64," . base64_encode($this->getBytes($quality));
        return $dataUri;
    }
    public function getBytes(int $quality): string
    {
        ob_start();
        imagepng($this->image, null, $quality);
        $contents = ob_get_contents();
        ob_end_clean();
        return $contents;
    }
    public function getText(): string
    {
        return $this->text;
    }

    public function destroy(): void
    {
        @imagedestroy($this->image);
    }
    public function __destruct()
    {
        if ($this->image) {
            $this->destroy();
        }
    }

    private function getFontColor()
    {
        [$red, $green, $blue] = $this->getDeepColor();
        var_dump([$red, $green, $blue]);
        return imagecolorallocate($this->image, $red, $green, $blue);
    }
    /**
     *  画曲线
     */
    protected function drawSineLine()
    {
        $px = $py = 0;

        // 曲线前部分
        $A = mt_rand(1, $this->height / 2);                  // 振幅
        $b = mt_rand(-$this->height / 4, $this->height / 4);   // Y轴方向偏移量
        $f = mt_rand(-$this->height / 4, $this->height / 4);   // X轴方向偏移量
        $T = mt_rand($this->height, $this->width * 2);  // 周期
        $w = (2 * M_PI) / $T;

        $px1 = 0;  // 曲线横坐标起始位置
        $px2 = mt_rand($this->width / 2, $this->width * 0.8);  // 曲线横坐标结束位置

        $color = imagecolorallocate($this->image, mt_rand(1, 150), mt_rand(1, 150), mt_rand(1, 150));

        for ($px = $px1; $px <= $px2; $px = $px + 1) {
            if ($w != 0) {
                $py = $A * sin($w * $px + $f) + $b + $this->height / 2;  // y = Asin(ωx+φ) + b
                $i = (int) ($this->fontSize / 5);
                while ($i > 0) {
                    imagesetpixel($this->image, $px + $i, $py + $i, $color);  // 这里(while)循环画像素点比imagettftext和imagestring用字体大小一次画出（不用这while循环）性能要好很多
                    $i--;
                }
            }
        }

        // 曲线后部分
        $A = mt_rand(1, $this->height / 2);                  // 振幅
        $f = mt_rand(-$this->height / 4, $this->height / 4);   // X轴方向偏移量
        $T = mt_rand($this->height, $this->width * 2);  // 周期
        $w = (2 * M_PI) / $T;
        $b = $py - $A * sin($w * $px + $f) - $this->height / 2;
        $px1 = $px2;
        $px2 = $this->width;

        for ($px = $px1; $px <= $px2; $px = $px + 1) {
            if ($w != 0) {
                $py = $A * sin($w * $px + $f) + $b + $this->height / 2;  // y = Asin(ωx+φ) + b
                $i = (int) ($this->fontSize / 5);
                while ($i > 0) {
                    imagesetpixel($this->image, $px + $i, $py + $i, $color);
                    $i--;
                }
            }
        }
    }

    /**
     * Draw lines over the image
     */
    protected function drawLine($image, $width, $height, $tcol = null)
    {
        if ($tcol === null) {
            $tcol = imagecolorallocate($image, mt_rand(100, 255), mt_rand(100, 255), mt_rand(100, 255));
        }
        if (mt_rand(0, 1)) { // Horizontal
            $Xa   = mt_rand(0, $width / 2);
            $Ya   = mt_rand(0, $height);
            $Xb   = mt_rand($width / 2, $width);
            $Yb   = mt_rand(0, $height);
        } else { // Vertical
            $Xa   = mt_rand(0, $width);
            $Ya   = mt_rand(0, $height / 2);
            $Xb   = mt_rand(0, $width);
            $Yb   = mt_rand($height / 2, $height);
        }
        imagesetthickness($image, mt_rand(1, 3));
        imageline($image, $Xa, $Ya, $Xb, $Yb, $tcol);
    }

    /**
     * 画杂点
     * 往图片上写不同颜色的字母或数字
     */
    private function drawNoise()
    {

        $codeSet = '2345678abcdefhijkmnpqrstuvwxyz';
        for ($i = 0; $i < $this->noiseLevel; $i++) {
            list($red, $green, $blue) = $this->getLightColor();

            //杂点颜色
            $noiseColor = imagecolorallocate($this->image, $red, $green, $blue);
            for ($j = 0; $j < 5; $j++) {
                // 绘杂点
                imagestring($this->image, 5, mt_rand(-10, $this->width),  mt_rand(-10, $this->height), $codeSet[mt_rand(0, 29)], $noiseColor);
            }
        }
    }

    /**
     * 获取随机浅色
     * @return array
     */
    private function getLightColor()
    {
        $colors[0] = 200 + mt_rand(1, 55);
        $colors[1] = 200 + mt_rand(1, 55);
        $colors[2] = 200 + mt_rand(1, 55);

        return $colors;
    }

    /**
     * 获取随机颜色
     * @return array
     */
    private function getRandColor()
    {
        $red = mt_rand(1, 254);
        $green = mt_rand(1, 254);

        if ($red + $green > 400) {
            $blue = 0;
        } else {
            $blue = 400 - $green - $red;
        }
        return [$red, $green, $blue];
    }

    /**
     * 获取随机深色
     * @return array
     */
    private function getDeepColor()
    {
        list($red, $green, $blue) = $this->getRandColor();
        $increase  = 30 + mt_rand(1, 254);

        $red = abs(min(255, $red - $increase));
        $green  = abs(min(255, $green - $increase));
        $blue  = abs(min(255, $blue - $increase));

        return [$red, $green, $blue];
    }

    /**
     * Get 随机字符
     *
     * @return  string
     */ 
    public function getCharacters()
    {
        return $this->characters;
    }

    /**
     * Set 随机字符
     *
     * @param  string  $characters  随机字符
     *
     * @return  self
     */ 
    public function setCharacters(string $characters)
    {
        $this->characters = $characters;

        return $this;
    }
}
