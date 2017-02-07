<?php

/**
 * Author: liangwengao
 * Email: 871024608@qq.com
 * Date: 2017-01-25
 * Time: 16:28
 */

namespace Awen\Bundles\Commands;

use Awen\Bundles\Generate\EventListenerGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class GenerateEventCommand extends Command
{
    /**
     * 控制台命令的名称与签名
     * @var string
     */
    protected $name = 'bundle:generate-event';

    /**
     * 控制台命令描述
     * @var string
     */
    protected $description = '生成Event包含Listener';

    /**
     * 执行控制台命令
     * @return mixed
     */
    public function fire()
    {
        $bootstrap = $this->laravel['bundles'];
        if(null === $bootstrap) return;
        $app_kernel = $bootstrap->isBootKernel() ? $bootstrap->getKernel() : null;
        if(null === $app_kernel) return;

        foreach ($app_kernel->getEventFiles() as $key => $events){
            $params = explode('.',$key);
            if(count($params) != 2) continue ;

            list($bundle_name, $module_name) = $params;
            foreach ($events as $event => $listeners) {
                if (! Str::contains($event, '\\')) {
                    continue;
                }

                $event = preg_replace('/@.+$/', '', $event);
                $this->callSilent('bundle:make-event', ['name'=> [$event], '-b' => $bundle_name, '-m' => $module_name]);

                if(is_array($listeners)){
                    foreach ($listeners as $listener) {
                        $listener = preg_replace('/@.+$/', '', $listener);
                        $this->call('bundle:make-listener', ['name' => [$listener], '-b' => $bundle_name,'-m' => $module_name,'-e' => $event]);
                    }
                }else{
                    $listener = preg_replace('/@.+$/', '', $listeners);
                    $this->call('bundle:make-listener', ['name' => [$listener], '-b' => $bundle_name,'-m' => $module_name,'-e' => $event]);
                }
            }
        }

        $this->info('Events and listeners generated successfully!');
    }

}