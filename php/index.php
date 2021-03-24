<?php
define('Version', 'v0.0.1');
define('ReleaseTime', '2021.03.23');
// 最大尝试次数(全局)
$MaxRetryGlobal = 5;
// 是否允许重复图片
$AllowRepeatImage = false;
// 存放的图片目录(暂时不支持子目录图片,会消耗大量资源)
$ImageSaveDir = array(
    // 本地路径(相对于index.php)
    'localPath' => __DIR__ . '/../testImgFull/',
    // 访问路径(相对于站点根目录)
    'accessPath' => '/testImgFull/',
);

// 图片随机获取,不完善,TODO
function getRandomImage() {
    global $ImageSaveDir;
    // 最大尝试次数(单函数)
    $MaxRetryFunc = 10;
    // 允许的图像后缀
    $AllowExt = array('png', 'jpg', 'jpeg', 'bmp', 'webp');
    $randomImageData = array('image_url' => '', 'success' => false, 'message' => '没有找到可以被选择的图像文件');
    $images = scandir($ImageSaveDir['localPath']);
    if (!empty($images)) {
        for ($o = 1; $o <= $MaxRetryFunc; $o++) {
            $tmpImageUrl = $images[rand(2, sizeof($images) - 1)];
            if (in_array(pathinfo($tmpImageUrl, PATHINFO_EXTENSION), $AllowExt)) {
                $randomImageData['success'] = true;
                $randomImageData['message'] = '获取成功';
                $randomImageData['image_url'] = $ImageSaveDir['accessPath'] . $tmpImageUrl;
                break;
            }
        }
    }
    return $randomImageData;
}

if (isset($_REQUEST['func_type'])) {
    if (trim($_REQUEST['func_type']) == 'refresh_image') {
        // 获取图片
        $respData = array('image_url' => '', 'success' => false, 'message' => '无法获取到不重复的图片');
        $lastImageUrl = isset($_REQUEST['now_image_url']) ? trim($_REQUEST['now_image_url']) : '';
        for ($i = 1; $i <= $MaxRetryGlobal; $i++) {
            $imageResult = getRandomImage();
            if (!$imageResult['success']) {
                $respData['success'] = false;
                $respData['message'] = '无法获取随机图像: ' . $imageResult['message'];
                break;
            }
			if ($lastImageUrl != $imageResult['image_url']) {
				$respData['success'] = true;
				$respData['message'] = '获取成功';
				$respData['image_url'] = $imageResult['image_url'];
				break;
			} else {
				if ($AllowRepeatImage) {
					$respData['success'] = true;
					$respData['message'] = '获取成功';
					$respData['image_url'] = $imageResult['image_url'];
					break;
				}
			}
        }
        exit(json_encode($respData));
    }
}
// 状态信息显示
echo '<!-- Simple Image Player PHP ' . Version . ' ' . ReleaseTime . ' -->' . PHP_EOL;
?>

<!--

Simple Image Player

前端修改自 https://moe.ci

-->
<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<title>Simple Image Player</title>
		<meta name="Description" content="Simple Image Player" />
		<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
		<script type="text/javascript" src="https://cdn.bootcss.com/jquery/2.2.4/jquery.min.js"></script>
		<script src="https://cdn.bootcdn.net/ajax/libs/layer/3.1.1/layer.min.js"></script>
		<style>
			* {
				padding: 0;
				margin: 0;
				box-sizing: border-box;
			}

			.imageDiv {
				width: 100vw;
				height: 100vh;
			}

			#imageContent {
				width: 100%;
				height: 100%;
				object-fit: contain;
			}
		</style>
	</head>
	<body>
		<div class="imageDiv">
			<img id="imageContent" src="" />
		</div>
		
		<script>
		// 加载错误时图片,必须为base64
		var errorImgUrl = 'data:image/jpeg;base64,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';
		</script>
		
		<script>
			var refreshImageLock = false;
			var nowImageUrl = '';

			// 修改图片路径
			var changeImage = function(imageUrl) {
					console.log('Image Change to ' + imageUrl);
					$("#imageContent").attr('src', imageUrl);
					if (imageUrl != errorImgUrl) {
						nowImageUrl = imageUrl;
					}
					return true;
			};
			
			// 页面加载时占位
			changeImage(errorImgUrl);
			
			// 刷新图片
			var refreshImage = function(deviceType) {
					if (refreshImageLock) {
						console.warn('Refresh Image is Locked');
						return false;
					}
					refreshImageLock = true;
					layer.load();
					var result = false;
					$.ajax({
						type: 'GET',
						url: '',
						async: false,
						data: {
							'func_type': 'refresh_image',
							'device_type': deviceType,
							'now_image_url': nowImageUrl
						},
						dataType: 'JSON',
						success: function(result) {
							if (result.success) {
								changeImage(result.image_url);
								result = true;
							} else {
								alert(result.message);
								console.error('Refresh Image Failed (Server Error): ', result.message);
							}
						},
						error: function(XMLHttpRequest, textStatus, errorThrown) {
							alert('与服务器的连接遇到问题');
							console.error('Refresh Image Failed (Ajax Error): ', XMLHttpRequest, textStatus, errorThrown);
						}
					});
					refreshImageLock = false;
					if (!result) {
						layer.closeAll('loading');
					}
					return result;
			};
			
			// 图片加载失败处理
			$('#imageContent').on('error', function(){
				// $(this).unbind('error');
				layer.closeAll('loading');
				if ($("#imageContent")[0].src == errorImgUrl) {
					// 加载失败提示图片也加载失败了,停止套娃
					return false;
				}
				layer.load();
				$(this).attr('src', errorImgUrl);
				nowImageUrl = '';
			});
			
			// 图片加载处理
			$('#imageContent').on('load', function(){
				if ($("#imageContent")[0].complete != true) {
					return false;
				}
				layer.closeAll('loading');
			});
			
			// 检查是否是手机
			var IsMobile = function() {
					var isMobile = {
						Android: function() {
							return navigator.userAgent.match(/Android/i) ? true : false;
						},
						BlackBerry: function() {
							return navigator.userAgent.match(/BlackBerry/i) ? true : false;
						},
						iOS: function() {
							return navigator.userAgent.match(/iPhone|iPad|iPod/i) ? true : false;
						},
						Windows: function() {
							return navigator.userAgent.match(/IEMobile/i) ? true : false;
						},
						any: function() {
							return (isMobile.Android() || isMobile.BlackBerry() || isMobile.iOS() || isMobile.Windows());
						}
					};
					return isMobile.any();
			};
			
			// 检查设备是否支持Touch Event并且是否是移动设备
			var checkTouchSupport = function(){
				if (IsMobile()) {
					try {
						document.createEvent("TouchEvent");
						console.log('为移动端设备并支持Touch Event');
						return true;
					} catch (e) {
						console.warn('该移动端设备不支持Touch Event,切换到PC模式: ' + e.message);
						alert('非常抱歉,该移动端设备不支持Touch Event,已切换到PC模式');
						return false;
					}
				} else {
					console.log('非移动端设备');
					return false;
				}
			};
			
			// 根据不同平台执行不同方案
			if (checkTouchSupport()) {
				// 移动端
				var touchMoveNumber = 0;
				var touchLock = false;
				var lastTouchX = 0;
				var lastTouchY = 0;
				var lastTouchLockTime = parseInt(new Date().getTime()/1000);
				// var minTouchLockTime = 0.8;
				// var minTouchLockTime = (window.innerHeight/16) * 0.01;
				var minTouchLockTime = 0;
				
				$('#imageContent').on('touchstart', function(e) {
					e.preventDefault();
					if (touchLock) {
						return false;
					} else {
						if (parseInt(new Date().getTime()/1000) - lastTouchLockTime < minTouchLockTime) {
							return false;
						}
					}
					touchMoveNumber = 0;
					lastTouchX = 0;
					lastTouchY = 0;
					touchLock = true;
					lastTouchLockTime = parseInt(new Date().getTime()/1000);

				});
				$('#imageContent').on('touchmove', function(e) {
					// 测试方案,可能出现问题
					var testMode = true;
					
					e.preventDefault();
					if (!touchLock) {
						return false;
					}
					if (testMode) {
						console.warn('正在使用测试方案,如存在问题请关闭');
						try {
							var touchChanged = false;
							// 获取第一个触点
							var tmpTouch = e.originalEvent.touches[0];
							// 页面触点X坐标
							var tmpTouchX = Number(tmpTouch.pageX);
							// 页面触点Y坐标
							var tmpTouchY = Number(tmpTouch.pageY);
							console.log('testMode X,Y Point: ', tmpTouchX, tmpTouchY);
							if (lastTouchX != tmpTouchX) {
								var touchXChangeNum = lastTouchX - tmpTouchX;
								if (touchXChangeNum >= 6 || touchXChangeNum <= -6) {
									touchChanged = true;
								}
							}
							if (lastTouchY != tmpTouchY && !touchChanged) {
								var touchYChangeNum = lastTouchY - tmpTouchY;
								if (touchYChangeNum >= 6 || touchYChangeNum <= -6) {
									touchChanged = true;
								}
							}
							if (touchChanged) {
								lastTouchX = tmpTouchX;
								lastTouchY = tmpTouchY;
								touchMoveNumber++;
							}
						} catch (excep) {
							console.warn('触点检查失败,降级: ' + excep.message);
							touchMoveNumber++;
						}
					} else {
						touchMoveNumber++;
					}
				});
				$('#imageContent').on('touchend', function(e) {
					// 点击会算1次move,所以2次move及以上才算滑动
					e.preventDefault();
					if (!touchLock) {
						return false;
					}
					if (touchMoveNumber >= 2) {
						refreshImage('mobile');
					}
					touchMoveNumber = 0;
					touchLock = false;
					lastTouchLockTime = parseInt(new Date().getTime()/1000);
					lastTouchX = 0;
					lastTouchY = 0;
				});
				
				// 初始化
				refreshImage('mobile');
			} else {
				// PC端
				$("#imageContent").click(function() {
					refreshImage('pc');
				});
				$('#imageContent').on('mousedown', function(e) {
					e.preventDefault();
				});
				$(document).keydown(function(e){
					if (e.keyCode == 37 || e.keyCode == 38 || e.keyCode == 39 || e.keyCode == 40) {
						// 上下左右方向键均可切换图片
						refreshImage('pc');
					}
				});
				
				// 初始化
				refreshImage('pc');
			};
		</script>
	</body>

</html>