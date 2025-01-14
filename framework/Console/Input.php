<?php

namespace Rid\Console;

use Rid\Base\Component;

/**
 * Input组件
 */
class Input extends Component
{

    // 脚本文件名
    protected $_scriptFileName;

    // 命令
    protected $_command = [];

    // 全部选项
    protected $_options = [];

    // 初始化事件
    public function onInitialize()
    {
        parent::onInitialize();
        $this->initialize();
    }

    // 初始化
    protected function initialize()
    {
        // 解析全部选项
        $options = [];
        foreach ($GLOBALS['argv'] as $key => $value) {
            // 获取脚本文件名
            if ($key == 0) {
                $this->_scriptFileName = $value;
            }
            // 获取命令
            if (in_array($key, [1, 2])) {
                $this->_command[] = $value;
            }
            // 获取选项
            if ($key > 2) {
                if (substr($value, 0, 2) == '--') {
                    $options[] = substr($value, 2);
                } else if (substr($value, 0, 1) == '-') {
                    $options[] = substr($value, 1);
                }
            }
        }
        parse_str(implode('&', $options), $options);
        // 设置选项默认值
        foreach ($options as $name => $value) {
            if ($value === '') {
                $options[$name] = true;
            }
        }
        $this->_options = $options;
    }

    // 获取脚本文件名
    public function getScriptFileName()
    {
        return $this->_scriptFileName;
    }

    // 获取命令
    public function getCommand()
    {
        return implode(' ', $this->_command);
    }

    // 获取命令名
    public function getCommandName()
    {
        return isset($this->_command[0]) ? $this->_command[0] : '';
    }

    // 获取命令动作
    public function getCommandAction()
    {
        return isset($this->_command[1]) ? $this->_command[1] : '';
    }

    // 获取全部选项
    public function getOptions()
    {
        return $this->_options;
    }

}
