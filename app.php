<?php
use Workerman\Worker;
use Workerman\WebServer;
use Workerman\Lib\Timer;
use PHPSocketIO\SocketIO;
// composer 的 autoload 文件
include __DIR__ . '/vendor/autoload.php';
include __DIR__ . '/vendor/workerman/phpsocket.io/src/autoload.php';

const SOCKET_PORT = 2120;
const HTTP_LISTEN_PORT = 2121;
// 全局数组保存uid在线数据
$connectionMap = array();

$sender_io = new SocketIO(SOCKET_PORT);
$sender_io->on('connection', function($socket){
    $socket->on('login', function ($uid,$viewlevel)use($socket){
        global $connectionMap;
        // 如果已经存在此连接则跳过
        if(isset($socket->uid)){
            return;
        }

        $uid = (string)$uid;
        if(!isset($connectionMap[$uid]))
        {
            $connectionMap[$uid] = array(
                'conn_count' => 0,
                'viewlevel' => $viewlevel);
        }
        // 连接计数
        ++$connectionMap[$uid]['conn_count'];
        // 将这个连接加入到uid分组，方便针对uid推送数据
        $socket->join($uid);
        $socket->uid = $uid;
    });
    
    // 当客户端断开连接是触发（一般是关闭网页或者跳转刷新导致）
    $socket->on('disconnect', function () use($socket) {
        if(!isset($socket->uid))
        {
             return;
        }
        global $connectionMap;
        // 将uid的在线socket数减一
        if(--$connectionMap[$socket->uid]['conn_count'] <= 0)
        {
            unset($connectionMap[$socket->uid]);
        }
    });
});
// 当$sender_io启动后监听一个http端口，通过这个端口可以给任意uid或者所有uid推送数据
$sender_io->on('workerStart', function(){
    // 监听一个http端口
    $inner_http_worker = new Worker('http://0.0.0.0:'.HTTP_LISTEN_PORT);
    // 当http客户端发来数据时触发
    $inner_http_worker->onMessage = function($http_connection, $data){
        // post body的格式
        //  {
        //      'from': 'uid', 发送者的uid
        //      'to': 'uid', 接收者的uid，若为空，则群发
        //      'content': 'text', 消息内容
        //      'viewlevel': '1', 消息接收群体，在to为空时有效
        //      'action': '' 该消息产生的动作
        // }
        $postBody = key($_POST);
        $msg = json_decode($postBody,true);
        // 重新整理消息，取from，content，action三部分推送
        $msgRefined = json_encode(array(
            'from' => $msg['from'],
            'content' => $msg['content'],
            'action' => $msg['action']));
        global $connectionMap, $sender_io;

        // 有指定uid则向uid推送
        if(isset($msg['to']) && !empty($msg['to'])){
            if(isset($connectionMap[$msg['to']])){
                $sender_io->to($msg['to'])->emit('new_msg', $msgRefined);
                // 推送成功则http返回ok，失败返回fail
                return $http_connection->send("{result:ok}");
            }else{
                return $http_connection->send("{result:fail}");
            }
            
        // 否则向viewlevel对应的群体推送
        }else if(isset($msg['viewlevel']) && !empty($msg['viewlevel'])){
            $flag = 0;
            foreach ($connectionMap as $uid => $value){
                if($value['viewlevel'] == $msg['viewlevel']){
                    $sender_io->to($uid)->emit('new_msg', $msgRefined);
                    ++$flag;
                }
            }
            return $http_connection->send(0 == $flag ? "{result:fail}" : "{result:ok}");
        // to和viewlevel都为空时向所有人推送
        }else{
            $sender_io->emit('new_msg', $msgRefined);
            return $http_connection->send("{result:ok}");
        }
        //http接口返回fail
        return $http_connection->send("{result:fail}");
    };
    // 执行监听
    $inner_http_worker->listen();
});

Worker::runAll();