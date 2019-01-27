<?php

namespace Mix\Base;

/**
 * @property \Mix\Log\Log $log
 * @property \Mix\Console\Input $input
 * @property \Mix\Console\Output $output
 * @property \Mix\Http\Route $route
 * @property \Mix\Http\Request|\Mix\Http\Compatible\Request $request
 * @property \Mix\Http\Response|\Mix\Http\Compatible\Response $response
 * @property \Mix\Http\Error|\Mix\Console\Error $error
 * @property \Mix\Http\Token $token
 * @property \Mix\Http\Session $session
 * @property \Mix\Http\Cookie $cookie
 * @property \Mix\Database\PDOConnection $pdo
 * @property \Mix\Redis\RedisConnection $redis
 * @property \Mix\Config\Config $config
 * @property \Mix\Mailer\Mailer $swiftmailer
 * @property \Mix\Pool\ConnectionPool $connectionPool
 * @property \Mix\User\User $user
 */
class Application extends BaseObject
{
    // 初始化回调
    public $initialize = [];
    // 基础路径
    public $basePath = '';
    // 组件配置
    public $components = [];
    // 类库配置
    public $libraries = [];
    // 组件容器
    protected $_components;
    // 组件命名空间
    protected $_componentPrefix;

    // 初始化事件
    public function onInitialize()
    {
        parent::onInitialize(); // TODO: Change the autogenerated stub
        // 快捷引用
        \Mix::setApp($this);
        // 错误注册
        Error::register();
        // 执行初始化回调
        foreach ($this->initialize as $callback) {
            call_user_func($callback);
        }
    }

    // 设置组件命名空间
    public function setComponentPrefix($prefix)
    {
        $this->_componentPrefix = $prefix;
    }

    // 装载组件
    public function loadComponent($name, $return = false)
    {
        // 已加载
        if (!$return && isset($this->_components[$name])) {
            return;
        }
        // 未注册
        if (!isset($this->components[$name])) {
            throw new \Mix\Exceptions\ComponentException("组件不存在：{$name}");
        }
        // 使用配置创建新对象
        $object = \Mix::createObject($this->components[$name], $name);
        // 组件效验
        if (!($object instanceof ComponentInterface)) {
            throw new \Mix\Exceptions\ComponentException("不是组件类型：{$this->components[$name]['class']}");
        }
        if ($return) {
            return $object;
        }
        // 装入容器
        $this->_components[$name] = $object;
    }

    // 获取配置
    public function config($name)
    {
        $message = "Config does not exist: {$name}.";
        // 处理带前缀的名称
        preg_match('/(\[[\w.]+\])/', $name, $matches);
        $subname = array_pop($matches);
        $name = str_replace($subname, str_replace('.', '|', $subname), $name);
        $fragments = explode('.', $name);
        foreach ($fragments as $key => $value) {
            if (strpos($value, '[') !== false) {
                $fragments[$key] = str_replace(['[', ']'], '', $value);
                $fragments[$key] = str_replace('|', '.', $fragments[$key]);
            }
        }
        // 判断一级配置是否存在
        $first = array_shift($fragments);
        if (!isset($this->$first)) {
            throw new \Mix\Exceptions\ConfigException($message);
        }
        // 判断其他配置是否存在
        $current = $this->$first;
        foreach ($fragments as $key) {
            if (!isset($current[$key])) {
                throw new \Mix\Exceptions\ConfigException($message);
            }
            $current = $current[$key];
        }
        return $current;
    }

    // 获取配置目录路径
    public function getConfigPath()
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'config';
    }

    // 获取运行目录路径
    public function getRuntimePath()
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'runtime';
    }
}
