<?php
/**
 * Created by PhpStorm.
 * User: Rhilip
 * Date: 2019/3/16
 * Time: 10:41
 */

namespace Rid\Http;


class ApiController extends Controller
{
    /**
     * @param array|string $methods
     * @return bool
     */
    protected function checkMethod($methods) {
        if (is_string($methods)) $methods = [$methods];

        foreach ($methods as $method) {
            if (strtolower(app()->request->method()) == strtolower($method))
                return true;
        }
        return false;
    }

    protected function buildMethodFailMsg($want_methods)
    {
        if (is_array($want_methods)) $want_methods = implode(',', $want_methods);

        app()->response->setStatusCode(405);
        $method = app()->request->method();

        return [
            'error' => 'Method Not Allowed',
            'detail' => [
                'method' => "The method `$method` is not allowed, You should use `$want_methods` in this action."
            ]
        ];
    }
}
