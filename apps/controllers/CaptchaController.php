<?php
/**
 * Created by PhpStorm.
 * User: Rhilip
 * Date: 2019/1/8
 * Time: 21:50
 */

namespace apps\controllers;

use Rid\Http\Captcha;

class CaptchaController
{
    public function actionIndex()
    {
        app()->response->setHeader('Content-Type', 'image/png');
        $captcha = new Captcha([
            'width'      => 150,
            'height'     => 40,
            'fontFile'   => app()->getPublicPath() . '/static/fonts/Times New Roman.ttf',
            'fontSize'   => 20,
            'wordNumber' => 6,
            'angleRand'  => [-20, 20],
            'xSpacing'   => 0.82,
            'yRand'      => [5, 15],
        ]);
        $captcha->generate();
        app()->session->set('captchaText', $captcha->getText());
        return $captcha->getContent();
    }
}
