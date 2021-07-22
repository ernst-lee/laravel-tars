<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2021/7/22 0022
 * Time: 9:54
 */
namespace Lxj\Laravel\Tars\Core;

use Tars\cmd\Restart;
use Tars\cmd\Stop;

class Command extends \Tars\cmd\Command {
    public function run()
    {
        $cmd = $this->cmd;
        $confPath = $this->confPath;
        if (!function_exists("exec")) {
            echo "Function `exec` is not exist, please check php.ini. " . PHP_EOL;
            exit;
        }

        if ($cmd === 'start') {
            $class = new Start($confPath);
            $class->execute();
        } elseif ($cmd === 'stop') {
            $class = new Stop($confPath);
            $class->execute();
        } elseif ($cmd === 'restart') {
            $class = new Restart($confPath);
            $class->execute();
        } else {
            // 默认其实就是start
            $class = new Start($confPath);
            $class->execute();
        }
    }
}