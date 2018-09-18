# Typecho_WechatShare_Plugin
Typecho 微信分享显示缩略图和简介的插件

## 依赖

php版本7.x，已安装Memcached扩展，服务器上也已经部署Memcached服务。

## 使用方法

### 如果你的域名已备案（未经测试）

1. 注册一个微信公众号（账号主体**不可以**是个人，否则微信的JS API无法使用）
    1. 设置公众号JS接口安全域名为你网站的域名（公众号设置-功能设置-JS接口安全域名）
    2. 开启微信JS API的分享功能（开发-接口权限，网页服务-分享接口）
2. 安装插件并进行配置。


### 如果你的域名没有备案

可以通过测试公众号：

https://mp.weixin.qq.com/debug/cgi-bin/sandbox?t=sandbox/login

实现此功能。限制就是，只有关注了你的测试公众号的微信账号，才能分享带缩略图和描述的链接，别人分享还是普通的链接。

测试公众号中也需要填写js接口安全域名。

## 效果展示

### 未开启插件分享结果样式

![未开启插件分享](https://raw.github.com/frederickjoe/my_images/master/disabled.png)

### 开启插件后分享结果样式

![开启插件分享1](https://raw.github.com/frederickjoe/my_images/master/enabled1.png)
![开启插件分享2](https://raw.github.com/frederickjoe/my_images/master/enabled2.png)

### 配置页面

![配置页面](https://raw.github.com/frederickjoe/my_images/master/settingpage.png)

### 撰写文章页面

![撰写文章页面](https://raw.github.com/frederickjoe/my_images/master/newarticle.png)
