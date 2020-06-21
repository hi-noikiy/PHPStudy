<?php
namespace App\Handler;

use App\Events\MessageReceived;
use App\User;
use Hhxsv5\LaravelS\Swoole\Task\Event;
use Hhxsv5\LaravelS\Swoole\WebSocketHandlerInterface;
use Illuminate\Support\Facades\Log;

/**
 * 很简单，就是在建立、断开 WebSocket 连接的时候打印下日志，然后在收到客户端发送过来的弹幕消息时
 * 将其推送给所有已连接的 WebSocket 客户端，达到「广播」的效果，这样，就不需要客户端主动来拉数据了。
 * 当然，这里是最简单的推送逻辑，你可以根据需要将弹幕消息保存到数据库或其他存储设备持久化存储。
 */
class WebSocketHandler implements WebSocketHandlerInterface
{
    public function __construct()
    {

    }

    /**
     * 连接建立时触发
     *
     * @param \Swoole\WebSocket\Server $server
     * @param \Swoole\Http\Request $request
     * @return void
     */
    public function onOpen(\Swoole\WebSocket\Server $server, \Swoole\Http\Request $request)
    {
        Log::info('WebSocket连接建立：'.$request->fd);
    }

    /**
     * 收到消息时触发
     *
     * @param \Swoole\WebSocket\Server $server
     * @param \Swoole\WebSocket\Frame $frame
     * @return void
     */
    public function onMessage(\Swoole\WebSocket\Server $server, \Swoole\WebSocket\Frame $frame)
    {
        // $frame->fd 是客户端 id，$frame->data 是客户端发送的数据
        Log::info("从 {$frame->fd} 接收到的数据：{$frame->data}");
        $message = json_decode($frame->data);
        // 基于Token的用户认证校验
        // WebSocket 连接与之前认证使用的 HTTP 连接是不同的连接，所以认证逻辑也是独立的，
        // 不能简单通过 Auth 那种方式判断，那一套逻辑仅适用于 HTTP 通信。
        if (empty($message->token) || ($user = User::where('api_token', $message->token)->first())) {
            Log::warning('用户'.$message->name.'已经离线，不能发送消息');
            $server->push($frame->fd, "离线用户不能发送消息"); // 告知用户离线不能发送消息
        } else {
            // 触发消息接收事件
            $event = new MessageReceived($message, $user->id);
            Event::fire($event);
            unset($message->token); // 从消息中去掉当前用户令牌字段
            // 广播消息
            foreach ($server->connections as $fd) {
                if (!$server->isEstablished($fd)) {
                    // 如果连接不可用则忽略
                    continue;
                }
                $server->push($fd, $frame->data); // 向所有连接的客户端发送数据
            }
        }

    }

    /**
     * 连接关闭时触发
     *
     * @param \Swoole\WebSocket\Server $server
     * @param int $fd
     * @param int $reactorId
     * @return void
     */
    public function onClose(\Swoole\WebSocket\Server $server, $fd, $reactorId)
    {
        Log::info('WebSocket连接关闭：'.$fd);
    }
}
