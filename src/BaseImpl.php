<?php
namespace Lxj\Laravel\Tars;

class BaseImpl {
    /**
     * The middleware defined on the Impl.
     *
     * @var array
     */
    protected $middleware = [];

    public function __construct()
    {
        //添加中间件，中间件是事件命令，事件的监听自己添加，事件的触发在swoole的onReceive方法里，接收消息后，调用impl的对应方法时，会自动触发中间件；
        //同步事件
        //事件正常执行不要返回数据
    }

    /**
     * Define a middleware on the controller.
     *
     * @param  string  $middleware
     * @param  array  $options
     * @return void
     */
    public function middleware($middleware, array $options = [])
    {
        $this->middleware[$middleware] = $options;
    }

    /**
     * Get the middleware for a given method.
     *
     * @param  string  $method
     * @return array
     */
    public function getMiddlewareForMethod($method)
    {
        $middleware = [];

        foreach ($this->middleware as $name => $options) {
            if (isset($options['only']) && ! in_array($method, (array) $options['only'])) {
                continue;
            }

            if (isset($options['except']) && in_array($method, (array) $options['except'])) {
                continue;
            }

            $middleware[] = $name;
        }

        return $middleware;
    }
}