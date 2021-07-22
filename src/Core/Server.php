<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2021/7/22 0022
 * Time: 9:54
 */
namespace Lxj\Laravel\Tars\Core;

use Symfony\Component\HttpFoundation\HeaderBag;
use Tars\core\Request;
use Tars\core\Response;
use Tars\core\TarsPlatform;
use Tars\protocol\ProtocolFactory;

class Server extends \Tars\core\Server {
    public function __construct($conf)
    {
        parent::__construct($conf);
        //swoole高版本中有checkOption方法，对多余log_path参数会报异常，因此需要释放
        unset($this->setting['log_path']);
    }

    // 这里应该找到对应的解码协议类型,执行解码,并在收到逻辑处理回复后,进行编码和发送数据
    // 这里重写PHPTars/tars-server/Server的接收请求的方法，将上下文写入全局变量
    public function onReceive($server, $fd, $fromId, $data)
    {
        $resp = new Response();
        $resp->fd = $fd;
        $resp->fromFd = $fromId;
        $resp->server = $server;

        // 处理管理端口的特殊逻辑
        $unpackResult = \TUPAPI::decodeReqPacket($data);
        $sServantName = $unpackResult['sServantName'];
        $sFuncName = $unpackResult['sFuncName'];

        $objName = explode('.', $sServantName)[2];

        if (!isset(self::$paramInfos[$objName]) || !isset(self::$impl[$objName])) {
            App::getLogger()->error(__METHOD__ . " objName $objName not found.");
            $resp->send('');
            //TODO 这里好像可以直接返回一个taf error code 提示obj 不存在的
            return;
        }

        $req = new Request();
        $req->reqBuf = $data;
        $req->paramInfos = self::$paramInfos[$objName];
        $req->impl = self::$impl[$objName];
        // 把全局对象带入到请求中,在多个worker之间共享
        $req->server = $this->sw;

        // 处理管理端口相关的逻辑
        if ($sServantName === 'AdminObj') {
            TarsPlatform::processAdmin($this->tarsConfig, $unpackResult, $sFuncName, $resp, $this->sw->master_pid);
        }

        $impl = $req->impl;
        $paramInfos = $req->paramInfos;
        $protocol = ProtocolFactory::getProtocol($this->servicesInfo[$objName]['protocolName']);
        try {
            // 这里通过protocol先进行unpack
            $result = $protocol->route($req, $resp, $this->tarsConfig);
            if (is_null($result)) {
                return;
            } else {
                $sFuncName = $result['sFuncName'];
                $args = $result['args'];
                $unpackResult = $result['unpackResult'];

                if (method_exists($impl, $sFuncName)) {
                    //将tcp附加的请求头，写入headers
                    request()->headers = new HeaderBag($unpackResult['context']);
                    $returnVal = $impl->$sFuncName(...$args);
                } else {
                    throw new \Exception(Code::TARSSERVERNOFUNCERR);
                }
                $paramInfo = $paramInfos[$sFuncName];
                $rspBuf = $protocol->packRsp($paramInfo, $unpackResult, $args, $returnVal);
                $resp->send($rspBuf);
                return;
            }
        } catch (\Exception $e) {
            $unpackResult['iVersion'] = 1;
            $rspBuf = $protocol->packErrRsp($unpackResult, $e->getCode(), $e->getMessage());
            $resp->send($rspBuf);
            return;
        }

    }
}