<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2021 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
namespace xsframe\console\command\make;

use xsframe\console\command\Make;

class Listener extends Make
{
    protected $type = "Listener";

    protected function configure()
    {
        parent::configure();
        $this->setName('make:listener')
            ->setDescription('Create a new listener class 2');
    }

    protected function getStub(): string
    {
        return __DIR__ . DIRECTORY_SEPARATOR . 'stubs' . DIRECTORY_SEPARATOR . 'listener.stub';
    }

    protected function getNamespace(string $app): string
    {
        return parent::getNamespace($app) . '\\listener';
    }
}
