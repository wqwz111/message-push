# message-push
这个推送服务端是基于[web-message-sender](https://github.com/walkor/web-msg-sender)修改的。它只能运行在linux平台。
该服务占用端口为`2120`和`2121`，可以在*start.php*中修改

## 后台使用

启动服务(服务启动后可以关闭终端)：
```shell
php app.php start -d
```
停止服务：
```shell
php app.php stop
```
查看服务状态：
```shell
php app.php status
```
调试模式：
```shell
php app.php start
```
*调试模式下，`var_dump`,`var_export`和`echo`的内容都会在终端显示。终端关闭后，服务会终止。


## API调用

通过http发送POST到端口2121完成推送。

`Content-Type: application/json`
```json
{
"from":"123", 
"to":"223",
"content":"helloworld",
"viewlevel":"2",
"action":"1"
}
```

* `from`, 发送者uid。
* `to`, 接收者uid，留空时为群发。
* `content`, 发送的消息内容。
* `viewlevel`, 接收消息的群体。
* `action`, 消息产生的动作。
