# livelink-php

提供livelink接口（PHP）的封装,具体可参考[接入文档](https://livelink.qq.com/doc/activities/)

### 请求示例 
> 可以直接使用do_request发起请求，内部自己计算签名、code等信息
```php

// 创建请求客户端
$cli = new Client("your secKey", "your sigKey");

// 创建请求头信息
$req = new ReqParam(6351, "yxzj", "huya", new PlatUser("xxxxx"));

// 直接发起流程调用请求
$str = $cli->do_request($req,"ApiRequest", array(
    "flowId" => "xxxx"
));

```

### 签名计算 
> 如果只需要计算签名、code的能力，可以调用trans_args方法进行参数的转换输出
```php 
// 创建请求客户端
$cli = new Client("your secKey", "your sigKey");

// 转换签名、code等参数 
$args = $cli->trans_args($req)

// 转换成query参数 
echo http_build_query($args)

```