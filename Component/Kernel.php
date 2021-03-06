<?php

/**
 * Author: liangwengao
 * Email: 871024608@qq.com
 * Date: 2017-01-25
 * Time: 12:57
 */

namespace Awen\Bundles\Component;

use Awen\Bundles\Exceptions\BundleException;
use Awen\Bundles\Contracts\BundleInterface;
use Awen\Bundles\Contracts\KernelInterface;
use Awen\Bundles\Exceptions\BundleNotFoundException;
use Awen\Bundles\Extensions\ToolExtend;
use Illuminate\Foundation\Application;

abstract class Kernel extends ToolExtend implements KernelInterface
{
    /**
     * 全部包
     * @var array
     */
    protected $bundles = [];

    /**
     * 全部模块
     * @var array
     */
    protected $modules = [];

    /**
     * 全部服务实例
     * @var array
     */
    protected $services = [];

    /**
     * 处理Bundle开始时间
     * @var float
     */
    protected $start_time;

    /**
     * 当前处理Bundle使用时间
     * @var float
     */
    protected $use_time;

    /**
     * 是否引导
     * @var bool
     */
    protected $booted = false;

    /**
     * 当前path
     * @var bool
     */
    protected $root_path = false;

    /**
     * 版本
     */
    const VERSION = '5.2.1';

    /**
     * @var Application
     */
    private $app;

    public function __construct(Application $app, $root_path)
    {
        $this->app = $app;
        $this->root_path = $root_path;
        $this->start_time = microtime(true);
    }

    /**
     * 启动引导
     */
    public function boot()
    {
        $this->initializeBundles();
        $this->initializeModules();
        $this->initializeServices();
        $this->booted = true;
        $this->use_time = (microtime(true) - (float)$this->start_time);
    }

    /**
     * 初始化bundles
     * @throws BundleException
     * @throws BundleNotFoundException
     */
    protected function initializeBundles()
    {
        $bundles = $this->getArrDefault($this->registerBundles(), []);
        foreach ($bundles as $bundle_class) {
            if (!class_exists($bundle_class)) {
                $err = [
                    'en' => "[{$bundle_class}] This bundle entrance file non existent!",
                    'zh' => "[{$bundle_class}] 这个 Bundle 入口文件不存在!"
                ];
                throw new BundleNotFoundException($err);
            }

            $_name = $this->getName($bundle_class);
            $bundle = new $bundle_class($this->app, $this, $_name);
            if (!$bundle instanceof BundleInterface) {
                $err = [
                    'en' => "[{$this->getClassName($bundle)}] This bundle entrance file must realize to 'BundleInterface' interface!",
                    'zh' => "[{$this->getClassName($bundle)}] 这个 Bundle 入口文件必须实现 'BundleInterface' 接口!"
                ];
                throw new BundleException($err);
            }

            if (isset($this->bundles[$_name])) {
                $err = [
                    'en' => "[{$bundle_class}] Attempting to register two identical names of the bundle",
                    'zh' => "[{$bundle_class}] 试图注册两个名称相同的 Bundle !"
                ];
                throw new BundleException($err);
            }

            $this->bundles[$_name] = $bundle;
        }
    }

    /**
     * 初始化模块
     */
    protected function initializeModules()
    {
        foreach ($this->bundles as $bundle) {
            $bundle->initializeModules();
        }
    }

    /**
     * 初始化服务
     */
    protected function initializeServices()
    {
        foreach ($this->bundles as $bundle) {
            $bundle->initializeServices();
        }
    }

    /**
     * 获取所有包
     * @return array
     */
    public function getBundles()
    {
        return $this->bundles;
    }

    /**
     * 获取所有模块
     * @return array
     */
    public function getModules()
    {
        if (empty($this->modules)) {
            $_modules = [];
            foreach ($this->bundles as $b_name => $bundle) {
                $modules = $bundle->getModules();
                foreach ($modules as $m_name => $module) {
                    $key = $b_name . '.' . $m_name;
                    $_modules[$key] = $module;
                }
            }
            return ($this->modules = $_modules);
        }

        return $this->modules;
    }

    /**获取服务
     * @param $name
     * @param $reset
     * @return mixed|null
     * @throws BundleNotFoundException
     * @throws BundleException
     */
    public function getService($name, $reset)
    {
        $param = explode(':', $name);
        if(count($param) != 2){
            $err = [
                'en' =>"[{$name}] Get service format error, Please get registered service with 'BundleName:ServiceName'!",
                'zh' =>"[{$name}] 获取服务格式错误，请以 'BundleName:ServiceName' 获取注册的服务!"
            ];
            throw new BundleException($err);
        }
        list($b_name, $s_name) = $param;
        $_b_name = $this->snakeName($b_name);
        $_s_name = $this->snakeName($s_name);

        $key = $_b_name. ':' . $_s_name;
        if (isset($this->services[$key]) && !$reset) {
            return $this->services[$key];
        }

        if(!isset($this->bundles[$_b_name])){
            $err = [
                'en' =>"[{$name}] Can't find this bundle!",
                'zh' =>"[{$name}] 找不到这个 Bundle!"
            ];
            throw new BundleNotFoundException($err);
        }

        $service = $this->bundles[$_b_name]->makeService($_b_name, $_s_name);
        if (!is_null($service)) {
            return $this->services[$key] = $service;
        }

        return null;
    }

    /**
     * 获取所有Listen
     * @return array
     */
    public function getEventFiles(){
        $event_files = [];
        foreach ($this->bundles as $bundle) {
            $event_files = array_merge($event_files, $bundle->getEventFiles());
        }

        return $event_files;
    }


    /**
     * 检查Bundle是否存在
     * @param $name
     * @return mixed
     */
    public function hasBundle($name)
    {
        $_name = $this->snakeName($name);
        return array_key_exists($_name, $this->bundles);
    }

    /**
     * 根据名字获取Bundle
     * @param $name
     * @return mixed|null
     */
    public function getBundle($name)
    {
        $_name = $this->snakeName($name);
        if (isset($this->bundles[$_name])) {
            return $this->bundles[$_name];
        }
        return null;
    }

    /**
     * 检查模块是否存在
     * @param $bundle
     * @param $module
     * @return mixed
     */
    public function hasModule($bundle, $module){
        $_bundle = $this->snakeName($bundle);

        if(!isset($this->bundles[$_bundle])) return false;

        return $this->bundles[$_bundle]->hasModule($module);
    }

    /**
     * 根据名字获取Module
     * @param $bundle
     * @param $name
     * @return null
     */
    public function getModule($bundle, $name)
    {
        $_bundle = $this->snakeName($bundle);
        if (isset($this->bundles[$_bundle])) {
           return $this->bundles[$_bundle]->getModule($name);
        }
        return null;
    }

    /**删除Bundle
     * @param $name
     * @return bool
     */
    public function deleteBundle($name)
    {
        $_name = $this->snakeName($name);
        if (isset($this->bundles[$_name])) {
            unset($this->bundles[$_name]);
        }
        return true;
    }

    /**
     * 获取包相关参数
     * @param $name
     * @return null
     */
    public function getBundleParam($name){
        $_name = $this->snakeName($name);
        if (isset($this->bundles[$_name])) {
            return $this->bundles[$_name]->getParam();
        }
        return null;
    }

    /**
     * 获取模块相关参数
     * @param $bundle
     * @param $name
     * @return mixed|null
     */
    public function getModuleParam($bundle, $name){
        $_bundle = $this->snakeName($bundle);
        if (isset($this->bundles[$_bundle])) {
            $module_param = $this->bundles[$_bundle]->getModuleParam($name, 'parameter');
            return $module_param;
        }

        return null;
    }

    /**
     * 获取模块注册相关
     * @param $bundle
     * @param $module
     * @param $name
     * @return null
     */
    public function getModuleRegisterParam($bundle, $module, $name){
        $_bundle = $this->snakeName($bundle);
        if (isset($this->bundles[$_bundle])) {
            $module_param = $this->bundles[$_bundle]->getModuleParam($module, $name);
            return $module_param;
        }

        return null;
    }

    /**
     * 获取当前pathName
     * @return mixed
     */
    public function pathName(){
        return basename($this->root_path);
    }

    /**
     * 获取当前Bundle
     * @param $_this
     * @return mixed
     */
    public function getCurrentBundle($_this){
        $reflected = new \ReflectionObject($_this);
        $path = str_replace('\\', '/', dirname($reflected->getFileName()));

        $pattern = '/.*\/'.$this->pathName().'\/(.*)\/Modules\/.*/';
        if(preg_match($pattern, $path, $matches) && count($matches) > 1){
            return $this->getBundle($matches[1]);
        }

        return null;
    }

    /**
     * 获取当前Bundle
     * @param $_this
     * @return mixed|null
     */
    public function getCurrentModule($_this){
        $reflected = new \ReflectionObject($_this);
        $path = str_replace('\\', '/', dirname($reflected->getFileName()));

        $pattern = '/.*\/'.$this->pathName().'\/(.*)\/Modules\/(.*)/';
        if(preg_match($pattern, $path, $matches) && count($matches) > 2){
            $bundle = $matches[1];
            $module = substr($matches[2], 0 , stripos($matches[2], '/'));
            return $this->getModule($bundle, $module);
        }

        return null;
    }

    /**
     * 获取使用的时间
     * @return float
     */
    public function getUseTime(){
        return $this->use_time;
    }
}