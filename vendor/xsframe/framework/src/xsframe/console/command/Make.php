<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 刘志淳 <chun@engineer.com>
// +----------------------------------------------------------------------

namespace xsframe\console\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;

abstract class Make extends Command
{
    protected $type;

    abstract protected function getStub();

    protected function configure()
    {
        $this->addArgument('name', Argument::REQUIRED, "The name of the class");
    }

    protected function execute(Input $input, Output $output)
    {
        $name = trim($input->getArgument('name'));

        $classname = $this->getClassName($name);

        $pathname = $this->getPathName($classname);

        if (is_file($pathname)) {
            $output->writeln('<error>' . $this->type . ':' . $classname . ' already exists!</error>');
            return false;
        }

        if (!is_dir(dirname($pathname))) {
            mkdir(dirname($pathname), 0755, true);
        }

        file_put_contents($pathname, $this->buildClass($classname));

        $output->writeln('<info>' . $this->type . ':' . $classname . ' created successfully.</info>');
    }

    protected function buildClass(string $name)
    {
        $stub = file_get_contents($this->getStub());

        $namespace = trim(implode('\\', array_slice(explode('\\', $name), 0, -1)), '\\');

        $class = str_replace($namespace . '\\', '', $name);

        return str_replace(['{%className%}', '{%actionSuffix%}', '{%namespace%}', '{%app_namespace%}'], [
            $class,
            $this->app->config->get('route.action_suffix'),
            $namespace,
            $this->app->getNamespace(),
        ], $stub);
    }

    protected function getPathName(string $name): string
    {
        $name = substr($name, 4);

        return $this->app->getBasePath() . ltrim(str_replace('\\', '/', $name), '/') . '.php';
    }

    protected function getClassName(string $name): string
    {
        if (strpos($name, '\\') !== false) {
            return $name;
        }

        if (strpos($name, '@')) {
            [$app, $name] = explode('@', $name);
        } else {
            $app = '';
        }

        if (strpos($name, '/') !== false) {
            $name = str_replace('/', '\\', $name);
        }

        return $this->getNamespace($app) . '\\' . $name;
    }

    protected function getNamespace(string $app): string
    {
        return 'app' . ($app ? '\\' . $app : '');
    }

    protected function capitalizeLastFilename($path, $isDir = false)
    {
        $path = str_replace("/", "\\", $path);
        // 使用 DIRECTORY_SEPARATOR 来确保跨平台兼容性
        $parts = explode(DIRECTORY_SEPARATOR, $path);

        // 获取最后一个文件名
        $lastFile = end($parts);

        // 检查是否是文件（包含扩展名）
        $capitalizedLastFile = $lastFile;
        if (pathinfo($lastFile, PATHINFO_EXTENSION) !== '') {
            // 分离文件名和扩展名
            $filename = pathinfo($lastFile, PATHINFO_FILENAME);
            $extension = pathinfo($lastFile, PATHINFO_EXTENSION);

            // 将文件名首字母大写
            $capitalizedFilename = ucfirst($filename);

            // 重新组合文件名和扩展名
            $capitalizedLastFile = $capitalizedFilename . '.' . $extension;
        } else {
            if ($isDir) {
                $capitalizedLastFile = ucfirst($lastFile);
            }
        }

        // 将最后一个文件名替换为处理后的文件名
        array_pop($parts);
        if ($capitalizedLastFile) {
            $parts[] = $capitalizedLastFile;
        }

        // 重新组合路径
        return implode(DIRECTORY_SEPARATOR, $parts);
    }
}
