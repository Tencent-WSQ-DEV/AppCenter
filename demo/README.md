demo说明
=========

### 目录结构
- - -
demo/
├── HTTP
│   ├── Request
│   │   └── Exception.php   --http请求异常处理   
│   ├── Request.php         --http请求
│   └── Response.php        --http接收
├── index.php               --入口文件 
└── WSQ.php                 --微社区接口

### 使用说明
- - -
1. WSQ是对微社区接口请求的封装。实例化对象时需要传入参数appId、appSecret、code