<?php

namespace Rid\pool;

use Rid\Base\Component;

/**
 * ConnectionPool组件
 */
class ConnectionPool extends Component
{

    // 最小连接数
    public $min;

    // 最大连接数
    public $max;

    /** @var \Swoole\Coroutine\Channel */
    protected $_queue;

    // 活跃连接数
    protected $_activeCount = 0;

    // 初始化事件
    public function onInitialize()
    {
        parent::onInitialize(); // TODO: Change the autogenerated stub
        // 设置协程模式
        $this->setCoroutineMode(Component::COROUTINE_MODE_REFERENCE);
        // 创建协程队列
        $this->_queue = new \Swoole\Coroutine\Channel($this->min);
    }

    // 获取连接池的统计信息
    public function getStats()
    {
        return [
            'current_count' => $this->getCurrentCount(),
            'queue_count'   => $this->getQueueCount(),
            'active_count'  => $this->getActiveCount(),
        ];
    }

    // 获取队列中的连接数
    public function getQueueCount()
    {
        $count = $this->_queue->stats()['queue_num'];
        return $count < 0 ? 0 : $count;
    }

    // 获取活跃的连接数
    public function getActiveCount()
    {
        return $this->_activeCount;
    }

    // 获取当前总连接数
    public function getCurrentCount()
    {
        return $this->getQueueCount() + $this->getActiveCount();
    }

    // 活跃连接数自增
    protected function activeCountIncrement()
    {
        return ++$this->_activeCount;
    }

    // 活跃连接数自减
    protected function activeCountDecrement()
    {
        return --$this->_activeCount;
    }

    // 放入连接
    protected function push($connection)
    {
        $this->activeCountDecrement();
        if ($this->getQueueCount() < $this->min) {
            return $this->_queue->push($connection);
        }
        return false;
    }

    // 弹出连接
    protected function pop()
    {
        while (true) {
            $connection = $this->_queue->pop();
            $this->activeCountIncrement();
            return $connection;
        }
    }

    // 获取连接
    public function getConnection($closure)
    {
        // 队列有连接，从队列取
        if ($this->getQueueCount() > 0) {
            return $this->pop();
        }
        // 达到最大连接数，从队列取
        if ($this->getCurrentCount() >= $this->max) {
            return $this->pop();
        }
        // 活跃连接数自增
        $this->activeCountIncrement();
        // 执行创建连接的匿名函数并返回
        return $closure();
    }

    // 释放连接
    public function releaseConnection($connection, $closure)
    {
        // 放入连接
        $this->push($connection);
        // 执行销毁连接的匿名函数
        $closure();
    }

    // 销毁连接
    public function destroyConnection($closure)
    {
        // 执行销毁连接的匿名函数
        $closure();
        // 活跃连接数自减
        $this->activeCountDecrement();
    }

}
