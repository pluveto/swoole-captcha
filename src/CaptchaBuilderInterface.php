<?php

namespace Pluveto\Swoole\Captcha;

use Psr\Http\Message\ResponseInterface;

/**
 * 验证码接口
 * Interface CaptchaBuilderInterface
 * @package Pluveto\Swoole\Captcha
 */
interface CaptchaBuilderInterface
{

    public function create(): CaptchaBuilderInterface;

    public function save(string $filename, int $quality): bool;

    public function output(ResponseInterface $response, int $quality): ResponseInterface;

    public function getBase64(int $quality): string;

    public function getBytes(int $quality): string;

    public function getText(): string;
}
