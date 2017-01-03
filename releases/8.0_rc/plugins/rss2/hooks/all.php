<?php

function HookRss2AllPreheaderoutput()
	{
	if(!function_exists("get_api_key"))
		{
		include __DIR__ . "/../../../include/api_functions.php";
		}
	}

function HookRss2AllSearchbarbeforebottomlinks()
	{
 	global $baseurl,$lang,$userpassword,$username,$userref,$api_scramble_key;
	
	$query="user=" . base64_encode($username) . "&search=!last50";
	$private_key = get_api_key($userref);
	// Sign the query using the private key
	$sign=hash("sha256",$private_key . $query);
	?>
	<p><i aria-hidden="true" class="fa fa-fw fa-rss"></i>&nbsp;<a href="<?php echo $baseurl?>/plugins/rss2/pages/rssfilter.php?<?php echo $query; ?>&sign=<?php echo urlencode($sign); ?>"><?php echo $lang["new_content_rss_feed"]; ?></a></p>
	<?php
	}
