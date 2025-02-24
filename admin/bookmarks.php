<?php
/* 
 * @Description: 后台网站配置
 * @Author: LyLme admin@lylme.com
 * @Date: 2024-01-23 12:25:35
 * @LastEditors: LyLme admin@lylme.com
 * @LastEditTime: 2024-04-14 14:08:30
 * @FilePath: /lylme_spage/admin/bookmarks.php
 * @Copyright (c) 2024 by LyLme, All Rights Reserved. 
 */
include_once("../include/common.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

$title = '书签导入';
$html = '';

function matchURL($url)
{
    // 添加默认协议处理
    if (strpos($url, '://') === false) {
        $temp_url = 'http://' . $url;
    } else {
        $temp_url = $url;
    }

    // 解析URL获取各部分信息
    $parts = parse_url($temp_url);
    if (!$parts || !isset($parts['host'])) {
        return '';
    }

    // 提取协议、端口和主机名
    $scheme = isset($parts['scheme']) ? $parts['scheme'] : 'http';
    $port = isset($parts['port']) ? ':' . $parts['port'] : '';
    $host = strtolower($parts['host']);

    // 分割域名部分并处理www前缀
    $domains = explode('.', $host);
    if ($domains[0] === 'www') {
        array_shift($domains);
    }

    // 重新拼接主域名及补充信息
    return "{$scheme}://".implode('.', $domains) . $port;
}

/*
function getHeaders($url)
{
    // 发送HTTP头部请求
    $headers = @get_headers($url, 1); // 抑制错误提示
    if (is_array($headers)) {
        // 检查是否成功返回状态码
        $status_line = isset($headers[0]) ? $headers[0] : null;
        if ($status_line !== null) {
            list($http_version, $status_code, $status_text) = explode(' ', $status_line, 3);
            // if ((int)$status_code >= 200 && (int)$status_code < 300) {
            //     return true; // 网站可访问
            // }
            return (int)$status_code;
        }
    }
    return false; // 无法访问或连接失败
}
*/

function get_curl_code($url, $timeout = 5)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_NOBODY, true); // 只获取头部信息
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    $response = curl_exec($ch);

    if ($response === false) {
        return curl_error($ch);
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return $httpCode;
}

function upLoadFile($url, $file)
{
	if (!empty($url)) {
		loadFile($url);
	}
	elseif ($file["type"] == "text/html" && $file["size"] >0 && $file["size"] < 10485760) {
		loadFile($file["tmp_name"]);
	}
	else {
		echo '<script>alert("上传的文件大小超过10MB或类型不符！");history.go(-1);</script>';
		exit();
	}
}

function loadFile($file)
{
	// 读取文件内容
    $content = @file_get_contents($file);
    if ($content === false)
    {
		echo '<script>alert("读取文件异常！");history.go(-1);</script>';
		exit();
	}

    // 使用 DOMDocument 解析 HTML 内容
    $dom = new DOMDocument();
    libxml_use_internal_errors(true); // 忽略 HTML 的一些错误
    $dom->loadHTML($content);

    // 获取所有的 <A> 标签
    $links = $dom->getElementsByTagName('a');

    // 遍历所有的书签并导入到数据库
    if (!empty($links))
    {
    	$bookmarks = [];
	    foreach ($links as $idx => $link)
	    {
	        $name = $link->nodeValue; // 书签名称
	        $url = $link->getAttribute('href'); // 书签链接
	        $icon = $link->getAttribute('icon_uri') ?? $link->getAttribute('ICON'); // 书签图标地址
	        if (!isset($url) || empty($url) || $name == '最近使用的标签' || $url == 'chrome://bookmarks/') {
	        	continue;
	        }

	        if (!isset($icon) || empty($icon)) {
	        	$icon = matchURL($url) . '/favicon.ico';
	        }

	        $bookmarks[] = [
	        	"name" => $name,
				// "group_id" => 10,
				"url" => $url,
				"icon" => $icon,
				"link_desc" => $name,
				"link_status" => 0,
	        ];
	    }
	    unset($link);

	    if(count($bookmarks) > 0)
	    {
	    	global $html;
	    	$tr = '';
	    	$_SESSION['bookmarks'] = $bookmarks;
	    	foreach ($bookmarks as $key => $bookmark)
	    	{
		    	$tr .= '<tr>';
				$tr .= 	'<td><input type="checkbox" name="link-check" value="'. $key .'"></td>';
				$tr .= 	'<td><div style="width: 220px; word-break: break-all;">'. $bookmark['name']. '</div></td>';
				$tr .= 	'<td><div style="width: 300px; word-break: break-all;">';
				if(!empty($bookmark['icon'])) {
					$tr .= 	'<img src="'. $bookmark['icon']. '" width="16" height="16" />';
				}
				$tr .= 	'<a href="'. $bookmark['url']. '" target="_blank">'. $bookmark['url']. '</a></div></td>';
				$tr .= 	'<td><button class="btn btn-info btn-primary" onclick="check_url(\''.$key.'\', this)">检测</button></td>';
				$tr .= 	'<td><button class="btn btn-primary btn-danger" onclick="del_bookmark(\''.$key.'\')">删除</button></td>';
				$tr .= 	'</tr>';				
		    }
		    unset($bookmark);

		    global $DB;
		    $option = '';
			$grouplists = $DB->query("SELECT * FROM lylme_groups ORDER BY group_order ASC");
			while ($grouplist = $DB->fetch($grouplists)) {
				$option .= '<option value="' . $grouplist["group_id"] . '">' . $grouplist["group_id"] . ' - ' . $grouplist["group_name"] . '</option>';
			}

	    	$table .= '<div class="row">';
			$table .= '	<div class="col-lg-12">';
			$table .= '		<div class="card">';
			$table .= '<div id="toolbar" class="toolbar-btn-action">';
	        $table .= '    <button id="btn_delete" type="button" class="btn btn-danger btn-label" onclick="del_bookmarks()">';
            $table .= '    <label><i class="mdi mdi-window-close" aria-hidden="true"></i></label>删除</button>';
			
	        $table .= '<div class="layui-layer-form" style="display: inline-block; position: relative; top: 2px; width:200px; padding: 0 6px;"><div class="form-group">';
	        $table .= '        <select class="form-control" id="group_id" name="group_id">';
	        $table .= $option;
	        $table .= '</select></div></div>';
            $table .= '    <button id="btn_edit" type="button" class="btn btn-success btn-label" onclick="save_bookmarks()">';
	        $table .= '    <label><i class="mdi mdi-check" aria-hidden="true"></i></label>保存</button>';
	        $table .= '</div>';
			$table .= '			<div class="table-responsive">';
			$table .= '		        <table class="table table-striped" id="classlisttbody">';
			$table .= '		          <thead>';
			$table .= '		          	<tr style="cursor: pointer">';
			$table .= '			          <th><input type="checkbox" class="checkbox-parent" id="check_all" onclick="check_all()"></th>';
			$table .= '			          <th>名称</th><th>链接</th><th>可用性</th><th>操作</th>';
			$table .= '			      	</tr>';
			$table .= '			      </thead>';
			$table .= '		          <tbody>';
			$table .= $tr;
			$table .= '			      </tbody>';
			$table .= '			    </table>';
			$table .= '			</div>';
			$table .= '		</div>';
			$table .= '	</div>';
			$table .= '</div>';
			$html = $table;
	    }
	}
	else
	{
		echo '<script>alert("文件中未找到有效链接！");history.go(-1);</script>';
		exit();
	}
}

$set = isset($_GET['set']) ? $_GET['set'] : null;
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $set == 'upload')
{
	$file = $_FILES["bookmark_file"];
	// die(json_encode($file));
	if (!empty($file["name"]) && $file['error'] !== UPLOAD_ERR_OK) {
        echo '<script>alert("文件上传错误！");window.location.href="./bookmarks.php";</script>';
        exit;
    }
	if (empty($file["tmp_name"]) && empty($_POST['bookmark_url'])) {
		echo '<script>alert("请输入书签链接或选择本地书签路径！");window.location.href="./bookmarks.php";</script>';
		exit();
	}
	$bookmarkUrl = $_POST['bookmark_url'];
	upLoadFile($bookmarkUrl, $file);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $set == 'check') {
	header('Content-Type: application/json');
	$bookmarks = $_SESSION['bookmarks'];
	$rawData = file_get_contents("php://input");
    // 将 JSON 数据解码为 PHP 数组
    $data = json_decode($rawData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $idx = intval($data['index']);
    $url = $bookmarks[$idx]['url'];
    if(empty($bookmarks))
    {
		die(json_encode(['code' => -1, 'msg' => '请重新导入分析']));
    }
    if(!empty($url))
    {
    	$httpCode = get_curl_code($url);
		die(json_encode(['code' => 0, 'msg' => $httpCode]));
    }
	die(json_encode(['code' => -1, 'msg' => '参数错误']));
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $set == 'delete') {
	header('Content-Type: application/json');
	$bookmarks = $_SESSION['bookmarks'];
	if(empty($bookmarks))
    {
		die(json_encode(['code' => -1, 'msg' => '请重新导入分析']));
    }
	$rawData = file_get_contents("php://input");
    // 将 JSON 数据解码为 PHP 数组
    $data = json_decode($rawData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $idx = $data['index'];

	// 分割索引字符串
	$indices = explode(',', $idx);

	if(empty($indices))
    {
		die(json_encode(['code' => -1, 'msg' => '参数错误']));
    }

	// 转换为整型数组
	foreach ($indices as &$index) {
	    $index = intval($index);
	}
	unset($index); // 释放引用

	// 过滤掉指定索引的元素
	$bookmarks = array_filter($bookmarks, function ($key) use ($indices) {
	    return !in_array($key, $indices);
	}, ARRAY_FILTER_USE_KEY);

	// 重置数组索引
	// $bookmarks = array_values($bookmarks);
	$_SESSION['bookmarks'] = $bookmarks;

	die(json_encode(['code' => 0, 'msg' => '删除成功']));
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $set == 'save') {
	header('Content-Type: application/json');
	$bookmarks = $_SESSION['bookmarks'];
	if(empty($bookmarks))
    {
		die(json_encode(['code' => -1, 'msg' => '请重新导入分析']));
    }
    $rawData = file_get_contents("php://input");
    // 将 JSON 数据解码为 PHP 数组
    $data = json_decode($rawData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $idx = $data['index'];
    $group_id = $data['group_id'] ?? 1;

	// 分割索引字符串
	$indices = explode(',', $idx);

	if(empty($indices))
    {
		die(json_encode(['code' => -1, 'msg' => '参数错误']));
    }

	// 构建批量插入的 SQL 语句
	global $DB;
    $sql = "INSERT INTO `lylme_links` (`name`, `group_id`, `url`, `icon`, `link_desc`, `link_status`) VALUES ";
    $values = [];

	foreach ($indices as &$index) {
	    // 为每一条数据构建值的部分
	    $index = intval($index);
	    $icon = !empty($bookmarks[$index]['icon']) ? "'" . $DB->escape($bookmarks[$index]['icon']) . "'" : "NULL";
		$link_desc = !empty($bookmarks[$index]['link_desc']) ? "'" . $DB->escape($bookmarks[$index]['link_desc']) . "'" : "NULL";
		$values[] = "('" . $DB->escape($bookmarks[$index]['name']) . "', " 
		                . (int)$group_id . ", '" 
		                . $DB->escape($bookmarks[$index]['url']) . "', " 
		                . $icon . ", " 
		                . $link_desc . ", " 
		                . (int)$bookmarks[$index]['link_status'] . ")";
	}
	unset($index);
	// 合并所有的值部分
	$sql .= implode(", ", $values) . ";";

	$res = ['code' => -1, 'msg' => '保存失败'];
	if($DB->query($sql)) {
		// 过滤掉指定索引的元素
		$bookmarks = array_filter($bookmarks, function ($key) use ($indices) {
		    return !in_array($key, $indices);
		}, ARRAY_FILTER_USE_KEY);
		$_SESSION['bookmarks'] = $bookmarks;

		$res = ['code' => 0, 'msg' => '保存成功'];
	}
	die(json_encode($res, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

// else
// {
include './head.php';
?>

	<script>
		function updatetext(check) {
			document.getElementById(check).innerHTML = "重新选择";
		}
		function check_all() {
		    var ischecked = $("#check_all").prop('checked');
		    if (ischecked == true) {
		        $('[name="link-check"]').prop('checked', true);
		    } else {
		        $('[name="link-check"]').prop('checked', false);
		    }
		}
		function check_url(key, elm) {
			let data = {'index': key};
			postData('check', JSON.stringify(data), elm);
		}
		function del_bookmark(key) {
			let data = {'index': key};
			postData('delete', JSON.stringify(data), key);
		}
		function del_bookmarks() {
			var check = [];
		    $('[name="link-check"]:checked').each(function() {
		        check.push($(this).val());
		    });
			let data = {'index': String(check)};
			postData('delete', JSON.stringify(data), check);
		}
		function save_bookmarks() {
			var check = [];
			var groupId = $('#group_id').val() ?? 1;
		    $('[name="link-check"]:checked').each(function() {
		        check.push($(this).val());
		    });
			let data = {
				'index': String(check),
				'group_id': groupId
			};
			postData('save', JSON.stringify(data), check);
		}
		function postData(type = 'check', formData = '{}', eml = "") {
			$.ajax({
                url: 'bookmarks.php?set=' + type, // 替换为你的服务器端脚本地址
                type: 'POST',
                data: formData,
                cache: false, // 禁止缓存                
                success: function(response) {
                	if(type !== 'check') {
						alert(response.msg);
                	}
                    if(type == 'check' && !!eml) {
                    	$(eml).before(`<span style="display: inline-block; width: 160px; word-break: break-all;">httpCode: ${response.msg} </span>`)
                    } 
                    else if (type == 'delete') {
                    	if(!!eml) {
                    		if(typeof eml === 'string') {
                    			$(`input[type="checkbox"][value="${eml}"]`).parents('tr').remove();
                    		} else {
                    			eml.forEach(item => {
                    				$(`input[type="checkbox"][value="${item}"]`).parents('tr').remove();
                    			})
                    		}
                    	}
                    } 
                    else if (type == 'save' && response.code == 0) {
                    	if(!!eml) {
                    		if(typeof eml === 'string') {
                    			$(`input[type="checkbox"][value="${eml}"]`).parents('tr').remove();
                    		} else {
                    			eml.forEach(item => {
                    				$(`input[type="checkbox"][value="${item}"]`).parents('tr').remove();
                    			})
                    		}
                    	}
						// window.location.href='./bookmarks.php';
                    }
                },
                error: function(xhr, textStatus, errorThrown) {
                    // 处理错误情况
                    console.log('Error:', textStatus, errorThrown);
                    alert("服务错误，请稍候重试~");
                }
            });
		}
	</script>
	<!--页面主要内容-->
	<main class="lyear-layout-content">
		<div class="container-fluid">
			<div class="row">
				<div class="col-lg-12">
					<div class="card">
						<div class="tab-content">
							<div class="tab-pane active">
								<form action="bookmarks.php?set=upload" method="post" name="edit-form" class="edit-form" enctype="multipart/form-data">
									<div class="form-group">
										<label for="bookmark_url">书签文件</label>
										<div class="input-group">
											<input type="text" class="form-control" name="bookmark_url" id="bookmark_url" value="" />
											<div class="input-group-btn">
												<label class="btn btn-default" id="bookmark_btn" for="bookmark_file" type="button">选择书签文件</label>
												<input type="file" style="display:none" accept=".html" id="bookmark_file" name="bookmark_file" onclick="updatetext('bookmark_btn');" />
											</div>
										</div>
										<small class="help-block">可填写书签的URL，默认值：<code><?php echo siteurl() ?>/assets/bookmarks.html</code>或从<code>本地上传</code></small>
									</div>
					
									<div class="form-group">
										<button type="submit" class="btn btn-primary m-r-5">导入分析</button>
									</div>
								</form>
							</div>
						</div>
					</div>
				</div>
			</div>

			<?=$html?>
		</div>
	</main>
	<!--End 页面主要内容-->
<?php
// }
include './footer.php';
?>